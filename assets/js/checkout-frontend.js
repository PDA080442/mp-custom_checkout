/**
 * MP Custom Checkout — базовая навигация шагов.
 */
(function ($) {
	'use strict';

	var selectors = {
		root: '#mp-cc-checkout',
		app: '#mp-cc-checkout-app'
	};
	var flagNames = {
		multiStepFlow: 'multi_step_flow',
		multiPickupPoints: 'multi_pickup_points',
		conditionsStep: 'conditions_step',
		discountPlacement: 'discount_block_placement',
		checkoutTestingMode: 'checkout_testing_mode',
		adminLivePreview: 'admin_live_preview'
	};

	function parseContext() {
		var root = document.querySelector(selectors.root);
		if (!root) {
			return {};
		}

		var raw = root.getAttribute('data-mp-cc-context');
		if (!raw) {
			return {};
		}

		try {
			return JSON.parse(raw) || {};
		} catch (e) {
			return {};
		}
	}

	function buildState(context) {
		var flow = context.checkout_flow || {};
		var contextFlags = context.feature_flags || {};
		var localizedFlags = (window.mpCcCheckout && window.mpCcCheckout.flags) ? window.mpCcCheckout.flags : {};
		var allSteps = Array.isArray(flow.steps) ? flow.steps : [];
		var visibleIds = Array.isArray(flow.visible_steps) ? flow.visible_steps : [];
		var currentStep = flow.current_step || (visibleIds[0] || '');
		var visible = [];
		var i;

		for (i = 0; i < visibleIds.length; i += 1) {
			var stepId = visibleIds[i];
			var found = null;
			var j;

			for (j = 0; j < allSteps.length; j += 1) {
				if (allSteps[j] && allSteps[j].id === stepId) {
					found = allSteps[j];
					break;
				}
			}

			if (!found) {
				found = { id: stepId, label: stepId, validation_mode: 'server' };
			}

			visible.push(found);
		}

		if (!currentStep && visible.length) {
			currentStep = visible[0].id;
		}

		var currentIndex = getStepIndex(visible, currentStep);
		if (currentIndex < 0) {
			currentIndex = 0;
			currentStep = visible.length ? visible[0].id : '';
		}

		return {
			context: context,
			flowContextId: flow.context_id || '',
			visibleSteps: visible,
			currentStepId: currentStep,
			maxReachedIndex: currentIndex,
			isTransitioning: false,
			frontendStore: {
				answers: flow.answers || {},
				scenario: flow.scenario || '',
				expiresAt: flow.expires_at || 0
			},
			featureFlags: $.extend({}, contextFlags, localizedFlags)
		};
	}

	function getStepIndex(steps, stepId) {
		var i;
		for (i = 0; i < steps.length; i += 1) {
			if (steps[i] && steps[i].id === stepId) {
				return i;
			}
		}
		return -1;
	}

	function postCheckout(subAction, payload) {
		var localized = window.mpCcCheckout || {};
		if (!localized.ajaxUrl || !localized.nonce) {
			return $.Deferred().resolve({ success: true }).promise();
		}

		return $.ajax({
			url: localized.ajaxUrl,
			method: 'POST',
			dataType: 'json',
			data: $.extend(
				{
					action: 'mp_cc_checkout',
					nonce: localized.nonce,
					sub_action: subAction,
					context_id: (payload && payload.context_id) ? payload.context_id : ''
				},
				payload || {}
			)
		});
	}

	function stepKeyById(stepId) {
		if (stepId === 'cart') {
			return 'step_one';
		}
		if (stepId === 'date' || stepId === 'conditions') {
			return 'date_conditions';
		}
		if (stepId === 'contact_payment') {
			return 'contact_billing';
		}
		return stepId;
	}

	function syncFromFlow(state, flow) {
		var nextFlow = flow || {};
		state.context.checkout_flow = nextFlow;
		state.flowContextId = nextFlow.context_id || state.flowContextId || '';
		state.frontendStore.answers = nextFlow.answers || {};
		state.frontendStore.scenario = nextFlow.scenario || '';
		state.frontendStore.expiresAt = nextFlow.expires_at || 0;
	}

	function requestForwardValidation(stepId) {
		var deferred = $.Deferred();
		var detail = {
			stepId: stepId,
			valid: true,
			resolve: function (isValid) {
				deferred.resolve(Boolean(isValid));
			}
		};

		var event = new CustomEvent('mp_cc_before_step_forward', {
			detail: detail
		});
		document.dispatchEvent(event);

		if (typeof window.mpCcValidateStep === 'function') {
			try {
				var fnResult = window.mpCcValidateStep(stepId);
				if (fnResult && typeof fnResult.then === 'function') {
					fnResult.then(function (ok) {
						deferred.resolve(Boolean(ok));
					}).catch(function () {
						deferred.resolve(false);
					});
					return deferred.promise();
				}
				deferred.resolve(Boolean(fnResult));
				return deferred.promise();
			} catch (e) {
				deferred.resolve(false);
				return deferred.promise();
			}
		}

		deferred.resolve(Boolean(detail.valid));
		return deferred.promise();
	}

	function withTransitionLock(state, $app, task) {
		if (state.isTransitioning) {
			return $.Deferred().reject().promise();
		}

		state.isTransitioning = true;
		$app.attr('data-nav-lock', '1').addClass('is-nav-lock');
		$app.find('button, a').attr('aria-disabled', 'true');

		var done = function () {
			state.isTransitioning = false;
			$app.attr('data-nav-lock', '0').removeClass('is-nav-lock');
			$app.find('button, a').removeAttr('aria-disabled');
		};

		return task().always(done);
	}

	function scrollToStepTop() {
		var root = document.querySelector(selectors.root);
		if (!root) {
			return;
		}
		root.scrollIntoView({ behavior: 'smooth', block: 'start' });
	}

	function setCurrentStep(state, $app, targetStepId) {
		var targetIndex = getStepIndex(state.visibleSteps, targetStepId);
		if (targetIndex < 0) {
			return $.Deferred().reject().promise();
		}

		return withTransitionLock(state, $app, function () {
			return postCheckout('session_set_step', { step_id: targetStepId, context_id: state.flowContextId }).then(function () {
				state.currentStepId = targetStepId;
				state.maxReachedIndex = Math.max(state.maxReachedIndex, targetIndex);
				render(state, $app);
				scrollToStepTop();

				document.dispatchEvent(
					new CustomEvent('mp_cc_step_changed', {
						detail: {
							stepId: targetStepId,
							index: targetIndex + 1,
							total: state.visibleSteps.length
						}
					})
				);
			});
		});
	}

	function moveBackward(state, $app) {
		var currentIndex = getStepIndex(state.visibleSteps, state.currentStepId);
		if (currentIndex <= 0) {
			return;
		}
		var target = state.visibleSteps[currentIndex - 1];
		setCurrentStep(state, $app, target.id);
	}

	function moveForward(state, $app) {
		var currentIndex = getStepIndex(state.visibleSteps, state.currentStepId);
		if (currentIndex < 0 || currentIndex >= state.visibleSteps.length - 1) {
			return;
		}
		var target = state.visibleSteps[currentIndex + 1];
		requestForwardValidation(state.currentStepId).then(function (valid) {
			if (!valid) {
				return;
			}
			setCurrentStep(state, $app, target.id);
		});
	}

	function saveCurrentStepDraft(state) {
		var stepId = state.currentStepId;
		if (!stepId) {
			return $.Deferred().resolve().promise();
		}

		var storageKey = stepKeyById(stepId);
		var payload = state.frontendStore.answers && state.frontendStore.answers[storageKey]
			? state.frontendStore.answers[storageKey]
			: {};

		return postCheckout('session_set_answers', {
			step_id: stepId,
			context_id: state.flowContextId,
			answers: payload
		});
	}

	function buildProgressHtml(state) {
		var currentIndex = getStepIndex(state.visibleSteps, state.currentStepId);
		var html = '';
		var i;

		html += '<ol class="mp-cc-progress" role="list" aria-label="Checkout steps">';
		for (i = 0; i < state.visibleSteps.length; i += 1) {
			var step = state.visibleSteps[i];
			var canGo = i <= state.maxReachedIndex;
			var isCurrent = i === currentIndex;
			var classes = ['mp-cc-progress__item'];

			if (isCurrent) {
				classes.push('is-active');
			}
			if (i < currentIndex) {
				classes.push('is-complete');
			}

			html += '<li class="' + classes.join(' ') + '">';
			html += '<button type="button" class="mp-cc-progress__btn" data-step="' + step.id + '"';
			html += canGo ? '' : ' disabled';
			html += isCurrent ? ' aria-current="step"' : '';
			html += '>';
			html += '<span class="mp-cc-progress__index">' + (i + 1) + '</span>';
			html += '<span class="mp-cc-progress__label">' + escapeHtml(step.label || step.id) + '</span>';
			html += '</button>';
			html += '</li>';
		}
		html += '</ol>';

		return html;
	}

	function buildStepPanelHtml(state) {
		var currentIndex = getStepIndex(state.visibleSteps, state.currentStepId);
		var step = currentIndex >= 0 ? state.visibleSteps[currentIndex] : null;
		var label = step ? (step.label || step.id) : '';
		var html = '';

		html += '<section class="mp-cc-step-panel" data-step-panel="' + escapeHtml(step ? step.id : '') + '">';
		html += '<header class="mp-cc-step-panel__header">';
		html += '<p class="mp-cc-step-panel__meta">Step ' + (currentIndex + 1) + ' / ' + state.visibleSteps.length + '</p>';
		html += '<h2 class="mp-cc-step-panel__title">' + escapeHtml(label) + '</h2>';
		html += '</header>';
		html += '<div class="mp-cc-step-panel__content" data-mp-cc-step-slot="' + escapeHtml(step ? step.id : '') + '"></div>';
		if (!isFlagEnabled(state, flagNames.discountPlacement, true)) {
			html += '<p class="mp-cc-step-panel__hint">Discount tools are rendered inline in payment step.</p>';
		}
		if (!isFlagEnabled(state, flagNames.multiPickupPoints, false)) {
			html += '<p class="mp-cc-step-panel__hint">Single pickup point mode is active.</p>';
		}
		if (isFlagEnabled(state, flagNames.checkoutTestingMode, false)) {
			html += '<p class="mp-cc-step-panel__hint">Checkout testing mode is enabled.</p>';
		}
		html += '</section>';

		return html;
	}

	function buildNavHtml(state) {
		if (!isFlagEnabled(state, flagNames.multiStepFlow, true)) {
			return '';
		}
		var currentIndex = getStepIndex(state.visibleSteps, state.currentStepId);
		var isFirst = currentIndex <= 0;
		var isLast = currentIndex >= state.visibleSteps.length - 1;
		var html = '';

		html += '<nav class="mp-cc-nav" aria-label="Step navigation">';
		html += '<button type="button" class="mp-cc-nav__btn mp-cc-nav__btn--back" data-nav="back"' + (isFirst ? ' disabled' : '') + '>Back</button>';
		html += '<button type="button" class="mp-cc-nav__btn mp-cc-nav__btn--next" data-nav="next"' + (isLast ? ' disabled' : '') + '>Next</button>';
		html += '</nav>';
		return html;
	}

	function render(state, $app) {
		if (!state.visibleSteps.length) {
			$app.html('<p class="mp-cc-empty">No steps available.</p>');
			return;
		}

		var html = '';
		html += '<div class="mp-cc-nav-shell" data-step-count="' + state.visibleSteps.length + '">';
		if (isFlagEnabled(state, flagNames.multiStepFlow, true)) {
			html += buildProgressHtml(state);
		}
		html += buildStepPanelHtml(state);
		html += buildNavHtml(state);
		html += '</div>';

		$app.html(html);
		bindHandlers(state, $app);

		document.dispatchEvent(
			new CustomEvent('mp_cc_store_synced', {
				detail: {
					contextId: state.flowContextId,
					store: state.frontendStore
				}
			})
		);
	}

	function bindHandlers(state, $app) {
		if (!isFlagEnabled(state, flagNames.multiStepFlow, true)) {
			return;
		}

		$app.find('[data-nav="back"]').off('click').on('click', function () {
			moveBackward(state, $app);
		});

		$app.find('[data-nav="next"]').off('click').on('click', function () {
			saveCurrentStepDraft(state).always(function () {
				moveForward(state, $app);
			});
		});

		$app.find('.mp-cc-progress__btn').off('click').on('click', function () {
			var target = $(this).data('step');
			if (!target) {
				return;
			}
			var targetIndex = getStepIndex(state.visibleSteps, target);
			if (targetIndex < 0 || targetIndex > state.maxReachedIndex) {
				return;
			}
			setCurrentStep(state, $app, String(target));
		});
	}

	function isFlagEnabled(state, flag, fallback) {
		if (!state || !state.featureFlags || typeof state.featureFlags !== 'object') {
			return Boolean(fallback);
		}
		if (!Object.prototype.hasOwnProperty.call(state.featureFlags, flag)) {
			return Boolean(fallback);
		}
		return Boolean(state.featureFlags[flag]);
	}

	function escapeHtml(value) {
		return String(value || '')
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#39;');
	}

	$(function () {
		var $app = $(selectors.app);
		if (!$app.length) {
			return;
		}

		var state = buildState(parseContext());
		render(state, $app);

		postCheckout('session_get_state', { context_id: state.flowContextId }).then(function (response) {
			if (!response || !response.success || !response.data || !response.data.flow) {
				return;
			}
			syncFromFlow(state, response.data.flow);
			var rehydrated = buildState(state.context);
			state.visibleSteps = rehydrated.visibleSteps;
			state.currentStepId = rehydrated.currentStepId;
			state.maxReachedIndex = Math.max(state.maxReachedIndex, rehydrated.maxReachedIndex);
			state.frontendStore = rehydrated.frontendStore;
			state.flowContextId = rehydrated.flowContextId;
			render(state, $app);
		});

		document.addEventListener('mp_cc_scenario_changed', function (event) {
			var nextScenario = event && event.detail ? String(event.detail.scenario || '') : '';
			if (!nextScenario) {
				return;
			}

			withTransitionLock(state, $app, function () {
				return postCheckout('session_set_scenario', { scenario: nextScenario, context_id: state.flowContextId })
					.then(function () {
						return postCheckout('session_get_state', { context_id: state.flowContextId });
					})
					.then(function (response) {
						if (!response || !response.success || !response.data || !response.data.flow) {
							return;
						}

						syncFromFlow(state, response.data.flow);
						var nextState = buildState(state.context);
						state.visibleSteps = nextState.visibleSteps;
						state.currentStepId = nextState.currentStepId;
						state.maxReachedIndex = Math.max(state.maxReachedIndex, nextState.maxReachedIndex);
						state.frontendStore = nextState.frontendStore;
						state.flowContextId = nextState.flowContextId;
						render(state, $app);
					});
			});
		});

		document.addEventListener('mp_cc_store_update', function (event) {
			var detail = event && event.detail ? event.detail : {};
			var bucket = detail.bucket ? String(detail.bucket) : '';
			var payload = detail.payload || {};
			if (!bucket) {
				return;
			}
			state.frontendStore.answers[bucket] = payload;
		});

		document.addEventListener('visibilitychange', function () {
			if (document.visibilityState === 'hidden') {
				saveCurrentStepDraft(state);
			}
		});
	});
})(jQuery);
