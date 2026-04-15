/**
 * MP Custom Checkout — базовая навигация шагов.
 */
(function ($) {
	'use strict';

	var selectors = {
		root: '#mp-cc-checkout',
		app: '#mp-cc-checkout-app',
		progress: '#mp-cc-progress-container',
		actions: '#mp-cc-navigation-actions',
		summary: '#mp-cc-summary-sidebar',
		notifications: '#mp-cc-notifications'
	};
	var flagNames = {
		multiStepFlow: 'multi_step_flow',
		multiPickupPoints: 'multi_pickup_points',
		conditionsStep: 'conditions_step',
		discountPlacement: 'discount_block_placement',
		checkoutTestingMode: 'checkout_testing_mode',
		adminLivePreview: 'admin_live_preview'
	};
	var animationDurationMs = 180;

	function getUiText(path, fallback) {
		var source = (window.mpCcCheckout && window.mpCcCheckout.uiText) ? window.mpCcCheckout.uiText : {};
		var parts = String(path || '').split('.');
		var node = source;
		var i;
		for (i = 0; i < parts.length; i += 1) {
			if (!node || typeof node !== 'object' || !Object.prototype.hasOwnProperty.call(node, parts[i])) {
				return fallback;
			}
			node = node[parts[i]];
		}
		return (typeof node === 'string' && node !== '') ? node : fallback;
	}

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

	function applyThemeVariant(context) {
		var root = document.querySelector(selectors.root);
		if (!root) {
			return;
		}

		var designTokens = (window.mpCcCheckout && window.mpCcCheckout.designTokens) ? window.mpCcCheckout.designTokens : {};
		var variant = '';
		if (designTokens && typeof designTokens === 'object' && designTokens.theme_variant) {
			variant = String(designTokens.theme_variant);
		} else if (context && context.design_tokens && context.design_tokens.theme_variant) {
			variant = String(context.design_tokens.theme_variant);
		}

		root.classList.remove('mp-cc-theme-luxe');
		if (variant.toLowerCase() === 'luxe' || variant.toLowerCase() === 'luxury') {
			root.classList.add('mp-cc-theme-luxe');
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
			frontendStore: createFrontendStore(flow, visible, allSteps, currentStep),
			featureFlags: $.extend({}, contextFlags, localizedFlags)
		};
	}

	function createFrontendStore(flow, visibleSteps, allSteps, currentStepId) {
		var answers = flow.answers || {};
		var contactBilling = answers.contact_billing || {};
		var paymentGateway = '';
		if (contactBilling && typeof contactBilling === 'object') {
			paymentGateway = contactBilling.payment_gateway || contactBilling.gateway || '';
		}

		return {
			steps: {
				current: currentStepId || '',
				visible: visibleSteps || [],
				all: allSteps || []
			},
			cart: {
				snapshot: flow.snapshot || {},
				summary: {},
				items: []
			},
			form: {
				contact: contactBilling,
				errors: {}
			},
			fulfillment: {
				scenario: flow.scenario || '',
				date: answers.date_conditions || {},
				scenarioData: answers.scenario || {}
			},
			discounts: answers.discounts || { coupons: [], gift_card: [] },
			payment: {
				gateway: paymentGateway || '',
				state: 'idle'
			},
			runtime: {
				loading: false,
				success: false,
				blocked: false,
				dirty: false,
				lastSyncAt: Date.now()
			},
			meta: {
				contextId: flow.context_id || '',
				expiresAt: flow.expires_at || 0
			}
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
		state.frontendStore = createFrontendStore(
			nextFlow,
			state.visibleSteps,
			(state.frontendStore.steps && state.frontendStore.steps.all) ? state.frontendStore.steps.all : [],
			state.currentStepId
		);
		state.frontendStore.runtime.loading = false;
		state.frontendStore.runtime.blocked = false;
		state.frontendStore.runtime.dirty = false;
		state.frontendStore.runtime.lastSyncAt = Date.now();
		var contextCart = state.context && state.context.cart ? state.context.cart : {};
		state.frontendStore.cart.items = Array.isArray(contextCart.items) ? contextCart.items : [];
		state.frontendStore.cart.summary = contextCart.summary || {};
	}

	function setRuntimeFlag(state, key, value) {
		if (!state || !state.frontendStore || !state.frontendStore.runtime) {
			return;
		}
		state.frontendStore.runtime[key] = Boolean(value);
	}

	function syncStoreWithBackend(state, $app) {
		return postCheckout('session_get_state', { context_id: state.flowContextId }).then(function (response) {
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
		setRuntimeFlag(state, 'loading', true);
		$app.attr('data-nav-lock', '1').addClass('is-nav-lock is-loading');
		$(selectors.summary).addClass('is-loading');
		$app.find('button, a').attr('aria-disabled', 'true');
		$(selectors.actions).find('.mp-cc-nav__btn').prop('disabled', true);

		var done = function () {
			state.isTransitioning = false;
			setRuntimeFlag(state, 'loading', false);
			$app.attr('data-nav-lock', '0').removeClass('is-nav-lock is-loading');
			$(selectors.summary).removeClass('is-loading');
			$app.find('button, a').removeAttr('aria-disabled');
			$(selectors.actions).find('.mp-cc-nav__btn').prop('disabled', false);
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
			runStepTransitionAnimation($app);
			return postCheckout('session_set_step', { step_id: targetStepId, context_id: state.flowContextId }).then(function () {
				state.currentStepId = targetStepId;
				state.frontendStore.steps.current = targetStepId;
				state.maxReachedIndex = Math.max(state.maxReachedIndex, targetIndex);
				setRuntimeFlag(state, 'blocked', false);
				render(state, $app);
				scrollToStepTop();
				focusStepHeading($app);

				document.dispatchEvent(
					new CustomEvent('mp_cc_step_changed', {
						detail: {
							stepId: targetStepId,
							index: targetIndex + 1,
							total: state.visibleSteps.length
						}
					})
				);
				return syncStoreWithBackend(state, $app);
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
				setRuntimeFlag(state, 'blocked', true);
				notify('Заполните обязательные поля текущего шага.', 'error');
				return;
			}
			setRuntimeFlag(state, 'blocked', false);
			setCurrentStep(state, $app, target.id);
		});
	}

	function saveCurrentStepDraft(state) {
		var stepId = state.currentStepId;
		if (!stepId) {
			return $.Deferred().resolve().promise();
		}

		var storageKey = stepKeyById(stepId);
		var payload = getDraftPayloadByStorageKey(state, storageKey);
		setRuntimeFlag(state, 'dirty', true);

		return postCheckout('session_set_answers', {
			step_id: stepId,
			context_id: state.flowContextId,
			answers: payload
		}).then(function () {
			setRuntimeFlag(state, 'dirty', false);
		});
	}

	function getDraftPayloadByStorageKey(state, storageKey) {
		if (!state || !state.frontendStore) {
			return {};
		}
		if (storageKey === 'step_one') {
			return state.frontendStore.cart.snapshot || {};
		}
		if (storageKey === 'date_conditions') {
			return state.frontendStore.fulfillment.date || {};
		}
		if (storageKey === 'contact_billing') {
			return state.frontendStore.form.contact || {};
		}
		if (storageKey === 'scenario') {
			return state.frontendStore.fulfillment.scenarioData || {};
		}
		if (storageKey === 'discounts') {
			return state.frontendStore.discounts || {};
		}
		return {};
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
		html += '<h2 class="mp-cc-step-panel__title" id="mp-cc-step-heading" tabindex="-1">' + escapeHtml(label) + '</h2>';
		html += '</header>';
		html += '<div class="mp-cc-step-panel__content" data-mp-cc-step-slot="' + escapeHtml(step ? step.id : '') + '">';
		if (step && step.id === 'cart') {
			html += buildCartItemsHtml(state);
		}
		html += '</div>';
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

	function buildCartItemsHtml(state) {
		var items = state.frontendStore && state.frontendStore.cart && Array.isArray(state.frontendStore.cart.items)
			? state.frontendStore.cart.items
			: [];
		var html = '';

		html += '<section class="mp-cc-cart-list" aria-label="' + escapeHtml(getUiText('step_1.title', 'Cart items')) + '">';
		html += '<div class="mp-cc-cart-list__items" data-mp-cc-item-list="1">';

		if (!items.length) {
			html += '<div class="mp-cc-cart-list__empty">';
			html += '<p class="mp-cc-empty">' + escapeHtml(getUiText('step_1.empty_cart', 'Cart is empty')) + '</p>';
			html += '</div>';
			html += '</div></section>';
			return html;
		}

		for (var i = 0; i < items.length; i += 1) {
			var item = items[i] || {};
			var itemKey = String(item.key || 'item-' + i);
			var productId = Number(item.product_id || 0);
			var variationId = Number(item.variation_id || 0);
			var title = item.name ? String(item.name) : getUiText('step_1.title', 'Product');
			var priceHtml = item.price_html ? String(item.price_html) : '';
			var sku = item.sku ? String(item.sku) : '';
			var variationText = item.variation_text ? String(item.variation_text) : '';
			var imageUrl = item.image_url ? String(item.image_url) : '';
			var qty = Number(item.quantity || 0);
			var subtotal = item.line_subtotal ? String(item.line_subtotal) : '';

			html += '<article class="mp-cc-cart-item"';
			html += ' data-cart-item-key="' + escapeHtml(itemKey) + '"';
			html += ' data-product-id="' + escapeHtml(productId) + '"';
			html += ' data-variation-id="' + escapeHtml(variationId) + '"';
			html += '>';
			html += '<div class="mp-cc-cart-item__media">';
			if (imageUrl) {
				html += '<img src="' + escapeHtml(imageUrl) + '" alt="" loading="lazy" />';
			} else {
				html += '<div class="mp-cc-cart-item__placeholder" aria-hidden="true"></div>';
			}
			html += '</div>';
			html += '<div class="mp-cc-cart-item__body">';
			html += '<h3 class="mp-cc-cart-item__title" title="' + escapeHtml(title) + '">' + escapeHtml(title) + '</h3>';
			if (priceHtml) {
				html += '<div class="mp-cc-cart-item__price">' + priceHtml + '</div>';
			}
			html += '<dl class="mp-cc-cart-item__meta">';
			if (sku) {
				html += '<div class="mp-cc-cart-item__meta-row"><dt>SKU</dt><dd>' + escapeHtml(sku) + '</dd></div>';
			}
			if (variationText) {
				html += '<div class="mp-cc-cart-item__meta-row"><dt>' + escapeHtml(getUiText('step_1.positions_count', 'Details')) + '</dt><dd>' + escapeHtml(variationText) + '</dd></div>';
			}
			html += '</dl>';
			html += '<div class="mp-cc-cart-item__footer">';
			html += '<span class="mp-cc-cart-item__qty" data-cart-qty="' + escapeHtml(qty) + '">' + escapeHtml(qty) + ' ×</span>';
			html += '<span class="mp-cc-cart-item__subtotal">' + (subtotal || '—') + '</span>';
			html += '</div>';
			html += '</div>';
			html += '</article>';
		}

		html += '</div></section>';
		return html;
	}

	function buildNavHtml(state) {
		if (!isFlagEnabled(state, flagNames.multiStepFlow, true)) {
			return '';
		}
		var currentIndex = getStepIndex(state.visibleSteps, state.currentStepId);
		var isFirst = currentIndex <= 0;
		var isLast = currentIndex >= state.visibleSteps.length - 1;
		var isLoading = !!(state.frontendStore && state.frontendStore.runtime && state.frontendStore.runtime.loading);
		var isDirty = !!(state.frontendStore && state.frontendStore.runtime && state.frontendStore.runtime.dirty);
		var backLabel = getUiText('common.back', 'Back');
		var nextLabel = getUiText('common.next', 'Next');
		var payLabel = getUiText('common.pay', 'Proceed to payment');
		var confirmLabel = getUiText('common.confirm', 'Confirm');
		var currentStepId = state.currentStepId || '';
		var nextText = nextLabel;
		if (isLast && currentStepId === 'contact_payment') {
			nextText = state.frontendStore && state.frontendStore.payment && state.frontendStore.payment.gateway ? confirmLabel : payLabel;
		}
		var html = '';

		html += '<nav class="mp-cc-nav" aria-label="Step navigation">';
		html += '<button type="button" class="mp-cc-nav__btn mp-cc-nav__btn--back" data-nav="back"' + (isFirst || isLoading ? ' disabled' : '') + '>' + escapeHtml(backLabel) + '</button>';
		html += '<button type="button" class="mp-cc-nav__btn mp-cc-nav__btn--next" data-nav="next"' + (isLoading ? ' disabled' : '') + '>' + escapeHtml(nextText) + '</button>';
		html += '</nav>';
		if (isDirty) {
			html += '<p class="mp-cc-nav__dirty" role="status" aria-live="polite">' + escapeHtml('Unsaved changes') + '</p>';
		}
		return html;
	}

	function buildSummaryHtml(state) {
		var currentIndex = getStepIndex(state.visibleSteps, state.currentStepId);
		var total = state.visibleSteps.length;
		var snapshot = state.frontendStore && state.frontendStore.cart ? state.frontendStore.cart.snapshot || {} : {};
		var itemsCount = snapshot.items_count || 0;
		var totalText = snapshot.total || '';
		var html = '';

		html += '<section class="mp-cc-summary-card" aria-label="Order summary panel">';
		html += '<h3 class="mp-cc-summary-card__title">Order Summary</h3>';
		html += '<p class="mp-cc-summary-card__meta">Step ' + (currentIndex + 1) + ' of ' + total + '</p>';
		html += '<p class="mp-cc-summary-card__meta">Items: ' + escapeHtml(itemsCount) + '</p>';
		if (totalText) {
			html += '<p class="mp-cc-summary-card__meta">Total: ' + escapeHtml(totalText) + '</p>';
		}
		html += '<div class="mp-cc-summary-card__slot" data-mp-cc-summary-slot="1"></div>';
		html += '</section>';

		return html;
	}

	function notify(message, level) {
		var container = document.querySelector(selectors.notifications);
		if (!container) {
			return;
		}
		var safeMessage = escapeHtml(message || '');
		var safeLevel = level === 'error' ? 'error' : 'info';
		container.innerHTML = '<div class="mp-cc-notice mp-cc-notice--' + safeLevel + '" role="alert">' + safeMessage + '</div>';
	}

	function focusStepHeading($app) {
		var heading = $app.find('#mp-cc-step-heading').get(0);
		if (!heading || typeof heading.focus !== 'function') {
			return;
		}
		try {
			heading.focus({ preventScroll: true });
		} catch (e) {
			heading.focus();
		}
	}

	function prefersReducedMotion() {
		return !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
	}

	function runStepTransitionAnimation($app) {
		if (prefersReducedMotion()) {
			return;
		}
		$app.addClass('is-step-transition');
		window.setTimeout(function () {
			$app.removeClass('is-step-transition');
		}, animationDurationMs);
	}

	function render(state, $app) {
		var $progress = $(selectors.progress);
		var $actions = $(selectors.actions);
		var $summary = $(selectors.summary);

		if (!state.visibleSteps.length) {
			$app.html('<p class="mp-cc-empty">No steps available.</p>');
			$progress.empty();
			$actions.empty();
			$summary.empty();
			return;
		}

		$app.html(buildStepPanelHtml(state));
		$summary.html(buildSummaryHtml(state));
		if (isFlagEnabled(state, flagNames.multiStepFlow, true)) {
			$progress.html(buildProgressHtml(state));
			$actions.html(buildNavHtml(state));
		} else {
			$progress.empty();
			$actions.empty();
		}
		bindHandlers(state, $app, $progress, $actions);
		focusStepHeading($app);

		document.dispatchEvent(
			new CustomEvent('mp_cc_store_synced', {
				detail: {
					contextId: state.flowContextId,
					store: state.frontendStore
				}
			})
		);
	}

	function bindHandlers(state, $app, $progress, $actions) {
		if (!isFlagEnabled(state, flagNames.multiStepFlow, true)) {
			return;
		}

		$actions.find('[data-nav="back"]').off('click').on('click', function () {
			moveBackward(state, $app);
		});

		$actions.find('[data-nav="next"]').off('click').on('click', function () {
			saveCurrentStepDraft(state).always(function () {
				moveForward(state, $app);
			});
		});

		$progress.find('.mp-cc-progress__btn').off('click').on('click', function () {
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

		var context = parseContext();
		applyThemeVariant(context);
		var state = buildState(context);
		render(state, $app);

		syncStoreWithBackend(state, $app).fail(function () {
			notify('Не удалось восстановить состояние checkout.', 'error');
		});

		document.addEventListener('mp_cc_scenario_changed', function (event) {
			var nextScenario = event && event.detail ? String(event.detail.scenario || '') : '';
			if (!nextScenario) {
				return;
			}

			withTransitionLock(state, $app, function () {
				return postCheckout('session_set_scenario', { scenario: nextScenario, context_id: state.flowContextId })
					.then(function () {
						return syncStoreWithBackend(state, $app);
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
			if (bucket === 'step_one') {
				state.frontendStore.cart.snapshot = payload;
			} else if (bucket === 'date_conditions') {
				state.frontendStore.fulfillment.date = payload;
			} else if (bucket === 'contact_billing') {
				state.frontendStore.form.contact = payload;
				if (payload && typeof payload === 'object') {
					state.frontendStore.payment.gateway = payload.payment_gateway || payload.gateway || '';
				}
			} else if (bucket === 'scenario') {
				state.frontendStore.fulfillment.scenarioData = payload;
			} else if (bucket === 'discounts') {
				state.frontendStore.discounts = payload;
			}
			setRuntimeFlag(state, 'dirty', true);
		});

		document.addEventListener('visibilitychange', function () {
			if (document.visibilityState === 'hidden') {
				saveCurrentStepDraft(state);
			}
		});

		document.addEventListener('mp_cc_checkout_success', function () {
			setRuntimeFlag(state, 'success', true);
		});
	});
})(jQuery);
