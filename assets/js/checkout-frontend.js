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
				lastSyncAt: Date.now(),
				summaryHydrated: false
			},
			meta: {
				contextId: flow.context_id || '',
				expiresAt: flow.expires_at || 0,
				lastSummarySignature: ''
			}
		};
	}

	function normalizeCartPayload(cartPayload) {
		var safePayload = cartPayload && typeof cartPayload === 'object' ? cartPayload : {};
		var safeSummary = (safePayload.summary && typeof safePayload.summary === 'object') ? safePayload.summary : {};
		var fallbackCatalogUrl = (window.mpCcCheckout && window.mpCcCheckout.checkoutUrl) ? String(window.mpCcCheckout.checkoutUrl) : '/';
		if (safePayload && safePayload.home_url) {
			fallbackCatalogUrl = String(safePayload.home_url);
		}
		return {
			items: Array.isArray(safePayload.items) ? safePayload.items : [],
			summary: $.extend(
				{
					items_count: 0,
					subtotal: '',
					catalog_url: fallbackCatalogUrl
				},
				safeSummary
			)
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

	function syncFromFlow(state, flow, cartPayload) {
		var nextFlow = flow || {};
		state.context.checkout_flow = nextFlow;
		if (cartPayload && typeof cartPayload === 'object') {
			state.context.cart = normalizeCartPayload(cartPayload);
		}
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
		state.frontendStore.runtime.summaryHydrated = true;
		var contextCart = normalizeCartPayload(state.context && state.context.cart ? state.context.cart : {});
		state.frontendStore.cart.items = contextCart.items;
		state.frontendStore.cart.summary = contextCart.summary;
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
			syncFromFlow(state, response.data.flow, response.data.cart || {});
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
		var cartSummary = state.frontendStore && state.frontendStore.cart ? state.frontendStore.cart.summary || {} : {};
		if (state.currentStepId === 'cart' && Number(cartSummary.items_count || 0) <= 0) {
			notify(getUiText('step_1.empty_cart', 'Cart is empty'), 'error');
			return;
		}
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
			var emptySummary = state.frontendStore && state.frontendStore.cart ? (state.frontendStore.cart.summary || {}) : {};
			var catalogUrl = emptySummary.catalog_url ? String(emptySummary.catalog_url) : '/';
			html += '<div class="mp-cc-cart-list__empty">';
			html += '<p class="mp-cc-empty">' + escapeHtml(getUiText('step_1.empty_cart', 'Cart is empty')) + '</p>';
			html += '<a class="mp-cc-cart-list__cta" href="' + escapeHtml(catalogUrl) + '">' + escapeHtml(getUiText('step_1.btn_choose_gifts', 'Return to catalog')) + '</a>';
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
			var minQty = Number(item.min_quantity || 1);
			var maxQty = Number(item.max_quantity || 9999);
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
			html += '<div class="mp-cc-cart-item__qty-controls" role="group" aria-label="' + escapeHtml(getUiText('step_1.positions_count', 'Quantity')) + '">';
			html += '<button type="button" class="mp-cc-qty-btn" data-qty-action="decrease" data-cart-qty-btn="-1" aria-label="Decrease quantity"' + (qty <= minQty ? ' disabled' : '') + '>−</button>';
			html += '<input class="mp-cc-qty-input" type="number" inputmode="numeric" min="' + escapeHtml(minQty) + '" max="' + escapeHtml(maxQty) + '" step="1" value="' + escapeHtml(qty) + '" data-cart-qty-input="1" aria-label="Quantity" />';
			html += '<button type="button" class="mp-cc-qty-btn" data-qty-action="increase" data-cart-qty-btn="+1" aria-label="Increase quantity"' + (qty >= maxQty ? ' disabled' : '') + '>+</button>';
			html += '</div>';
			html += '<button type="button" class="mp-cc-cart-item__remove" data-cart-remove="1" aria-label="Remove item">' + escapeHtml(getUiText('common.remove', 'Remove')) + '</button>';
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
		var cartSummary = state.frontendStore && state.frontendStore.cart ? state.frontendStore.cart.summary || {} : {};
		var isCartEmpty = (state.currentStepId === 'cart') && Number(cartSummary.items_count || 0) <= 0;
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
		html += '<button type="button" class="mp-cc-nav__btn mp-cc-nav__btn--next" data-nav="next"' + (isLoading || isCartEmpty ? ' disabled' : '') + '>' + escapeHtml(nextText) + '</button>';
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
		var cartSummary = state.frontendStore && state.frontendStore.cart ? state.frontendStore.cart.summary || {} : {};
		var runtime = state.frontendStore && state.frontendStore.runtime ? state.frontendStore.runtime : {};
		var showPlaceholders = !runtime.summaryHydrated;
		var itemsCount = cartSummary.items_count || snapshot.items_count || 0;
		var subtotalText = cartSummary.subtotal || '';
		var totalText = snapshot.total || subtotalText || '';
		var displayAmount = state.currentStepId === 'cart' ? subtotalText : totalText;
		var amountLabel = state.currentStepId === 'cart' ? 'Subtotal' : 'Total';
		var returnUrl = cartSummary.catalog_url ? String(cartSummary.catalog_url) : '/';
		var html = '';

		html += '<section class="mp-cc-summary-card" aria-label="Order summary panel">';
		html += '<h3 class="mp-cc-summary-card__title">Order Summary</h3>';
		html += '<p class="mp-cc-summary-card__meta">Step ' + (currentIndex + 1) + ' of ' + total + '</p>';
		if (showPlaceholders) {
			html += '<div class="mp-cc-summary-card__placeholder" aria-hidden="true"></div>';
			html += '<div class="mp-cc-summary-card__placeholder mp-cc-summary-card__placeholder--sm" aria-hidden="true"></div>';
		} else {
			html += '<p class="mp-cc-summary-card__meta">Items: <strong>' + escapeHtml(itemsCount) + '</strong></p>';
			if (displayAmount) {
				html += '<p class="mp-cc-summary-card__meta"><span class="mp-cc-summary-card__amount-label">' + escapeHtml(amountLabel) + ':</span> <span class="mp-cc-summary-card__amount" data-summary-amount="1">' + displayAmount + '</span></p>';
			}
		}
		if (state.currentStepId === 'cart') {
			html += '<div class="mp-cc-summary-card__actions">';
			html += '<button type="button" class="mp-cc-summary-card__btn mp-cc-summary-card__btn--primary" data-summary-action="continue">Continue</button>';
			html += '<a href="' + escapeHtml(returnUrl) + '" class="mp-cc-summary-card__btn mp-cc-summary-card__btn--ghost">' + escapeHtml(getUiText('step_1.btn_choose_gifts', 'Return to shop')) + '</a>';
			html += '</div>';
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
		animateSummaryUpdate(state, $summary);
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

		$(selectors.summary).find('[data-summary-action="continue"]').off('click').on('click', function () {
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

		$app.find('[data-cart-qty-btn]').off('click').on('click', function () {
			var $btn = $(this);
			var $item = $btn.closest('[data-cart-item-key]');
			if (!$item.length) {
				return;
			}
			var $input = $item.find('[data-cart-qty-input]');
			var current = Number($input.val() || 0);
			var delta = Number($btn.data('cart-qty-btn') || 0);
			if (!delta) {
				return;
			}
			applyQuantityChange(state, $app, $item, current + delta);
		});

		$app.find('[data-cart-qty-input]').off('change blur').on('change blur', function () {
			var $input = $(this);
			var $item = $input.closest('[data-cart-item-key]');
			if (!$item.length) {
				return;
			}
			applyQuantityChange(state, $app, $item, Number($input.val() || 0));
		});

		$app.find('[data-cart-remove]').off('click').on('click', function () {
			var $btn = $(this);
			var $item = $btn.closest('[data-cart-item-key]');
			if (!$item.length) {
				return;
			}
			applyRemoveItem(state, $app, $item);
		});
	}

	function clampQuantity(nextQty, minQty, maxQty) {
		var safeQty = Number(nextQty || 0);
		if (!Number.isFinite(safeQty)) {
			safeQty = minQty;
		}
		safeQty = Math.round(safeQty);
		if (safeQty < minQty) {
			safeQty = minQty;
		}
		if (safeQty > maxQty) {
			safeQty = maxQty;
		}
		return safeQty;
	}

	function updateLocalCartItem(state, itemKey, quantity, lineSubtotal) {
		if (!state || !state.frontendStore || !state.frontendStore.cart || !Array.isArray(state.frontendStore.cart.items)) {
			return;
		}
		for (var i = 0; i < state.frontendStore.cart.items.length; i += 1) {
			if (String(state.frontendStore.cart.items[i].key || '') === String(itemKey || '')) {
				state.frontendStore.cart.items[i].quantity = quantity;
				if (typeof lineSubtotal === 'string' && lineSubtotal !== '') {
					state.frontendStore.cart.items[i].line_subtotal = lineSubtotal;
				}
				break;
			}
		}
	}

	function getLocalCartItem(state, itemKey) {
		if (!state || !state.frontendStore || !state.frontendStore.cart || !Array.isArray(state.frontendStore.cart.items)) {
			return null;
		}
		for (var i = 0; i < state.frontendStore.cart.items.length; i += 1) {
			if (String(state.frontendStore.cart.items[i].key || '') === String(itemKey || '')) {
				return state.frontendStore.cart.items[i];
			}
		}
		return null;
	}

	function removeLocalCartItem(state, itemKey) {
		if (!state || !state.frontendStore || !state.frontendStore.cart || !Array.isArray(state.frontendStore.cart.items)) {
			return false;
		}
		var items = state.frontendStore.cart.items;
		for (var i = 0; i < items.length; i += 1) {
			if (String(items[i].key || '') === String(itemKey || '')) {
				items.splice(i, 1);
				state.frontendStore.cart.summary.items_count = Math.max(0, Number(state.frontendStore.cart.summary.items_count || 0) - 1);
				return true;
			}
		}
		return false;
	}

	function applyQuantityChange(state, $app, $item, requestedQty) {
		var itemKey = String($item.data('cart-item-key') || '');
		var $input = $item.find('[data-cart-qty-input]');
		var $decrease = $item.find('[data-qty-action="decrease"]');
		var $increase = $item.find('[data-qty-action="increase"]');
		if (!itemKey || !$input.length) {
			return;
		}

		var minQty = Number($input.attr('min') || 1);
		var maxQty = Number($input.attr('max') || 9999);
		var localItem = getLocalCartItem(state, itemKey);
		var prevQty = Number(localItem && localItem.quantity ? localItem.quantity : minQty);
		var nextQty = clampQuantity(requestedQty, minQty, maxQty);
		if (nextQty === prevQty || state.isTransitioning) {
			$input.val(nextQty);
			return;
		}
		$item.addClass('is-updating');
		$input.val(nextQty);
		$decrease.prop('disabled', nextQty <= minQty);
		$increase.prop('disabled', nextQty >= maxQty);
		updateLocalCartItem(state, itemKey, nextQty, '');
		render(state, $app);

		postCheckout('update_quantity', {
			context_id: state.flowContextId,
			item_key: itemKey,
			quantity: nextQty
		}).then(function (response) {
			if (!response || !response.success || !response.data) {
				throw new Error('update_quantity_failed');
			}
			var payload = response.data;
			var nextFlow = payload.flow || state.context.checkout_flow || {};
			var nextCart = normalizeCartPayload(payload.cart || {});
			syncFromFlow(state, nextFlow, nextCart);
			if (payload.item && payload.item.key) {
				updateLocalCartItem(state, payload.item.key, Number(payload.item.quantity || nextQty), String(payload.item.line_subtotal || ''));
			}
			render(state, $app);
		}).fail(function (xhr) {
			var errorPayload = xhr && xhr.responseJSON && xhr.responseJSON.data ? xhr.responseJSON.data : {};
			var message = errorPayload.message || 'Не удалось обновить количество. Попробуйте снова.';
			updateLocalCartItem(state, itemKey, prevQty, '');
			notify(message, 'error');
			syncStoreWithBackend(state, $app);
		}).always(function () {
			$item.removeClass('is-updating');
		});
	}

	function applyRemoveItem(state, $app, $item) {
		var itemKey = String($item.data('cart-item-key') || '');
		if (!itemKey || state.isTransitioning) {
			return;
		}

		var existingItem = getLocalCartItem(state, itemKey);
		if (!existingItem) {
			return;
		}
		var snapshotItem = $.extend({}, existingItem);
		var previousItemsCount = Number(state.frontendStore.cart.summary.items_count || 0);
		var previousSubtotal = String(state.frontendStore.cart.summary.subtotal || '');
		var previousFlowTotal = String(state.frontendStore.cart.snapshot && state.frontendStore.cart.snapshot.total ? state.frontendStore.cart.snapshot.total : '');

		$item.addClass('is-removing');
		removeLocalCartItem(state, itemKey);
		if (Number(state.frontendStore.cart.summary.items_count || 0) === 0) {
			state.frontendStore.cart.summary.subtotal = '';
			if (state.frontendStore.cart.snapshot && typeof state.frontendStore.cart.snapshot === 'object') {
				state.frontendStore.cart.snapshot.total = '';
			}
		}
		render(state, $app);

		postCheckout('remove_item', {
			context_id: state.flowContextId,
			item_key: itemKey
		}).then(function (response) {
			if (!response || !response.success || !response.data) {
				throw new Error('remove_item_failed');
			}
			var payload = response.data;
			var nextFlow = payload.flow || state.context.checkout_flow || {};
			var nextCart = normalizeCartPayload(payload.cart || {});
			syncFromFlow(state, nextFlow, nextCart);
			render(state, $app);
			if (payload.is_empty) {
				notify(getUiText('step_1.empty_cart', 'Cart is empty'), 'info');
			}
		}).fail(function (xhr) {
			var errorPayload = xhr && xhr.responseJSON && xhr.responseJSON.data ? xhr.responseJSON.data : {};
			var message = errorPayload.message || 'Не удалось удалить позицию. Попробуйте снова.';
			state.frontendStore.cart.items.push(snapshotItem);
			state.frontendStore.cart.summary.items_count = previousItemsCount;
			state.frontendStore.cart.summary.subtotal = previousSubtotal;
			if (state.frontendStore.cart.snapshot && typeof state.frontendStore.cart.snapshot === 'object') {
				state.frontendStore.cart.snapshot.total = previousFlowTotal;
			}
			render(state, $app);
			notify(message, 'error');
			syncStoreWithBackend(state, $app);
		});
	}

	function animateSummaryUpdate(state, $summary) {
		if (!$summary || !$summary.length || !state || !state.frontendStore || !state.frontendStore.meta) {
			return;
		}
		var cartSummary = state.frontendStore.cart && state.frontendStore.cart.summary ? state.frontendStore.cart.summary : {};
		var snapshot = state.frontendStore.cart && state.frontendStore.cart.snapshot ? state.frontendStore.cart.snapshot : {};
		var signature = String(cartSummary.items_count || 0) + '|' + String(cartSummary.subtotal || '') + '|' + String(snapshot.total || '');
		var prevSignature = String(state.frontendStore.meta.lastSummarySignature || '');
		state.frontendStore.meta.lastSummarySignature = signature;
		if (!prevSignature || prevSignature === signature) {
			return;
		}
		$summary.find('[data-summary-amount]').addClass('is-updated');
		window.setTimeout(function () {
			$summary.find('[data-summary-amount]').removeClass('is-updated');
		}, 320);
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
