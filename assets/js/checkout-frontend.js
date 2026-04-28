/**
 * MP Custom Checkout — базовая навигация шагов.
 */
(function ($) {
	'use strict';

	var selectors = {
		root: '#mp-cc-checkout',
		app: '#mp-cc-checkout-app',
		parcel: '#mp-cc-parcel-header',
		progress: '#mp-cc-progress-container',
		actions: '#mp-cc-navigation-actions',
		summary: '#mp-cc-summary-sidebar',
		notifications: '#mp-cc-notifications',
		exit: '#mp-cc-exit-checkout',
		shellParcelBadge: '#mp-cc-shell-parcel-badge',
		a11yAnnouncer: '#mp-cc-a11y-announcer',
		stepContent: '#mp-cc-step-content-container'
	};
	var viewportKeyboardBound = false;
	var flagNames = {
		checkoutUiV2: 'checkout_ui_v2',
		multiStepFlow: 'multi_step_flow',
		multiPickupPoints: 'multi_pickup_points',
		conditionsStep: 'conditions_step',
		discountPlacement: 'discount_block_placement',
		checkoutTestingMode: 'checkout_testing_mode',
		adminLivePreview: 'admin_live_preview'
	};
	var animationDurationMs = 180;
	var draftSaveTimer = null;
	var isClientErrorLoggingBound = false;
	var stepTransitionTimer = 0;
	var qtyInputDebounceTimers = {};
	var criticalRequestLocks = {
		stepTransition: false,
		paymentSubmit: false
	};
	var pickupMapScriptPromise = null;
	var pickupMapLogCache = {};
	var motionThrottleLast = {};
	/** Монотонный счётчик syncStoreWithBackend: отбрасываем устаревший session_get_state при гонках. */
	var syncStoreGeneration = 0;
	/** Защита от параллельных кликов по способу/тарифу доставки на шаге 1. */
	var shippingMutationInFlight = false;
	/** Отложенный клик по тарифу, если пользователь нажал во время shippingMutationInFlight. */
	var pendingShippingTariffChoice = null;

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

	function formatCheckoutStepMeta(currentOneBased, totalSteps) {
		var cur = Math.max(1, Math.round(Number(currentOneBased) || 0));
		var tot = Math.max(1, Math.round(Number(totalSteps) || 0));
		var tmpl = getUiText('checkout.step_meta', 'Шаг {current} / {total}');
		return String(tmpl)
			.replace(/\{current\}/g, String(cur))
			.replace(/\{total\}/g, String(tot));
	}

	function formatParcelBadgeLabel(count) {
		var n = Math.max(0, Math.round(Number(count) || 0));
		if (n <= 0) {
			return '';
		}
		var fewTmpl = getUiText('step_1.parcel_count_few', '{n} посылки');
		var otherTmpl = getUiText('step_1.parcel_count_other', '{n} посылок');
		var oneTmpl = getUiText('step_1.parcel_count_one', '{n} посылка');
		var mod10 = n % 10;
		var mod100 = n % 100;
		if (n === 1) {
			return String(oneTmpl).replace(/\{n\}/g, String(n));
		}
		if (mod10 >= 2 && mod10 <= 4 && (mod100 < 12 || mod100 > 14)) {
			return String(fewTmpl).replace(/\{n\}/g, String(n));
		}
		return String(otherTmpl).replace(/\{n\}/g, String(n));
	}

	function buildShellParcelBadgeHtml(state) {
		var cart = state && state.frontendStore && state.frontendStore.cart ? state.frontendStore.cart : {};
		var items = Array.isArray(cart.items) ? cart.items : [];
		var summary = cart.summary && typeof cart.summary === 'object' ? cart.summary : {};
		var count = Number(summary.items_count || items.length || 0);
		var label = formatParcelBadgeLabel(count);
		if (!label) {
			return '';
		}
		return '<span class="mp-cc-shell-parcel-badge">' + escapeHtml(label) + '</span>';
	}

	function getSummaryStepProgress(state) {
		if (isV2CheckoutUiEnabled(state)) {
			ensureV2ScreenState(state);
			var v2len = state.v2Screens && state.v2Screens.length ? state.v2Screens.length : 1;
			return { cur: state.v2CurrentIndex + 1, total: v2len };
		}
		var ix = getStepIndex(state.visibleSteps, state.currentStepId);
		var tot = state.visibleSteps.length || 1;
		return { cur: Math.min(tot, Math.max(1, ix + 1)), total: tot };
	}

	function getStepOneConfig() {
		var source = (window.mpCcCheckout && window.mpCcCheckout.stepOneConfig && typeof window.mpCcCheckout.stepOneConfig === 'object')
			? window.mpCcCheckout.stepOneConfig
			: {};
		return $.extend(true, {
			labels: {
				title: '',
				summary_title: '',
				subtotal_label: '',
				items_label: '',
				continue_label: '',
				return_label: '',
				empty_title: '',
				address_form: {
					city_row: '',
					city_placeholder: '',
					city_empty_hint: '',
					change_button: '',
					method_row: '',
					tariff_intro: '',
					office_row: '',
					office_not_set: ''
				}
			},
			product_meta_visibility: {
				show_image: true,
				show_sku: true,
				show_variation: true,
				show_price: true,
				show_subtotal: true
			},
			quantity_controls: {
				enabled: true,
				allow_manual_input: true,
				show_increment: true,
				show_decrement: true
			},
			empty_state: {
				message: '',
				cta_label: '',
				cta_enabled: true
			},
			style_controls: {
				card_compact: false,
				card_emphasis: 'default',
				summary_emphasis: 'default'
			},
			layout_order: {
				secondary_order: ['price', 'sku', 'variation', 'quantity', 'subtotal', 'remove']
			},
			address_form_style_preset: 'concept_a',
			responsive: {
				desktop_mode: 'comfortable',
				tablet_mode: 'comfortable',
				mobile_mode: 'compact',
				hide_media_mobile: false
			},
			admin_preview: {
				enabled: true
			},
			address_form_styles: {
				card_bg: '#ffffff',
				card_border: '#e5e7eb',
				card_radius: '14px',
				row_divider: '#e5e7eb',
				label_color: '#111111',
				label_size: '1.05rem',
				value_color: '#1f2937',
				value_size: '1.03rem',
				placeholder_color: '#80868f',
				option_title_color: '#111111',
				option_title_size: '1.05rem',
				option_hint_color: '#6b7280',
				option_hint_size: '0.96rem',
				radio_border_color: '#8b919a',
				radio_checked_color: '#111111',
				edit_btn_bg: '#f3f4f6',
				edit_btn_border: '#d9dce1',
				edit_btn_color: '#1f2937',
				edit_btn_radius: '6px'
			}
		}, source);
	}

	function getAddressFormPresetStyles(preset) {
		var key = trimNonEmpty(preset).toLowerCase();
		if (key === 'clean') {
			return {
				card_bg: '#ffffff',
				card_border: '#dfe3e8',
				card_radius: '12px',
				row_divider: '#eceff3',
				label_color: '#0f172a',
				label_size: '1.02rem',
				value_color: '#1f2937',
				value_size: '1rem',
				placeholder_color: '#9aa1aa',
				option_title_color: '#111827',
				option_title_size: '1.02rem',
				option_hint_color: '#6b7280',
				option_hint_size: '0.92rem',
				radio_border_color: '#9aa1aa',
				radio_checked_color: '#111827',
				edit_btn_bg: '#f8fafc',
				edit_btn_border: '#d9dde3',
				edit_btn_color: '#1f2937',
				edit_btn_radius: '6px'
			};
		}
		if (key === 'compact') {
			return {
				card_bg: '#ffffff',
				card_border: '#e5e7eb',
				card_radius: '10px',
				row_divider: '#eef0f3',
				label_color: '#111111',
				label_size: '0.98rem',
				value_color: '#1f2937',
				value_size: '0.96rem',
				placeholder_color: '#8b9098',
				option_title_color: '#111111',
				option_title_size: '0.98rem',
				option_hint_color: '#6b7280',
				option_hint_size: '0.88rem',
				radio_border_color: '#8f959d',
				radio_checked_color: '#111111',
				edit_btn_bg: '#f3f4f6',
				edit_btn_border: '#d9dce1',
				edit_btn_color: '#1f2937',
				edit_btn_radius: '5px'
			};
		}
		// concept_a (default)
		return {
			card_bg: '#ffffff',
			card_border: '#e5e7eb',
			card_radius: '14px',
			row_divider: '#e5e7eb',
			label_color: '#111111',
			label_size: '1.05rem',
			value_color: '#1f2937',
			value_size: '1.03rem',
			placeholder_color: '#80868f',
			option_title_color: '#111111',
			option_title_size: '1.05rem',
			option_hint_color: '#6b7280',
			option_hint_size: '0.96rem',
			radio_border_color: '#8b919a',
			radio_checked_color: '#111111',
			edit_btn_bg: '#f3f4f6',
			edit_btn_border: '#d9dce1',
			edit_btn_color: '#1f2937',
			edit_btn_radius: '6px'
		};
	}

	function buildAddressFormStyleAttr(state) {
		var config = state && state.stepOneConfig && typeof state.stepOneConfig === 'object' ? state.stepOneConfig : {};
		var presetKey = trimNonEmpty(config.address_form_style_preset) || 'concept_a';
		var presetStyles = getAddressFormPresetStyles(presetKey);
		var conceptABase = getAddressFormPresetStyles('concept_a');
		var rawOverrides = config.address_form_styles && typeof config.address_form_styles === 'object'
			? config.address_form_styles
			: {};
		var overrides = {};
		Object.keys(rawOverrides).forEach(function (key) {
			var val = trimNonEmpty(rawOverrides[key]);
			if (!val) {
				return;
			}
			// Для non-concept пресетов игнорируем дефолтные concept-a значения из tree,
			// чтобы пресет реально переключался без ручной очистки всех полей.
			if (presetKey !== 'concept_a' && Object.prototype.hasOwnProperty.call(conceptABase, key) && String(conceptABase[key]) === String(val)) {
				return;
			}
			overrides[key] = val;
		});
		var styles = $.extend({}, presetStyles, overrides);
		var vars = {
			'--mp-cc-address-card-bg': styles.card_bg,
			'--mp-cc-address-card-border': styles.card_border,
			'--mp-cc-address-card-radius': styles.card_radius,
			'--mp-cc-address-row-divider': styles.row_divider,
			'--mp-cc-address-label-color': styles.label_color,
			'--mp-cc-address-label-size': styles.label_size,
			'--mp-cc-address-value-color': styles.value_color,
			'--mp-cc-address-value-size': styles.value_size,
			'--mp-cc-address-placeholder-color': styles.placeholder_color,
			'--mp-cc-address-option-title-color': styles.option_title_color,
			'--mp-cc-address-option-title-size': styles.option_title_size,
			'--mp-cc-address-option-hint-color': styles.option_hint_color,
			'--mp-cc-address-option-hint-size': styles.option_hint_size,
			'--mp-cc-address-radio-border-color': styles.radio_border_color,
			'--mp-cc-address-radio-checked-color': styles.radio_checked_color,
			'--mp-cc-address-edit-btn-bg': styles.edit_btn_bg,
			'--mp-cc-address-edit-btn-border': styles.edit_btn_border,
			'--mp-cc-address-edit-btn-color': styles.edit_btn_color,
			'--mp-cc-address-edit-btn-radius': styles.edit_btn_radius
		};
		var out = [];
		Object.keys(vars).forEach(function (key) {
			var value = trimNonEmpty(vars[key]);
			if (!value) {
				return;
			}
			out.push(key + ': ' + value);
		});
		return out.length ? ' style="' + escapeHtml(out.join('; ')) + '"' : '';
	}

	function resolveStepOneLabelsPath(labels, keyPath) {
		if (!labels || !keyPath) {
			return '';
		}
		var parts = String(keyPath).split('.');
		var node = labels;
		var i;
		for (i = 0; i < parts.length; i += 1) {
			if (!node || typeof node !== 'object' || !Object.prototype.hasOwnProperty.call(node, parts[i])) {
				return '';
			}
			node = node[parts[i]];
		}
		if (typeof node === 'string' || typeof node === 'number') {
			return String(node);
		}
		return '';
	}

	function getStepOneLabel(state, keyPath, fallbackPath, fallbackText) {
		var configLabels = state && state.stepOneConfig && state.stepOneConfig.labels ? state.stepOneConfig.labels : {};
		var value = resolveStepOneLabelsPath(configLabels, keyPath);
		if (value !== '') {
			return value;
		}
		return getUiText(fallbackPath, fallbackText);
	}

	function getScenarioMap() {
		var map = (window.mpCcCheckout && window.mpCcCheckout.scenarioStepMap && typeof window.mpCcCheckout.scenarioStepMap === 'object')
			? window.mpCcCheckout.scenarioStepMap
			: {};
		return {
			scenarios: (map.scenarios && typeof map.scenarios === 'object') ? map.scenarios : {},
			rules: (map.scenarioRules && typeof map.scenarioRules === 'object') ? map.scenarioRules : {}
		};
	}

	function getPickupConfig() {
		var source = (window.mpCcCheckout && window.mpCcCheckout.pickupConfig && typeof window.mpCcCheckout.pickupConfig === 'object')
			? window.mpCcCheckout.pickupConfig
			: {};
		return {
			enablePointSelection: Boolean(source.enable_point_selection),
			mapSlotEnabled: source.map_slot_enabled !== false,
			mapWidget: $.extend(true, {
				enabled: true,
				provider: 'yandex',
				api_key: '',
				center_lat: 56.010563,
				center_lng: 92.852572,
				zoom: 14,
				marker_label: 'Пункт самовывоза',
				marker_hint: 'Заберите заказ в рабочие часы.',
				fallback_title: 'Карта временно недоступна',
				fallback_message: 'Посмотрите адрес пункта самовывоза выше и постройте маршрут в приложении карт.',
				desktop_height: 250,
				mobile_height: 190,
				diagnostics_enabled: true
			}, source.map_widget && typeof source.map_widget === 'object' ? source.map_widget : {}),
			points: Array.isArray(source.points) ? source.points : []
		};
	}

	function getScenarioUiConfig() {
		var source = (window.mpCcCheckout && window.mpCcCheckout.scenarioUiConfig && typeof window.mpCcCheckout.scenarioUiConfig === 'object')
			? window.mpCcCheckout.scenarioUiConfig
			: {};
		return $.extend(true, {
			default_scenario: 'pickup',
			card_order: ['pickup', 'delivery'],
			cards: {
				pickup: {
					title: 'Самовывоз',
					description: '',
					helper: '',
					icon_variant: 'pickup',
					icon_style: 'soft'
				},
				delivery: {
					title: 'Доставка',
					description: '',
					helper: '',
					icon_variant: 'delivery',
					icon_style: 'soft'
				}
			},
			responsive: {
				desktop_columns: 2,
				tablet_columns: 1,
				mobile_columns: 1,
				card_density: 'comfortable'
			}
		}, source);
	}

	function getDeliveryConfig() {
		var source = (window.mpCcCheckout && window.mpCcCheckout.deliveryConfig && typeof window.mpCcCheckout.deliveryConfig === 'object')
			? window.mpCcCheckout.deliveryConfig
			: {};
		return $.extend(true, {
			shipping_catalog: {
				sort_order: [],
				methods: {},
				error_copy: {
					method_unavailable: 'Выбранный метод доставки недоступен. Выберите другой вариант.',
					tariff_unavailable: 'Выбранный тариф недоступен. Выберите другой тариф.'
				},
				bulk_update: {
					enabled: true,
					seasonal_delta_pct: 0,
					seasonal_delta_abs: 0,
					eta_suffix: ''
				},
				preview: {
					enabled: true,
					mock_subtotal: 3670,
					mock_discount: 200,
					mock_tax: 160
				}
			}
		}, source);
	}

	function getStepThreeConfig() {
		var source = (window.mpCcCheckout && window.mpCcCheckout.stepThreeConfig && typeof window.mpCcCheckout.stepThreeConfig === 'object')
			? window.mpCcCheckout.stepThreeConfig
			: {};
		return $.extend(true, {
			copy: {
				title: 'Выберите дату получения',
				helper_by_scenario: {
					pickup: '',
					krasnoyarsk_delivery: '',
					other_city_delivery: ''
				},
				errors: {
					invalid_date: 'Выбранная дата недоступна. Обновите шаг и выберите другую дату.',
					empty_date: 'Выберите дату, чтобы продолжить.'
				},
				admin_preview: {
					enabled: true
				}
			},
			conditions_copy: {
				intro_by_scenario: {
					pickup: '',
					krasnoyarsk_delivery: '',
					other_city_delivery: ''
				},
				secondary_notes: ['', '', ''],
				krasnoyarsk_delivery: {
					title: '',
					body: '',
					delivery_within_day: ''
				},
				other_city_delivery: {
					title: '',
					body: '',
					logistics_note: ''
				},
				pickup: {
					title: '',
					body: '',
					office_block_title: '',
					office_address: '',
					office_description: '',
					office_hours_plain: '',
					office_hours: [],
					convenience_helper: '',
					critical_notice: '',
					show_multi_office_slot: true
				}
			}
		}, source);
	}

	function getStepFourConfig() {
		var source = (window.mpCcCheckout && window.mpCcCheckout.stepFourConfig && typeof window.mpCcCheckout.stepFourConfig === 'object')
			? window.mpCcCheckout.stepFourConfig
			: {};
		return $.extend(true, {
			contact_block: {
				title: '',
				intro: '',
				patronymic_required: false,
				field_order: ['last_name', 'first_name', 'patronymic', 'gender', 'birthdate', 'email', 'phone', 'order_notes'],
				field_visibility: {
					last_name: true,
					first_name: true,
					patronymic: true,
					gender: true,
					birthdate: true,
					email: true,
					phone: true,
					order_notes: true
				},
				field_required: {
					last_name: true,
					first_name: true,
					patronymic: false,
					gender: false,
					birthdate: true,
					email: true,
					phone: true,
					order_notes: false
				},
				placeholders: {
					last_name: '',
					first_name: '',
					patronymic: '',
					gender: '',
					birthdate: '',
					email: '',
					phone: '',
					order_notes: ''
				},
				labels: {
					last_name: '',
					first_name: '',
					patronymic: '',
					gender: '',
					birthdate: '',
					email: '',
					phone: '',
					order_notes: '',
					country_code: ''
				},
				hints: {
					email: '',
					phone: '',
					patronymic: '',
					gender: '',
					birthdate: '',
					order_notes: ''
				},
				gender_options: {
					placeholder: '',
					male: '',
					female: ''
				},
				validation_messages: {
					required: '',
					email_invalid: '',
					phone_required: '',
					phone_format: '',
					birthdate_required: '',
					birthdate_invalid: '',
					birthdate_range: '',
					order_notes_length: '',
					address_required: '',
					address_region: '',
					address_city: '',
					address_postcode: '',
					step_blocked: '',
					conditions_required: ''
				},
				validation_constraints: {
					birthdate_min_age: 0,
					birthdate_max_age: 120,
					phone_digits_override: 0
				},
				ajax_messages: {
					draft_save_failed: '',
					step_sync_failed: '',
					scenario_sync_failed: ''
				},
				order_notes_max_length: 500,
				order_notes_counter: { enabled: true },
				phone_country_codes: [
					{ dial: '+7', iso: 'RU', national_digits: 10, label: 'RU' },
					{ dial: '+7', iso: 'KZ', national_digits: 10, label: 'KZ' },
					{ dial: '+375', iso: 'BY', national_digits: 9, label: 'BY' },
					{ dial: '+994', iso: 'AZ', national_digits: 9, label: 'AZ' },
					{ dial: '+374', iso: 'AM', national_digits: 8, label: 'AM' },
					{ dial: '+995', iso: 'GE', national_digits: 9, label: 'GE' },
					{ dial: '+996', iso: 'KG', national_digits: 9, label: 'KG' },
					{ dial: '+992', iso: 'TJ', national_digits: 9, label: 'TJ' },
					{ dial: '+998', iso: 'UZ', national_digits: 9, label: 'UZ' }
				],
				default_phone_country_iso: 'RU',
				layout: {
					desktop_columns: 3,
					tablet_columns: 2,
					mobile_columns: 1,
					grid_gap: '0.75rem 1rem'
				},
				field_state_styles: {
					invalid_style: 'default',
					hint_style: 'default',
					focus_style: 'default',
					disabled_style: 'default'
				}
			},
			payment_block: {
				title: '',
				intro: '',
				gateway_order: [],
				card_surface: 'visual',
				decorative_card_fields: true,
				auto_classic_on_empty_gateway_fields: true,
				layout: { desktop_columns: 2, tablet_columns: 2, mobile_columns: 1, grid_gap: '0.6rem 0.75rem' },
				card_style: 'default',
				card_active_style: 'accent',
				radio_style: 'default',
				description_style: 'muted',
				show_description: true,
				required: true,
				bank_card_visual: {
					enabled: true,
					confirm_on_click_only: true,
					allow_deselect: true,
					card_max_width: '100%',
					glow_color: '#a78bfa',
					glow_intensity: 'medium',
					show_check_pill: true
				},
				error_message: '',
				messages: { loading: '', success: '', error: '' },
				summary_mini_review: {
					enabled: true,
					title: '',
					intro: '',
					method_label: '',
					id_label: '',
					state_loading: '',
					state_success: '',
					state_error: '',
					show_gateway_id: false,
					show_gateway_description: true
				},
				diagnostics: { enabled: true }
			},
			available_gateways: [],
			address_block: {
				title: '',
				intro: '',
				default_country: 'RU',
				subfields_order: ['country', 'state', 'city', 'address_1', 'address_2', 'postcode'],
				subfields_visible: {
					country: true,
					state: true,
					city: true,
					address_1: true,
					address_2: true,
					postcode: true
				},
				postcode_max_length: 16,
				labels: {
					country: '',
					state: '',
					city: '',
					address_1: '',
					address_2: '',
					postcode: ''
				}
			},
			address_geo: {},
			discount_layout: {
				placement: 'step_4',
				separate_step_enabled: false,
				order: ['coupon']
			},
			discount_block_styles: {
				state_empty: 'default',
				state_success: 'success',
				state_error: 'error',
				focus_style: 'default'
			},
			coupon_block: {
				title: '',
				intro: '',
				input_label: '',
				placeholder: '',
				apply_label: '',
				empty_message: '',
				success_message: '',
				error_message: '',
				allow_remove_applied: true,
				summary_section_title: ''
			},
			gift_card_block: {
				title: '',
				intro: '',
				input_label: '',
				placeholder: '',
				apply_label: '',
				empty_message: '',
				success_message: '',
				error_message: '',
				peer_next_to_payment: true,
				allow_remove_applied: true,
				card_title: '',
				card_subtitle: '',
				unavailable_message: ''
			},
			geo_preview: { enabled: false }
		}, source);
	}

	function isContactFieldVisible(key) {
		var cfg = getStepFourConfig();
		var vis = cfg.contact_block && cfg.contact_block.field_visibility ? cfg.contact_block.field_visibility : {};
		return vis[key] !== false;
	}

	function isContactFieldRequired(key) {
		var cfg = getStepFourConfig();
		var req = cfg.contact_block && cfg.contact_block.field_required ? cfg.contact_block.field_required : {};
		if (Object.prototype.hasOwnProperty.call(req, key)) {
			return Boolean(req[key]);
		}
		return key !== 'patronymic';
	}

	function getContactPlaceholder(key) {
		var cfg = getStepFourConfig();
		var placeholders = cfg.contact_block && cfg.contact_block.placeholders ? cfg.contact_block.placeholders : {};
		return trimNonEmpty(placeholders[key] ? String(placeholders[key]) : '');
	}

	function getContactLabel(key) {
		var cfg = getStepFourConfig();
		var labels = cfg.contact_block && cfg.contact_block.labels ? cfg.contact_block.labels : {};
		var fromCfg = labels[key] ? String(labels[key]) : '';
		if (trimNonEmpty(fromCfg)) {
			return fromCfg;
		}
		var fb = {
			last_name: 'Фамилия',
			first_name: 'Имя',
			patronymic: 'Отчество',
			gender: 'Пол',
			birthdate: 'Дата рождения',
			order_notes: 'Примечания к заказу',
			email: 'Email',
			phone: 'Телефон',
			country_code: 'Код страны'
		};
		var path = {
			last_name: 'step_4.contact_last_name',
			first_name: 'step_4.contact_first_name',
			patronymic: 'step_4.contact_patronymic',
			gender: 'step_4.contact_gender',
			birthdate: 'step_4.contact_birthdate',
			order_notes: 'step_4.contact_order_notes',
			email: 'step_4.contact_email',
			phone: 'step_4.contact_phone',
			country_code: 'step_4.contact_country_code'
		};
		return getUiText(path[key] || 'step_4.title', fb[key] || key);
	}

	function getContactHint(key) {
		var cfg = getStepFourConfig();
		var hints = cfg.contact_block && cfg.contact_block.hints ? cfg.contact_block.hints : {};
		var fromCfg = hints[key] ? String(hints[key]) : '';
		if (trimNonEmpty(fromCfg)) {
			return fromCfg;
		}
		var fb = {
			email: 'На этот адрес отправим подтверждение заказа.',
			phone: 'Введите номер без кода страны — он выбран слева.',
			patronymic: 'Укажите при наличии.',
			gender: 'Необязательное поле.',
			birthdate: 'Используем для корректной обработки заказа и персонализации сервиса.',
			order_notes: 'Оставьте детали по доставке, упаковке или пожелания к заказу.'
		};
		var path = {
			email: 'step_4.contact_hint_email',
			phone: 'step_4.contact_hint_phone',
			patronymic: 'step_4.contact_hint_patronymic',
			gender: 'step_4.contact_hint_gender',
			birthdate: 'step_4.contact_hint_birthdate',
			order_notes: 'step_4.contact_hint_order_notes'
		};
		return getUiText(path[key] || 'step_4.title', fb[key] || '');
	}

	function getGenderOptions() {
		var cfg = getStepFourConfig();
		var go = cfg.contact_block && cfg.contact_block.gender_options ? cfg.contact_block.gender_options : {};
		return {
			placeholder: trimNonEmpty(go.placeholder) || getUiText('step_4.contact_gender_placeholder', 'Не указывать'),
			male: trimNonEmpty(go.male) || getUiText('step_4.contact_gender_male', 'Мужчина'),
			female: trimNonEmpty(go.female) || getUiText('step_4.contact_gender_female', 'Женщина')
		};
	}

	function getBirthdateErrorText(code) {
		var cfg = getStepFourConfig();
		var vm = cfg.contact_block && cfg.contact_block.validation_messages ? cfg.contact_block.validation_messages : {};
		if (code === 'required') {
			return trimNonEmpty(vm.birthdate_required) || getUiText('step_4.contact_error_birthdate_required', 'Укажите дату рождения.');
		}
		if (code === 'invalid') {
			return trimNonEmpty(vm.birthdate_invalid) || getUiText('step_4.contact_error_birthdate_invalid', 'Введите корректную дату рождения.');
		}
		return trimNonEmpty(vm.birthdate_range) || getUiText('step_4.contact_error_birthdate_range', 'Допустимый возраст: от 0 до 120 лет.');
	}

	function getStepFourValidationMessages() {
		var cfg = getStepFourConfig();
		return cfg.contact_block && cfg.contact_block.validation_messages && typeof cfg.contact_block.validation_messages === 'object'
			? cfg.contact_block.validation_messages
			: {};
	}

	function getStepFourAjaxMessage(code, fallbackKey, fallbackText) {
		var cfg = getStepFourConfig();
		var ajax = cfg.contact_block && cfg.contact_block.ajax_messages && typeof cfg.contact_block.ajax_messages === 'object'
			? cfg.contact_block.ajax_messages
			: {};
		return trimNonEmpty(ajax[code]) || getUiText(fallbackKey, fallbackText);
	}

	function getFieldConstraintConfig() {
		var cfg = getStepFourConfig();
		var raw = cfg.contact_block && cfg.contact_block.validation_constraints && typeof cfg.contact_block.validation_constraints === 'object'
			? cfg.contact_block.validation_constraints
			: {};
		var minAge = Number(raw.birthdate_min_age);
		var maxAge = Number(raw.birthdate_max_age);
		var phoneOverride = Number(raw.phone_digits_override);
		if (!Number.isFinite(minAge) || minAge < 0) {
			minAge = 0;
		}
		if (!Number.isFinite(maxAge) || maxAge < minAge) {
			maxAge = 120;
		}
		if (!Number.isFinite(phoneOverride) || phoneOverride < 0) {
			phoneOverride = 0;
		}
		return { minAge: minAge, maxAge: maxAge, phoneDigitsOverride: phoneOverride };
	}

	function getOrderNotesSettings() {
		var cfg = getStepFourConfig();
		var block = cfg.contact_block || {};
		var maxLen = Number(block.order_notes_max_length || 500);
		if (!Number.isFinite(maxLen) || maxLen <= 0) {
			maxLen = 500;
		}
		var vm = block.validation_messages && typeof block.validation_messages === 'object' ? block.validation_messages : {};
		var counter = block.order_notes_counter && typeof block.order_notes_counter === 'object' ? block.order_notes_counter : {};
		return {
			maxLength: maxLen,
			showCounter: counter.enabled !== false,
			lengthErrorText: trimNonEmpty(vm.order_notes_length) || getUiText('step_4.contact_error_order_notes_length', 'Превышена максимальная длина примечания.')
		};
	}

	function getAddressGeoMerged() {
		var cfg = getStepFourConfig();
		return cfg.address_geo && typeof cfg.address_geo === 'object' ? cfg.address_geo : {};
	}

	function isAddressSubfieldVisible(key) {
		var cfg = getStepFourConfig();
		var ab = cfg.address_block || {};
		var vm = getStepFourValidationMessages();
		var vis = ab.subfields_visible && typeof ab.subfields_visible === 'object' ? ab.subfields_visible : {};
		return vis[key] !== false;
	}

	function shouldRenderAddressSubfield(key, contact) {
		if (!isAddressSubfieldVisible(key)) {
			return false;
		}
		var vis = contact && contact.__address_visibility ? contact.__address_visibility : {};
		if (vis.hide_address_fields) {
			return false;
		}
		if (key === 'country' && vis.hide_country) {
			return false;
		}
		if (key === 'state' && vis.hide_region) {
			return false;
		}
		if (key === 'city' && vis.hide_city) {
			return false;
		}
		if ((key === 'address_1' || key === 'address_2') && vis.hide_address_lines) {
			return false;
		}
		if (key === 'postcode' && vis.hide_postcode) {
			return false;
		}
		return true;
	}

	function getAddressLabel(key) {
		var cfg = getStepFourConfig();
		var labels = cfg.address_block && cfg.address_block.labels ? cfg.address_block.labels : {};
		var fromCfg = labels[key] ? String(labels[key]) : '';
		if (trimNonEmpty(fromCfg)) {
			return fromCfg;
		}
		var path = {
			country: 'step_4.address_country',
			state: 'step_4.address_region',
			city: 'step_4.address_city',
			address_1: 'step_4.address_line1',
			address_2: 'step_4.address_line2',
			postcode: 'step_4.address_postcode'
		};
		var fb = {
			country: 'Страна',
			state: 'Регион',
			city: 'Населённый пункт',
			address_1: 'Улица, дом',
			address_2: 'Квартира, офис',
			postcode: 'Почтовый индекс'
		};
		return getUiText(path[key] || 'step_4.address_block_title', fb[key] || key);
	}

	function getRegionsForCountry(geo, countryIso) {
		var c = geo[countryIso];
		if (!c || typeof c !== 'object') {
			return [];
		}
		var regs = c.regions && typeof c.regions === 'object' ? c.regions : {};
		var out = [];
		var rid;
		for (rid in regs) {
			if (!Object.prototype.hasOwnProperty.call(regs, rid)) {
				continue;
			}
			var row = regs[rid];
			if (!row || typeof row !== 'object') {
				continue;
			}
			out.push({
				id: rid,
				label: String(row.label || rid),
				settlements: Array.isArray(row.settlements) ? row.settlements : []
			});
		}
		out.sort(function (a, b) {
			return a.label.localeCompare(b.label, 'ru');
		});
		return out;
	}

	function getSettlementsForRegion(geo, countryIso, regionId) {
		var c = geo[countryIso];
		if (!c || !c.regions || !c.regions[regionId]) {
			return [];
		}
		var row = c.regions[regionId];
		return Array.isArray(row.settlements) ? row.settlements.slice() : [];
	}

	function ensureAddressDefaults(state) {
		if (!state || !state.frontendStore || !state.frontendStore.form) {
			return;
		}
		var contact = state.frontendStore.form.contact || {};
		var vis = contact.__address_visibility;
		if (vis && vis.hide_address_fields) {
			return;
		}
		var cfg = getStepFourConfig();
		var ab = cfg.address_block || {};
		var defCountry = trimNonEmpty(ab.default_country) ? String(ab.default_country) : 'RU';
		var needGeoKey = shouldRenderAddressSubfield('country', contact)
			|| shouldRenderAddressSubfield('state', contact)
			|| shouldRenderAddressSubfield('city', contact);
		if (needGeoKey && !trimNonEmpty(contact.country)) {
			contact.country = defCountry;
		}
		state.frontendStore.form.contact = contact;
	}

	function findPhoneCountryMeta(codes, iso) {
		var list = Array.isArray(codes) ? codes : [];
		var want = String(iso || '').toUpperCase();
		var i;
		for (i = 0; i < list.length; i++) {
			var row = list[i];
			if (row && String(row.iso || '').toUpperCase() === want) {
				return {
					dial: String(row.dial || '+7'),
					national_digits: Number(row.national_digits || 10),
					iso: String(row.iso || ''),
					label: trimNonEmpty(row.label) ? String(row.label) : String(row.iso || '')
				};
			}
		}
		if (list.length && list[0]) {
			var z = list[0];
			return {
				dial: String(z.dial || '+7'),
				national_digits: Number(z.national_digits || 10),
				iso: String(z.iso || 'RU'),
				label: trimNonEmpty(z.label) ? String(z.label) : String(z.iso || 'RU')
			};
		}
		return { dial: '+7', national_digits: 10, iso: 'RU', label: 'RU' };
	}

	function isoToFlagEmoji(iso) {
		var u = String(iso || '').toUpperCase();
		if (u.length !== 2) {
			return '';
		}
		var a = u.charCodeAt(0);
		var b = u.charCodeAt(1);
		if (a < 65 || a > 90 || b < 65 || b > 90) {
			return '';
		}
		return String.fromCodePoint(0x1F1E6 + (a - 65), 0x1F1E6 + (b - 65));
	}

	function ruDigitsWord(n) {
		var x = Math.abs(Math.floor(Number(n))) % 100;
		var x1 = x % 10;
		if (x > 10 && x < 20) {
			return 'цифр';
		}
		if (x1 > 1 && x1 < 5) {
			return 'цифры';
		}
		if (x1 === 1) {
			return 'цифра';
		}
		return 'цифр';
	}

	function formatNationalPhoneDisplay(dial, digits, iso) {
		var d = String(digits || '').replace(/\D/g, '');
		var dialStr = String(dial || '');
		var isoU = String(iso || '').toUpperCase();
		if (dialStr === '+375' || isoU === 'BY') {
			d = d.slice(0, 9);
			var p1 = d.slice(0, 2);
			var p2 = d.slice(2, 5);
			var p3 = d.slice(5, 7);
			var p4 = d.slice(7, 9);
			var outBy = '';
			if (p1) {
				outBy += '(' + p1 + ')';
			}
			if (p2) {
				outBy += (outBy ? ' ' : '') + p2;
			}
			if (p3) {
				outBy += '-' + p3;
			}
			if (p4) {
				outBy += '-' + p4;
			}
			return outBy;
		}
		if (dialStr === '+374' || isoU === 'AM') {
			d = d.slice(0, 8);
			var a8 = d.slice(0, 2);
			var b8 = d.slice(2, 4);
			var c8 = d.slice(4, 6);
			var e8 = d.slice(6, 8);
			if (!a8) {
				return '';
			}
			var s8 = '(' + a8 + ')';
			if (b8) {
				s8 += ' ' + b8;
			}
			if (c8) {
				s8 += '-' + c8;
			}
			if (e8) {
				s8 += '-' + e8;
			}
			return s8;
		}
		if (dialStr === '+994' || dialStr === '+995' || dialStr === '+996' || dialStr === '+992' || dialStr === '+998') {
			d = d.slice(0, 9);
			var op2 = d.slice(0, 2);
			var mid3 = d.slice(2, 5);
			var x2 = d.slice(5, 7);
			var y2 = d.slice(7, 9);
			if (!op2) {
				return '';
			}
			var s9 = '(' + op2 + ')';
			if (mid3) {
				s9 += ' ' + mid3;
			}
			if (x2) {
				s9 += '-' + x2;
			}
			if (y2) {
				s9 += '-' + y2;
			}
			return s9;
		}
		d = d.slice(0, 10);
		var a = d.slice(0, 3);
		var b = d.slice(3, 6);
		var c = d.slice(6, 8);
		var e = d.slice(8, 10);
		if (!a) {
			return '';
		}
		var s = '(' + a + ')';
		if (b) {
			s += ' ' + b;
		}
		if (c) {
			s += '-' + c;
		}
		if (e) {
			s += '-' + e;
		}
		return s;
	}

	function buildFullPhoneE164(contact) {
		var dial = String(contact.phone_dial_code || '+7');
		var nat = String(contact.billing_phone_national || '').replace(/\D/g, '');
		return dial + nat;
	}

	function isValidEmailValue(value) {
		var s = String(value || '').trim();
		if (!s) {
			return false;
		}
		return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(s);
	}

	function ensureContactDefaults(state) {
		if (!state || !state.frontendStore || !state.frontendStore.form) {
			return;
		}
		var c = state.frontendStore.form.contact || {};
		if (!c.__address_visibility) {
			applyScenarioFieldAvailability(state);
		}
		c = state.frontendStore.form.contact || {};
		var cfg = getStepFourConfig();
		var block = cfg.contact_block || {};
		var codes = Array.isArray(block.phone_country_codes) ? block.phone_country_codes : [];
		var constraints = getFieldConstraintConfig();
		var defIso = trimNonEmpty(block.default_phone_country_iso) ? String(block.default_phone_country_iso) : 'RU';
		if (!trimNonEmpty(c.phone_country_iso)) {
			c.phone_country_iso = defIso;
		}
		var meta = findPhoneCountryMeta(codes, c.phone_country_iso);
		c.phone_dial_code = meta.dial;
		if (!trimNonEmpty(c.billing_phone_national) && trimNonEmpty(c.billing_phone)) {
			var raw = String(c.billing_phone).replace(/\D/g, '');
			var dialDigits = String(meta.dial || '').replace(/\D/g, '');
			if (dialDigits && raw.indexOf(dialDigits) === 0) {
				c.billing_phone_national = raw.slice(dialDigits.length);
			} else {
				c.billing_phone_national = raw;
			}
		}
		c.billing_phone = buildFullPhoneE164(c);
		if (state.frontendStore && state.frontendStore.payment && !trimNonEmpty(state.frontendStore.payment.gateway)) {
			c.payment_gateway = '';
			c.gateway = '';
		}
		state.frontendStore.form.contact = c;
		ensureAddressDefaults(state);
	}

	/**
	 * Подтягивает значения контактной формы из DOM в state.
	 * Нужен перед валидацией «Далее» (автозаполнение / последний символ без input)
	 * и после session_get_state: иначе гонка с отложенным session_set_answers
	 * затирает ввод при syncStoreWithBackend на каждом input по полям адреса.
	 */
	function flushContactFormFromDom(state, $app) {
		if (!state || !$app || !$app.length || !state.frontendStore || !state.frontendStore.form) {
			return;
		}
		var $fields = $app.find('[data-contact-field]');
		if (!$fields.length) {
			return;
		}
		var contact = $.extend({}, state.frontendStore.form.contact || {});
		$fields.each(function () {
			var $el = $(this);
			var key = String($el.data('contact-field') || '');
			if (!key) {
				return;
			}
			var val = $el.val();
			if (key === 'order_notes') {
				var settings = getOrderNotesSettings();
				val = String(val || '');
				if (val.length > settings.maxLength) {
					val = val.slice(0, settings.maxLength);
					$el.val(val);
				}
			}
			contact[key] = val;
		});
		var $nat = $app.find('[data-contact-phone-national]');
		if ($nat.length) {
			var cfg = getStepFourConfig();
			var codes = cfg.contact_block && cfg.contact_block.phone_country_codes ? cfg.contact_block.phone_country_codes : [];
			var meta = findPhoneCountryMeta(codes, contact.phone_country_iso);
			var maxLen = meta.national_digits || 10;
			var raw = String($nat.val() || '').replace(/\D/g, '').slice(0, maxLen);
			contact.billing_phone_national = raw;
		}
		var $phoneCountry = $app.find('[data-contact-phone-country]');
		if ($phoneCountry.length) {
			var iso = String($phoneCountry.val() || '').trim();
			if (iso) {
				contact.phone_country_iso = iso;
			}
		}
		contact.billing_phone = buildFullPhoneE164(contact);
		state.frontendStore.form.contact = contact;
	}

	function getAvailablePaymentGateways() {
		var cfg = getStepFourConfig();
		var list = Array.isArray(cfg.available_gateways) ? cfg.available_gateways : [];
		var pb = cfg.payment_block && typeof cfg.payment_block === 'object' ? cfg.payment_block : {};
		var order = Array.isArray(pb.gateway_order) ? pb.gateway_order : [];
		var normalized = [];
		for (var i = 0; i < list.length; i += 1) {
			var row = list[i] || {};
			var id = trimNonEmpty(row.id);
			if (!id) {
				continue;
			}
			normalized.push({
				id: id,
				title: trimNonEmpty(row.title) || id,
				description: trimNonEmpty(row.description) || ''
			});
		}
		if (!order.length) {
			return normalized;
		}
		var rank = {};
		for (i = 0; i < order.length; i += 1) {
			var id = trimNonEmpty(order[i]);
			if (!id || Object.prototype.hasOwnProperty.call(rank, id)) {
				continue;
			}
			rank[id] = i;
		}
		normalized.sort(function (a, b) {
			var ar = Object.prototype.hasOwnProperty.call(rank, a.id) ? rank[a.id] : 9999;
			var br = Object.prototype.hasOwnProperty.call(rank, b.id) ? rank[b.id] : 9999;
			if (ar !== br) {
				return ar - br;
			}
			return String(a.title || '').localeCompare(String(b.title || ''));
		});
		return normalized;
	}

	function getSelectedGatewayTitle(state) {
		var selected = trimNonEmpty(state && state.frontendStore && state.frontendStore.payment ? state.frontendStore.payment.gateway : '');
		if (!selected) {
			return '';
		}
		var gateways = getAvailablePaymentGateways();
		for (var i = 0; i < gateways.length; i += 1) {
			if (String(gateways[i].id) === selected) {
				return String(gateways[i].title || selected);
			}
		}
		return selected;
	}

	function getGatewayMetaById(gatewayId) {
		var gid = trimNonEmpty(gatewayId);
		if (!gid) {
			return null;
		}
		var gateways = getAvailablePaymentGateways();
		for (var i = 0; i < gateways.length; i += 1) {
			if (String(gateways[i].id) === gid) {
				return gateways[i];
			}
		}
		return { id: gid, title: gid, description: '' };
	}

	function stripTagsHtml(s) {
		return String(s || '').replace(/<[^>]*>/g, '').replace(/\s+/g, ' ').trim();
	}

	function truncateUiPlain(s, maxLen) {
		var t = String(s || '');
		var m = Math.max(8, Number(maxLen) || 160);
		if (t.length <= m) {
			return t;
		}
		return t.slice(0, m - 1) + '…';
	}

	function isCouponRemoveAllowed() {
		var cfg = getStepFourConfig();
		var c = cfg.coupon_block || {};
		return c.allow_remove_applied !== false;
	}

	function buildContactPaymentSummaryBreakdownHtml(state) {
		var cartSummary = state.frontendStore && state.frontendStore.cart ? state.frontendStore.cart.summary || {} : {};
		var couponLines = Array.isArray(cartSummary.coupon_lines) ? cartSummary.coupon_lines : [];
		var giftCardCodes = Array.isArray(cartSummary.applied_gift_cards) ? cartSummary.applied_gift_cards : [];
		var giftCardLines = Array.isArray(cartSummary.gift_card_lines) ? cartSummary.gift_card_lines : [];
		var giftCardTotal = String(cartSummary.gift_card_total || '');
		if (!couponLines.length && !giftCardCodes.length && !giftCardLines.length) {
			return '';
		}
		var cfg = getStepFourConfig();
		var cb = cfg.coupon_block || {};
		var sectionTitle = trimNonEmpty(cb.summary_section_title) || getUiText('order_review.promo_breakdown_title', 'Промокоды и скидки');
		var giftCardLabel = getStepOneLabel(state, 'gift_card_label', 'step_4.gift_card_title', 'Подарочная карта');
		var couponWord = getUiText('order_review.coupon_line_prefix', 'Промокод');
		var allowRm = isCouponRemoveAllowed();
		var allowGiftRm = isGiftCardRemoveAllowed();
		var html = '';
		html += '<div class="mp-cc-summary-card__scenario mp-cc-summary-card__scenario--breakdown" data-final-review-breakdown="1">';
		html += '<p class="mp-cc-summary-card__scenario-title"><strong>' + escapeHtml(sectionTitle) + '</strong></p>';
		html += '<div class="mp-cc-summary-card__breakdown-list">';
		for (var di = 0; di < couponLines.length; di += 1) {
			var line = couponLines[di] || {};
			var ccode = String(line.code || '');
			html += '<div class="mp-cc-summary-card__breakdown-row">';
			html += '<span class="mp-cc-summary-card__breakdown-meta">' + escapeHtml(couponWord + (ccode ? ' ' + ccode : '')) + ': ' + wcPriceHtmlFragment(String(line.amount || '')) + '</span>';
			if (allowRm && ccode) {
				html += '<button type="button" class="mp-cc-summary-card__breakdown-remove" data-coupon-remove="1" data-code="' + escapeHtml(ccode) + '" aria-label="' + escapeHtml(getUiText('step_4.coupon_remove', 'Снять купон')) + '">×</button>';
			}
			html += '</div>';
		}
		for (var gli = 0; gli < giftCardLines.length; gli += 1) {
			var gl = giftCardLines[gli] || {};
			html += '<div class="mp-cc-summary-card__breakdown-row">';
			html += '<span class="mp-cc-summary-card__breakdown-meta">' + escapeHtml(String(gl.label || giftCardLabel)) + ': ' + wcPriceHtmlFragment(String(gl.amount || '')) + '</span>';
			html += '</div>';
		}
		if (!giftCardLines.length && (giftCardCodes.length || trimNonEmpty(giftCardTotal))) {
			var prefix = giftCardLabel + (giftCardCodes.length ? ' ' + giftCardCodes.join(', ') : '');
			html += '<div class="mp-cc-summary-card__breakdown-row">';
			html += '<span class="mp-cc-summary-card__breakdown-meta">' + escapeHtml(prefix) + ': ' + wcPriceHtmlFragment(giftCardTotal || '—') + '</span>';
			if (allowGiftRm && giftCardCodes.length === 1) {
				var gc0 = String(giftCardCodes[0] || '');
				if (gc0) {
					html += '<button type="button" class="mp-cc-summary-card__breakdown-remove" data-gift-card-remove="1" data-code="' + escapeHtml(gc0) + '" aria-label="' + escapeHtml(getUiText('step_4.gift_card_remove', 'Снять подарочную карту')) + '">×</button>';
				}
			}
			html += '</div>';
		}
		html += '</div></div>';
		return html;
	}

	function buildPaymentMiniReviewHtml(state) {
		var cfg = getStepFourConfig();
		var pb = cfg.payment_block || {};
		var mr = pb.summary_mini_review && typeof pb.summary_mini_review === 'object' ? pb.summary_mini_review : {};
		if (mr.enabled === false) {
			return '';
		}
		var pay = state.frontendStore && state.frontendStore.payment ? state.frontendStore.payment : {};
		var gw = trimNonEmpty(pay.gateway);
		var payState = String(pay.state || 'idle');
		var title = trimNonEmpty(mr.title) || getUiText('order_review.payment_mini_title', 'Способ оплаты');
		var intro = trimNonEmpty(mr.intro) || getUiText('order_review.payment_mini_intro', 'Выбранный метод проведения платежа.');
		var gatewayTitle = getSelectedGatewayTitle(state);
		var meta = getGatewayMetaById(gw);
		var desc = '';
		if (mr.show_gateway_description !== false && meta && meta.description) {
			desc = truncateUiPlain(stripTagsHtml(meta.description), 180);
		}
		var methodLbl = trimNonEmpty(mr.method_label) || getUiText('order_review.payment_mini_method', 'Метод');
		var idLbl = trimNonEmpty(mr.id_label) || getUiText('order_review.payment_mini_id', 'Код метода');
		var stateLine = '';
		if (gw && payState === 'syncing') {
			stateLine = trimNonEmpty(mr.state_loading) || getUiText('order_review.payment_mini_loading', 'Сохраняем выбор…');
		} else if (gw && payState === 'success') {
			stateLine = trimNonEmpty(mr.state_success) || getUiText('order_review.payment_mini_synced', 'Способ оплаты сохранён.');
		} else if (gw && payState === 'error') {
			stateLine = trimNonEmpty(mr.state_error) || getUiText('order_review.payment_mini_error', 'Проверьте способ оплаты.');
		}
		var html = '';
		html += '<div class="mp-cc-summary-card__mini-review" data-payment-mini-review="1" data-payment-mini-state="' + escapeHtml(payState) + '" data-payment-gateway="' + escapeHtml(gw) + '">';
		html += '<p class="mp-cc-summary-card__mini-review__title"><strong>' + escapeHtml(title) + '</strong></p>';
		if (intro) {
			html += '<p class="mp-cc-summary-card__mini-review__intro">' + escapeHtml(intro) + '</p>';
		}
		if (gw) {
			html += '<p class="mp-cc-summary-card__mini-review__line"><span class="mp-cc-summary-card__mini-review__k">' + escapeHtml(methodLbl) + '</span> <span class="mp-cc-summary-card__mini-review__v">' + escapeHtml(gatewayTitle || gw) + '</span></p>';
			if (mr.show_gateway_id === true) {
				html += '<p class="mp-cc-summary-card__mini-review__line mp-cc-summary-card__mini-review__line--muted"><span class="mp-cc-summary-card__mini-review__k">' + escapeHtml(idLbl) + '</span> <code class="mp-cc-summary-card__mini-review__code">' + escapeHtml(gw) + '</code></p>';
			}
			if (desc) {
				html += '<p class="mp-cc-summary-card__mini-review__desc">' + escapeHtml(desc) + '</p>';
			}
		} else {
			html += '<p class="mp-cc-summary-card__mini-review__muted">' + escapeHtml(getUiText('order_review.payment_mini_pick', 'Выберите способ оплаты слева.')) + '</p>';
		}
		if (stateLine) {
			var stClass = payState === 'error' ? 'mp-cc-summary-card__mini-review__state--error' : (payState === 'success' ? 'mp-cc-summary-card__mini-review__state--success' : 'mp-cc-summary-card__mini-review__state--muted');
			html += '<p class="mp-cc-summary-card__mini-review__state ' + stClass + '" data-payment-mini-review-state="1">' + escapeHtml(stateLine) + '</p>';
		}
		html += '</div>';
		return html;
	}

	function isPaymentSubmissionLocked(state) {
		return !!(state && state.frontendStore && state.frontendStore.runtime && state.frontendStore.runtime.paymentSubmitting);
	}

	function lockCriticalRequest(key) {
		if (!Object.prototype.hasOwnProperty.call(criticalRequestLocks, key)) {
			return false;
		}
		if (criticalRequestLocks[key]) {
			return false;
		}
		criticalRequestLocks[key] = true;
		return true;
	}

	function unlockCriticalRequest(key) {
		if (!Object.prototype.hasOwnProperty.call(criticalRequestLocks, key)) {
			return;
		}
		criticalRequestLocks[key] = false;
	}

	function recoverFromFailedPayment(state, $app, payload) {
		state.frontendStore.runtime = state.frontendStore.runtime || {};
		state.frontendStore.runtime.paymentSubmitting = false;
		state.frontendStore.payment = state.frontendStore.payment || { gateway: '', state: 'idle' };
		state.frontendStore.payment.state = 'error';
		if (payload && (payload.flow || payload.cart)) {
			syncFromFlow(state, payload.flow || {}, payload.cart || {});
		}
		render(state, $app);
		saveCurrentStepDraft(state);
	}

	function recoverFromStepAjaxFailure(state, $app, fallbackMessage) {
		setRuntimeFlag(state, 'blocked', false);
		syncStoreWithBackend(state, $app).always(function () {
			if (fallbackMessage) {
				notify(fallbackMessage, 'error');
			}
		});
	}

	function recoverFromInvalidSessionState(state, $app) {
		state.frontendStore.runtime = state.frontendStore.runtime || {};
		if (state.frontendStore.runtime.recoveringSession) {
			return;
		}
		state.frontendStore.runtime.recoveringSession = true;
		notify(getUiText('common.session_stale', 'Сессия checkout устарела. Состояние будет восстановлено.'), 'warning');
		postCheckout('session_abandon', { context_id: state.flowContextId }).always(function () {
			syncStoreWithBackend(state, $app).always(function () {
				state.frontendStore.runtime.recoveringSession = false;
				render(state, $app);
			});
		});
	}

	function recoverFromCartDesync(state, $app) {
		syncStoreWithBackend(state, $app).always(function () {
			notify(getUiText('step_1.cart_sync_recovered', 'Корзина была рассинхронизирована и восстановлена.'), 'info');
		});
	}

	function ensureCartSnapshotConsistency(state, $app) {
		var cart = state && state.frontendStore ? state.frontendStore.cart : null;
		var items = cart && Array.isArray(cart.items) ? cart.items : [];
		var summary = cart && cart.summary ? cart.summary : {};
		// items_count в summary — это суммарное количество товаров (get_cart_contents_count),
		// а не число строк корзины. Сравниваем с суммой qty локальных items.
		var backendCount = Number(summary.items_count || 0);
		var localCount = 0;
		for (var i = 0; i < items.length; i += 1) {
			var qty = Number(items[i] && items[i].quantity != null ? items[i].quantity : 0);
			if (!isFinite(qty) || qty < 0) {
				qty = 0;
			}
			localCount += qty;
		}
		if (backendCount > 0 && localCount > 0 && backendCount !== localCount) {
			recoverFromCartDesync(state, $app);
			return false;
		}
		return true;
	}

	function submitFinalPayment(state, $app) {
		if (!state || !state.frontendStore) {
			return;
		}
		state.frontendStore.runtime = state.frontendStore.runtime || {};
		if (isPaymentSubmissionLocked(state)) {
			notify(getUiText('order_review.payment_in_progress', 'Оплата уже отправляется. Подождите.'), 'info');
			return;
		}
		if (!lockCriticalRequest('paymentSubmit')) {
			notify(getUiText('order_review.payment_in_progress', 'Оплата уже отправляется. Подождите.'), 'info');
			return;
		}
		state.frontendStore.runtime.paymentSubmitting = true;
		state.frontendStore.payment = state.frontendStore.payment || { gateway: '', state: 'idle' };
		state.frontendStore.payment.state = 'syncing';
		render(state, $app);
		postCheckout('submit_payment', {
			context_id: state.flowContextId
		}).then(function (response) {
			var data = response && response.data ? response.data : {};
			state.frontendStore.runtime.paymentSubmitting = false;
			state.frontendStore.payment.state = 'success';
			if (data.flow || data.cart) {
				syncFromFlow(state, data.flow || {}, data.cart || {});
			}
			render(state, $app);
			var gatewayRedirect = trimNonEmpty(data && data.gateway_redirect ? data.gateway_redirect : '');
			if (gatewayRedirect) {
				window.location.href = gatewayRedirect;
				return;
			}
			if (data && data.confirmed && trimNonEmpty(data.success_url)) {
				window.location.href = String(data.success_url);
				return;
			}
			notify(getUiText('order_review.payment_unconfirmed', 'Платёж не подтверждён. Проверьте состояние заказа.'), 'error');
		}).fail(function (xhr) {
			var payload = xhr && xhr.responseJSON && xhr.responseJSON.data ? xhr.responseJSON.data : {};
			recoverFromFailedPayment(state, $app, payload);
			if (String(payload.code || '') === 'gateway_not_available') {
				notify(getUiText('order_review.gateway_unavailable', 'Выбранный gateway недоступен. Выберите другой способ оплаты.'), 'error');
			}
			if (String(payload.code || '') === 'stale_context') {
				recoverFromInvalidSessionState(state, $app);
			}
			notify(trimNonEmpty(payload.message) || getUiText('order_review.payment_submit_failed', 'Не удалось отправить оплату. Попробуйте ещё раз.'), 'error');
		}).always(function () {
			unlockCriticalRequest('paymentSubmit');
		});
	}

	function maybeSendGatewayRenderDiagnostics(state, issues) {
		var cfg = getStepFourConfig();
		var pb = cfg.payment_block && typeof cfg.payment_block === 'object' ? cfg.payment_block : {};
		var diagnostics = pb.diagnostics && typeof pb.diagnostics === 'object' ? pb.diagnostics : {};
		if (diagnostics.enabled === false) {
			return;
		}
		if (!Array.isArray(issues) || !issues.length) {
			return;
		}
		postCheckout('gateway_render_diagnostics', {
			context_id: state.flowContextId,
			issues: issues
		});
	}

	function ensureDiscountDefaults(state) {
		if (!state || !state.frontendStore) {
			return;
		}
		var discounts = state.frontendStore.discounts && typeof state.frontendStore.discounts === 'object'
			? state.frontendStore.discounts
			: {};
		discounts.coupons = Array.isArray(discounts.coupons) ? discounts.coupons : [];
		discounts.gift_card = Array.isArray(discounts.gift_card) ? discounts.gift_card : [];
		discounts.coupon_runtime = discounts.coupon_runtime && typeof discounts.coupon_runtime === 'object'
			? discounts.coupon_runtime
			: { code: '', state: 'empty', message: '' };
		discounts.gift_card_runtime = discounts.gift_card_runtime && typeof discounts.gift_card_runtime === 'object'
			? discounts.gift_card_runtime
			: { code: '', state: 'empty', message: '' };
		if (!trimNonEmpty(discounts.coupon_runtime.state)) {
			discounts.coupon_runtime.state = 'empty';
		}
		if (!trimNonEmpty(discounts.gift_card_runtime.state)) {
			discounts.gift_card_runtime.state = 'empty';
		}
		state.frontendStore.discounts = discounts;
	}

	function normalizeCouponBlockIntro(rawIntro, giftBlock) {
		var s = trimNonEmpty(rawIntro);
		if (!s) {
			return '';
		}
		giftBlock = giftBlock && typeof giftBlock === 'object' ? giftBlock : {};
		var giftIntro = trimNonEmpty(giftBlock.intro);
		var giftUi = getUiText('step_4.gift_card_intro', 'Введите код подарочной карты.');
		if (s === giftUi || (giftIntro && s === giftIntro) || s === 'Введите код подарочной карты.' || s === 'Введите код подарочной карты') {
			return '';
		}
		if (s.indexOf('подарочн') !== -1 && s.indexOf('карт') !== -1) {
			return '';
		}
		return s;
	}

	function getCouponCopy() {
		var cfg = getStepFourConfig();
		var c = cfg.coupon_block || {};
		var g = cfg.gift_card_block || {};
		return {
			title: trimNonEmpty(c.title) || trimNonEmpty(g.title) || getUiText('step_4.coupon_title', 'Промокод'),
			intro: normalizeCouponBlockIntro(c.intro, g) || getUiText('step_4.coupon_intro', 'Введите промокод.'),
			inputLabel: trimNonEmpty(c.input_label) || trimNonEmpty(g.input_label) || getUiText('step_4.coupon_input_label', 'Промокод'),
			placeholder: trimNonEmpty(c.placeholder) || trimNonEmpty(g.placeholder) || getUiText('step_4.coupon_placeholder', 'Например, SALE10'),
			applyLabel: trimNonEmpty(c.apply_label) || trimNonEmpty(g.apply_label) || getUiText('step_4.coupon_apply', 'Применить'),
			emptyMessage: trimNonEmpty(c.empty_message) || trimNonEmpty(g.empty_message) || getUiText('step_4.coupon_empty', 'Введите промокод.'),
			successMessage: trimNonEmpty(c.success_message) || trimNonEmpty(g.success_message) || getUiText('step_4.coupon_success', 'Промокод применён.'),
			errorMessage: trimNonEmpty(c.error_message) || trimNonEmpty(g.error_message) || getUiText('step_4.coupon_error', 'Не удалось применить промокод. Проверьте написание и срок действия купона.')
		};
	}

	function isGiftCardPeerNextToPaymentConfigured() {
		var cfg = getStepFourConfig();
		var g = cfg.gift_card_block || {};
		return g.peer_next_to_payment !== false;
	}

	function isGiftCardPwRuntimeAvailable() {
		return Boolean(window.mpCcCheckout && window.mpCcCheckout.giftCardIntegrationAvailable);
	}

	function isGiftCardRemoveAllowed() {
		var cfg = getStepFourConfig();
		var g = cfg.gift_card_block || {};
		return g.allow_remove_applied !== false;
	}

	function shouldHideGiftChipsInCouponBlock(state, opts) {
		if (!isGiftCardPeerNextToPaymentConfigured()) {
			return false;
		}
		if (opts && opts.cartStep === true) {
			return false;
		}
		var cfg = getStepFourConfig();
		var layout = cfg.discount_layout && typeof cfg.discount_layout === 'object' ? cfg.discount_layout : {};
		var placement = trimNonEmpty(layout.placement) || 'step_4';
		return placement === 'step_4';
	}

	function getGiftCardPeerCopy() {
		var cfg = getStepFourConfig();
		var g = cfg.gift_card_block || {};
		var cardTitle = trimNonEmpty(g.card_title) || trimNonEmpty(g.title) || getUiText('step_4.gift_card_title', 'Подарочная карта');
		var cardSubtitle = trimNonEmpty(g.card_subtitle) || trimNonEmpty(g.intro) || getUiText('step_4.gift_card_intro', 'Введите код подарочной карты.');
		var unavailable = trimNonEmpty(g.unavailable_message)
			|| getUiText('step_4.gift_card_peer_unavailable', 'Подарочные карты на этом сайте сейчас недоступны.');
		return {
			cardTitle: cardTitle,
			cardSubtitle: cardSubtitle,
			inputLabel: trimNonEmpty(g.input_label) || getUiText('step_4.gift_card_input_label', 'Номер подарочной карты'),
			placeholder: trimNonEmpty(g.placeholder) || getUiText('step_4.gift_card_placeholder', 'Например, GIFT-123'),
			applyLabel: trimNonEmpty(g.apply_label) || getUiText('step_4.gift_card_apply', 'Применить'),
			emptyMessage: trimNonEmpty(g.empty_message) || getUiText('step_4.gift_card_empty', 'Введите код подарочной карты.'),
			successMessage: trimNonEmpty(g.success_message) || getUiText('step_4.gift_card_success', 'Подарочная карта применена.'),
			errorMessage: trimNonEmpty(g.error_message) || getUiText('step_4.gift_card_error', 'Не удалось применить подарочную карту.'),
			unavailableMessage: unavailable
		};
	}

	function isV2CheckoutUiEnabled(state) {
		return isFlagEnabled(state, flagNames.checkoutUiV2, false);
	}

	function getV2StepScreens(state) {
		var labels = {
			step1: getUiText('step_2.title', 'Адрес и способ доставки'),
			step2: getUiText('step_4.contact_title', 'Получатель'),
			step3: getUiText('step_4.payment_title', 'Способ оплаты'),
			step4: getUiText('common.confirm', 'Подтверждение')
		};
		return [
			{ id: 'delivery_screen', label: labels.step1, legacyStep: 'address_delivery' },
			{ id: 'recipient_screen', label: labels.step2, legacyStep: 'recipient' },
			{ id: 'payment_screen', label: labels.step3, legacyStep: 'payment' },
			{ id: 'confirm_screen', label: labels.step4, legacyStep: 'confirm' }
		];
	}

	function getV2LegacyStepId(screen) {
		return screen && screen.legacyStep ? String(screen.legacyStep) : 'confirm';
	}

	function ensureV2ScreenState(state) {
		if (!isV2CheckoutUiEnabled(state)) {
			return;
		}
		state.v2Screens = getV2StepScreens(state);
		if (typeof state.v2CurrentIndex !== 'number' || state.v2CurrentIndex < 0 || state.v2CurrentIndex >= state.v2Screens.length) {
			if (state.currentStepId === 'confirm') {
				state.v2CurrentIndex = 1;
			} else {
				state.v2CurrentIndex = 0;
			}
		}
		if (typeof state.v2MaxReachedIndex !== 'number' || state.v2MaxReachedIndex < 0) {
			state.v2MaxReachedIndex = state.v2CurrentIndex;
		}
		if (state.v2MaxReachedIndex < state.v2CurrentIndex) {
			state.v2MaxReachedIndex = state.v2CurrentIndex;
		}
	}

	function invalidateV2DownstreamFrom(state, fromIndex) {
		if (!isV2CheckoutUiEnabled(state)) {
			return;
		}
		ensureV2ScreenState(state);
		if (typeof fromIndex !== 'number' || fromIndex < 0) {
			return;
		}
		if (state.v2MaxReachedIndex > fromIndex) {
			state.v2MaxReachedIndex = fromIndex;
		}
		var map = state.frontendStore && state.frontendStore.runtime && state.frontendStore.runtime.invalid_v2_steps
			? state.frontendStore.runtime.invalid_v2_steps
			: {};
		var i;
		for (i = fromIndex + 1; i < state.v2Screens.length; i += 1) {
			if (map[state.v2Screens[i].id]) {
				delete map[state.v2Screens[i].id];
			}
		}
		if (state.frontendStore && state.frontendStore.runtime) {
			state.frontendStore.runtime.invalid_v2_steps = map;
		}
	}

	function setV2StepInvalidState(state, screenId, isInvalid) {
		if (!state || !state.frontendStore || !state.frontendStore.runtime || !screenId) {
			return;
		}
		var map = state.frontendStore.runtime.invalid_v2_steps && typeof state.frontendStore.runtime.invalid_v2_steps === 'object'
			? state.frontendStore.runtime.invalid_v2_steps
			: {};
		if (isInvalid) {
			map[screenId] = true;
		} else if (Object.prototype.hasOwnProperty.call(map, screenId)) {
			delete map[screenId];
		}
		state.frontendStore.runtime.invalid_v2_steps = map;
	}

	function validateRecipientStep(state) {
		var ok = validateContactPaymentStep(state);
		var errors = state.frontendStore && state.frontendStore.form && state.frontendStore.form.errors
			? (state.frontendStore.form.errors.contact || {})
			: {};
		if (errors.payment_gateway) {
			delete errors.payment_gateway;
			ok = Object.keys(errors).length === 0;
			state.frontendStore.form.errors.contact = errors;
		}
		return ok;
	}

	function getV2ShippingCatalog(state) {
		var deliveryCfg = getDeliveryConfig();
		var source = deliveryCfg.shipping_catalog && typeof deliveryCfg.shipping_catalog === 'object' ? deliveryCfg.shipping_catalog : {};
		var mapMethods = source.methods && typeof source.methods === 'object' ? source.methods : {};
		var sortOrder = Array.isArray(source.sort_order) ? source.sort_order : Object.keys(mapMethods);
		var methods = [];
		var currentScenario = normalizeScenarioId(state && state.frontendStore && state.frontendStore.fulfillment ? (state.frontendStore.fulfillment.scenario || '') : '');
		// На шаге «Адрес и доставка» покупатель ещё выбирает способ: фильтр по сценарию скрывает
		// курьер/ПВЗ/почту после выбора «Самовывоз» (сценарий pickup у них не в visibility_scenarios).
		var skipScenarioFilter = !!(state && state.currentStepId === 'address_delivery');
		var i;
		for (i = 0; i < sortOrder.length; i += 1) {
			var methodId = String(sortOrder[i] || '');
			var raw = mapMethods[methodId] && typeof mapMethods[methodId] === 'object' ? mapMethods[methodId] : null;
			if (!raw || raw.active === false) {
				continue;
			}
			var scenarios = Array.isArray(raw.visibility_scenarios) ? raw.visibility_scenarios : [];
			// Исторически "pickup" мог быть ограничен только сценарием pickup.
			// Для шага 1 оставляем его доступным во всех сценариях, чтобы метод не "пропадал" после возврата.
			if (methodId === 'pickup' && scenarios.length === 1 && String(scenarios[0]) === 'pickup') {
				scenarios = ['pickup', 'krasnoyarsk_delivery', 'other_city_delivery'];
			}
			if (!skipScenarioFilter && scenarios.length && currentScenario && scenarios.indexOf(currentScenario) === -1) {
				continue;
			}
			var normalized = {
				id: methodId,
				title: String(raw.title || methodId),
				price: Number(raw.price || 0),
				eta: String(raw.eta || ''),
				description: String(raw.description || ''),
				requires_address: raw.requires_address !== false
			};
			if (raw.tariffs && typeof raw.tariffs === 'object') {
				var tariffs = [];
				var tariffKeys = Object.keys(raw.tariffs);
				var ti;
				for (ti = 0; ti < tariffKeys.length; ti += 1) {
					var tariffId = tariffKeys[ti];
					var tr = raw.tariffs[tariffId] && typeof raw.tariffs[tariffId] === 'object' ? raw.tariffs[tariffId] : null;
					if (!tr || tr.active === false) {
						continue;
					}
					tariffs.push({
						id: tariffId,
						title: String(tr.title || tariffId),
						price: Number(tr.price || 0),
						eta: String(tr.eta || '')
					});
				}
				if (tariffs.length) {
					normalized.tariffs = tariffs;
				}
			}
			methods.push(normalized);
		}
		if (!methods.length) {
			methods = [
				{ id: 'post_russia', title: 'Почта России', price: 321, eta: '', requires_address: true },
				{
					id: 'courier', title: 'Курьером до двери', requires_address: true, tariffs: [
						{ id: 'express', title: 'Курьером до двери (экспресс)', price: 550, eta: '2 дней' },
						{ id: 'standard', title: 'Курьером до двери (стандарт)', price: 375, eta: '2 дней' }
					]
				},
				{
					id: 'pvz', title: 'Доставка до ПВЗ', requires_address: false, tariffs: [
						{ id: 'express', title: 'Доставка до ПВЗ (экспресс)', price: 360, eta: '2 дней' },
						{ id: 'standard', title: 'Доставка до ПВЗ (стандарт)', price: 185, eta: '2 дней' }
					]
				},
				{ id: 'krasnoyarsk_delivery', title: 'Доставка по Красноярску', price: 400, eta: 'в течение дня', requires_address: true },
				{ id: 'pickup', title: 'Самовывоз', price: 0, eta: '', requires_address: false }
			];
		}
		return methods;
	}

	function resolveShippingSelection(methods, selectedMethodId, selectedTariffId) {
		var i;
		for (i = 0; i < methods.length; i += 1) {
			var m = methods[i] || {};
			if (String(m.id || '') !== String(selectedMethodId || '')) {
				continue;
			}
			var result = {
				method_id: String(m.id || ''),
				method_title: String(m.title || ''),
				tariff_id: '',
				tariff_title: '',
				price: Number(m.price || 0),
				eta: String(m.eta || ''),
				requires_address: m.requires_address !== false
			};
			var tariffs = Array.isArray(m.tariffs) ? m.tariffs : [];
			if (!tariffs.length) {
				return result;
			}
			var fallbackTariff = tariffs[0] || {};
			var chosen = fallbackTariff;
			var ti;
			for (ti = 0; ti < tariffs.length; ti += 1) {
				if (String(tariffs[ti].id || '') === String(selectedTariffId || '')) {
					chosen = tariffs[ti];
					break;
				}
			}
			result.tariff_id = String(chosen.id || '');
			result.tariff_title = String(chosen.title || '');
			result.price = Number(chosen.price || 0);
			result.eta = String(chosen.eta || '');
			return result;
		}
		return null;
	}

	function getShippingErrorCopy() {
		var deliveryCfg = getDeliveryConfig();
		var source = deliveryCfg && deliveryCfg.shipping_catalog ? deliveryCfg.shipping_catalog : {};
		var copy = source.error_copy && typeof source.error_copy === 'object' ? source.error_copy : {};
		return {
			methodUnavailable: trimNonEmpty(copy.method_unavailable) || 'Выбранный метод доставки недоступен. Выберите другой вариант.',
			tariffUnavailable: trimNonEmpty(copy.tariff_unavailable) || 'Выбранный тариф недоступен. Выберите другой тариф.'
		};
	}

	function applyShippingSelectionToState(state, selection) {
		if (!selection) {
			return;
		}
		var dateBox = state.frontendStore.fulfillment.date && typeof state.frontendStore.fulfillment.date === 'object'
			? state.frontendStore.fulfillment.date
			: {};
		dateBox.shipping_method_id = String(selection.method_id || '');
		dateBox.shipping_method_title = String(selection.method_title || '');
		dateBox.shipping_tariff_id = String(selection.tariff_id || '');
		dateBox.shipping_tariff_title = String(selection.tariff_title || '');
		dateBox.shipping_price = Number(selection.price || 0);
		dateBox.shipping_eta = String(selection.eta || '');
		dateBox.shipping_requires_address = selection.requires_address !== false;
		state.frontendStore.fulfillment.date = dateBox;

		var contact = state.frontendStore.form.contact || {};
		contact.__address_visibility = contact.__address_visibility && typeof contact.__address_visibility === 'object' ? contact.__address_visibility : {};
		if (dateBox.shipping_method_id === 'pickup' || dateBox.shipping_requires_address === false) {
			contact.__address_visibility.hide_address_fields = true;
			contact.__address_visibility.required_address_fields = false;
			// На шаге 1 («Адрес и доставка») пользователь только что вводил город — его сохраняем,
			// иначе при выборе самовывоза/ПВЗ он сбросится и UI перерисует пустой плейсхолдер.
			var preserveCityOnStepOne = state && state.currentStepId === 'address_delivery';
			delete contact.country;
			delete contact.state;
			if (!preserveCityOnStepOne) {
				delete contact.city;
			}
			delete contact.address_1;
			delete contact.address_2;
			delete contact.postcode;
		} else {
			contact.__address_visibility.hide_address_fields = false;
			contact.__address_visibility.required_address_fields = true;
		}
		state.frontendStore.form.contact = contact;

		var summary = state.frontendStore.cart && state.frontendStore.cart.summary && typeof state.frontendStore.cart.summary === 'object'
			? state.frontendStore.cart.summary
			: {};
		summary.shipping_total = Number(dateBox.shipping_price || 0);
		summary.shipping = summary.shipping_total > 0 ? String(summary.shipping_total.toFixed(0)) + ' ₽' : '';
		state.frontendStore.cart.summary = summary;
	}

	function scenarioByShippingMethod(methodId) {
		var id = String(methodId || '');
		if (id === 'pickup') {
			return 'pickup';
		}
		if (id === 'krasnoyarsk_delivery') {
			return 'krasnoyarsk_delivery';
		}
		return 'other_city_delivery';
	}

	function validateContactPaymentStep(state) {
		ensureContactDefaults(state);
		var contact = state.frontendStore.form.contact || {};
		var cfg = getStepFourConfig();
		var block = cfg.contact_block || {};
		var codes = Array.isArray(block.phone_country_codes) ? block.phone_country_codes : [];
		var constraints = getFieldConstraintConfig();
		var errors = {};
		var ok = true;
		if (isContactFieldVisible('last_name') && isContactFieldRequired('last_name') && !trimNonEmpty(contact.billing_last_name)) {
			errors.billing_last_name = 'required';
			ok = false;
		}
		if (isContactFieldVisible('first_name') && isContactFieldRequired('first_name') && !trimNonEmpty(contact.billing_first_name)) {
			errors.billing_first_name = 'required';
			ok = false;
		}
		if (isContactFieldVisible('patronymic') && (block.patronymic_required || isContactFieldRequired('patronymic')) && !trimNonEmpty(contact.billing_patronymic)) {
			errors.billing_patronymic = 'required';
			ok = false;
		}
		if (isContactFieldVisible('gender') && isContactFieldRequired('gender') && !trimNonEmpty(contact.billing_gender)) {
			errors.billing_gender = 'required';
			ok = false;
		}
		if (isContactFieldVisible('birthdate')) {
			var birthRaw = trimNonEmpty(contact.billing_birthdate);
			if (isContactFieldRequired('birthdate') && !birthRaw) {
				errors.billing_birthdate = 'required';
				ok = false;
			} else if (birthRaw) {
				var m = birthRaw.match(/^(\d{4})-(\d{2})-(\d{2})$/);
				if (!m) {
					errors.billing_birthdate = 'invalid';
					ok = false;
				} else {
					var yyyy = Number(m[1]);
					var mm = Number(m[2]) - 1;
					var dd = Number(m[3]);
					var date = new Date(yyyy, mm, dd);
					if (date.getFullYear() !== yyyy || date.getMonth() !== mm || date.getDate() !== dd) {
						errors.billing_birthdate = 'invalid';
						ok = false;
					} else {
						var today = new Date();
						var age = today.getFullYear() - yyyy;
						var beforeBirthday = (today.getMonth() < mm) || (today.getMonth() === mm && today.getDate() < dd);
						if (beforeBirthday) {
							age -= 1;
						}
						if (age < constraints.minAge || age > constraints.maxAge) {
							errors.billing_birthdate = 'range';
							ok = false;
						}
					}
				}
			}
		}
		if (isContactFieldVisible('order_notes') && trimNonEmpty(contact.order_notes)) {
			var notesCfg = getOrderNotesSettings();
			if (String(contact.order_notes).length > notesCfg.maxLength) {
				errors.order_notes = 'length';
				ok = false;
			}
		}
		if (isContactFieldVisible('email') && isContactFieldRequired('email') && !trimNonEmpty(contact.billing_email)) {
			errors.billing_email = 'required';
			ok = false;
		} else if (isContactFieldVisible('email') && trimNonEmpty(contact.billing_email) && !isValidEmailValue(contact.billing_email)) {
			errors.billing_email = 'format';
			ok = false;
		}
		var meta = findPhoneCountryMeta(codes, contact.phone_country_iso);
		var digits = String(contact.billing_phone_national || '').replace(/\D/g, '');
		var need = constraints.phoneDigitsOverride > 0 ? constraints.phoneDigitsOverride : (meta.national_digits || 10);
		if (isContactFieldVisible('phone') && isContactFieldRequired('phone') && !digits.length) {
			errors.billing_phone_national = 'required';
			ok = false;
		} else if (isContactFieldVisible('phone') && isContactFieldRequired('phone') && digits.length !== need) {
			errors.billing_phone_national = 'format';
			ok = false;
		}
		var addrVis = contact.__address_visibility;
		if (addrVis && !addrVis.hide_address_fields && addrVis.required_address_fields) {
			var ab = cfg.address_block || {};
			var order = Array.isArray(ab.subfields_order)
				? ab.subfields_order
				: ['country', 'state', 'city', 'address_1', 'address_2', 'postcode'];
			var maxZip = Number(ab.postcode_max_length);
			if (!Number.isFinite(maxZip) || maxZip <= 0) {
				maxZip = 16;
			}
			var ki;
			for (ki = 0; ki < order.length; ki++) {
				var ak = order[ki];
				if (!shouldRenderAddressSubfield(ak, contact)) {
					continue;
				}
				if (ak === 'address_2') {
					continue;
				}
				if (ak === 'country') {
					if (!trimNonEmpty(contact.country)) {
						errors.country = 'required';
						ok = false;
					}
					continue;
				}
				if (ak === 'state') {
					if (!trimNonEmpty(contact.state)) {
						errors.state = 'required';
						ok = false;
					}
					continue;
				}
				if (ak === 'city') {
					if (!trimNonEmpty(contact.city)) {
						errors.city = 'required';
						ok = false;
					}
					continue;
				}
				if (ak === 'postcode') {
					var pc = String(contact.postcode || '').trim();
					if (!pc) {
						errors.postcode = 'required';
						ok = false;
					} else if (pc.length > maxZip) {
						errors.postcode = 'postcode';
						ok = false;
					}
					continue;
				}
				if (ak === 'address_1' && !trimNonEmpty(contact.address_1)) {
					errors.address_1 = 'required';
					ok = false;
				}
			}
		}
		var gateways = getAvailablePaymentGateways();
		var paymentCfg = cfg.payment_block && typeof cfg.payment_block === 'object' ? cfg.payment_block : {};
		var paymentRequired = paymentCfg.required !== false;
		var selectedGateway = trimNonEmpty(state.frontendStore && state.frontendStore.payment ? state.frontendStore.payment.gateway : '');
		if (paymentRequired && gateways.length && !selectedGateway) {
			errors.payment_gateway = 'required';
			ok = false;
		}
		state.frontendStore.form.errors = state.frontendStore.form.errors || {};
		state.frontendStore.form.errors.contact = ok ? {} : errors;
		return ok;
	}

	function setStepInvalidState(state, stepId, isInvalid) {
		if (!state || !state.frontendStore || !state.frontendStore.runtime || !stepId) {
			return;
		}
		var map = state.frontendStore.runtime.invalid_steps && typeof state.frontendStore.runtime.invalid_steps === 'object'
			? state.frontendStore.runtime.invalid_steps
			: {};
		if (isInvalid) {
			map[stepId] = true;
		} else if (Object.prototype.hasOwnProperty.call(map, stepId)) {
			delete map[stepId];
		}
		state.frontendStore.runtime.invalid_steps = map;
	}

	function isElementFocusableForValidation(el) {
		if (!el || el.nodeType !== 1) {
			return false;
		}
		if (el.hasAttribute('disabled') || el.getAttribute('aria-disabled') === 'true') {
			return false;
		}
		var tag = String(el.tagName || '').toLowerCase();
		if (tag === 'input' || tag === 'select' || tag === 'textarea' || tag === 'button') {
			return true;
		}
		return el.getAttribute('tabindex') === '-1' && el.id === 'mp-cc-payment-gateway-fields';
	}

	function findFirstInvalidFieldElement($root) {
		var $scope = $root && $root.length ? $root : $(selectors.root);
		var $aria = $scope.find('[aria-invalid="true"]').filter(function () {
			return isElementFocusableForValidation(this);
		}).first();
		if ($aria.length) {
			return $aria;
		}
		var selectorsList = [
			'.mp-cc-input.is-invalid',
			'.mp-cc-select.is-invalid',
			'.mp-cc-payment-card__radio.is-invalid',
			'.mp-cc-payment--has-field-error #mp-cc-payment-gateway-fields',
			'.mp-cc-date-step__helper.is-error'
		];
		var i;
		for (i = 0; i < selectorsList.length; i += 1) {
			var $el = $scope.find(selectorsList[i]).first();
			if ($el.length) {
				return $el;
			}
		}
		return $();
	}

	function getMotionConfig() {
		var m = (window.mpCcCheckout && window.mpCcCheckout.motion && typeof window.mpCcCheckout.motion === 'object')
			? window.mpCcCheckout.motion
			: {};
		return m;
	}

	function isCheckoutMotionMobileViewport() {
		return !!(window.matchMedia && window.matchMedia('(max-width: 767px)').matches);
	}

	/** Десктоп: `durations_ms`; узкий экран: эффективные mobile-длительности (совпадают с desktop при use_desktop_durations). */
	function getEffectiveMotionDurations() {
		var m = getMotionConfig();
		var desk = m.durations_ms && typeof m.durations_ms === 'object' ? m.durations_ms : {};
		var mob = m.durations_ms_mobile_effective && typeof m.durations_ms_mobile_effective === 'object' ? m.durations_ms_mobile_effective : null;
		if (isCheckoutMotionMobileViewport() && mob) {
			return mob;
		}
		return desk;
	}

	function prefersReducedMotionOs() {
		return !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
	}

	function useCheckoutReducedMotion() {
		var m = getMotionConfig();
		if (m.force_reduced_motion) {
			return true;
		}
		if (m.respect_prefers_reduced_motion === false) {
			return false;
		}
		return prefersReducedMotionOs();
	}

	function shouldThrottleMotion(category) {
		var m = getMotionConfig();
		var th = m.throttle || {};
		if (!th.enabled) {
			return false;
		}
		var minInt = Math.max(0, parseInt(String(th.min_interval_ms == null ? 120 : th.min_interval_ms), 10) || 0);
		if (!minInt) {
			return false;
		}
		var key = String(category || 'default');
		var now = Date.now();
		var last = motionThrottleLast[key] || 0;
		if (now - last < minInt) {
			return true;
		}
		motionThrottleLast[key] = now;
		return false;
	}

	function logMotionInstrumentation(label, durationMs) {
		var m = getMotionConfig();
		var flags = (window.mpCcCheckout && window.mpCcCheckout.flags) ? window.mpCcCheckout.flags : {};
		if (!m.instrumentation_enabled && !flags.checkout_testing_mode) {
			return;
		}
		var ms = Math.round(Number(durationMs) || 0);
		if (window.console && window.console.info) {
			window.console.info('[mp-cc-motion]', String(label || 'motion'), ms + 'ms');
		}
		try {
			document.dispatchEvent(new CustomEvent('mp_cc_motion_metric', { detail: { label: String(label || 'motion'), durationMs: ms } }));
		} catch (e0) {
			// ignore
		}
		try {
			window.__mpCcMotionMetrics = window.__mpCcMotionMetrics || [];
			window.__mpCcMotionMetrics.push({ t: Date.now(), label: String(label || ''), ms: ms });
			if (window.__mpCcMotionMetrics.length > 60) {
				window.__mpCcMotionMetrics.shift();
			}
		} catch (e1) {
			// ignore
		}
	}

	function syncMotionRuntimeVars() {
		var root = document.querySelector(selectors.root);
		var m = getMotionConfig();
		var d = getEffectiveMotionDurations();
		if (typeof d.step_transition === 'number' && !Number.isNaN(d.step_transition)) {
			animationDurationMs = Math.max(0, Math.round(Number(d.step_transition)));
		}
		if (!root) {
			return;
		}
		var toggles = m.toggles || {};
		root.classList.toggle('mp-cc-motion-rail-off', toggles.rail === false);
		root.classList.toggle('mp-cc-motion-step-reveal-off', toggles.step_reveal === false);
		var reduced = useCheckoutReducedMotion();
		root.classList.toggle('mp-cc-motion-reduced', reduced);
		var fieldOn = toggles.field_state !== false && !reduced;
		if (fieldOn) {
			root.setAttribute('data-mp-cc-field-motion', '1');
		} else {
			root.removeAttribute('data-mp-cc-field-motion');
		}
	}

	function bindReducedMotionMediaListener() {
		if (!window.matchMedia) {
			return;
		}
		var mq = window.matchMedia('(prefers-reduced-motion: reduce)');
		var fn = function () {
			syncMotionRuntimeVars();
		};
		if (mq.addEventListener) {
			mq.addEventListener('change', fn);
		} else if (mq.addListener) {
			mq.addListener(fn);
		}
	}

	function bindMotionViewportMediaListener() {
		if (!window.matchMedia) {
			return;
		}
		var mq = window.matchMedia('(max-width: 767px)');
		var fn = function () {
			syncMotionRuntimeVars();
		};
		if (mq.addEventListener) {
			mq.addEventListener('change', fn);
		} else if (mq.addListener) {
			mq.addListener(fn);
		}
		var resizeTimer;
		window.addEventListener('resize', function () {
			window.clearTimeout(resizeTimer);
			resizeTimer = window.setTimeout(fn, 120);
		}, { passive: true });
	}

	function scrollToFirstInvalidField($app) {
		var $root = $(selectors.root);
		var $el = findFirstInvalidFieldElement($root);
		var behavior = useCheckoutReducedMotion() ? 'auto' : 'smooth';
		var blockPos = 'center';
		if (window.matchMedia && window.matchMedia('(max-width: 767px)').matches) {
			blockPos = 'nearest';
		}
		if (!$el.length) {
			var $heading = $root.find('#mp-cc-step-heading, #mp-cc-fulfillment-title, #mp-cc-contact-title, #mp-cc-payment-title').first();
			if ($heading.length && $heading.get(0).scrollIntoView) {
				$heading.get(0).scrollIntoView({ behavior: behavior, block: blockPos, inline: 'nearest' });
			}
			return;
		}
		var node = $el.get(0);
		if (node && typeof node.scrollIntoView === 'function') {
			node.scrollIntoView({ behavior: behavior, block: blockPos, inline: 'nearest' });
		}
		window.setTimeout(function () {
			if (!node || typeof node.focus !== 'function') {
				return;
			}
			if ($el.is('.mp-cc-date-step__helper')) {
				return;
			}
			try {
				node.focus({ preventScroll: true });
			} catch (e1) {
				try {
					node.focus();
				} catch (e2) {
					// ignore
				}
			}
		}, useCheckoutReducedMotion() ? 0 : 80);
	}

	function logValidationFailure(state, stepId, errorsMap) {
		var cleanStep = String(stepId || '');
		var errs = errorsMap && typeof errorsMap === 'object' ? errorsMap : {};
		var keys = Object.keys(errs);
		if (!cleanStep || !keys.length) {
			return;
		}
		postCheckout('validation_log', {
			context_id: state.flowContextId,
			step_id: cleanStep,
			errors: errs
		});
	}

	function getContactFieldError(state, fieldKey) {
		var e = state.frontendStore && state.frontendStore.form && state.frontendStore.form.errors && state.frontendStore.form.errors.contact
			? state.frontendStore.form.errors.contact
			: {};
		return e[fieldKey] ? String(e[fieldKey]) : '';
	}

	function buildContactPaymentHtml(state, options) {
		options = options || {};
		var includePayment = options.includePayment !== false;
		ensureContactDefaults(state);
		var contact = state.frontendStore.form.contact || {};
		var cfg = getStepFourConfig();
		var block = cfg.contact_block || {};
		var codes = Array.isArray(block.phone_country_codes) ? block.phone_country_codes : [];
		var meta = findPhoneCountryMeta(codes, contact.phone_country_iso);
		var nationalDigits = String(contact.billing_phone_national || '').replace(/\D/g, '');
		var displayPhone = formatNationalPhoneDisplay(meta.dial, nationalDigits, meta.iso);
		var title = trimNonEmpty(block.title) || getUiText('step_4.title', 'Контакты и оплата');
		var intro = trimNonEmpty(block.intro) || getUiText('step_4.contact_block_intro', 'Укажите данные для связи и оформления заказа.');
		var errLast = getContactFieldError(state, 'billing_last_name');
		var errFirst = getContactFieldError(state, 'billing_first_name');
		var errPat = getContactFieldError(state, 'billing_patronymic');
		var errGender = getContactFieldError(state, 'billing_gender');
		var errBirth = getContactFieldError(state, 'billing_birthdate');
		var errNotes = getContactFieldError(state, 'order_notes');
		var errEmail = getContactFieldError(state, 'billing_email');
		var errPhone = getContactFieldError(state, 'billing_phone_national');
		var vm = getStepFourValidationMessages();
		var constraints = getFieldConstraintConfig();
		var order = Array.isArray(block.field_order) ? block.field_order : ['last_name', 'first_name', 'patronymic', 'gender', 'birthdate', 'email', 'phone', 'order_notes'];
		var seen = {};
		var ordered = [];
		var oi;
		for (oi = 0; oi < order.length; oi++) {
			var k = String(order[oi] || '');
			if (!k || seen[k]) {
				continue;
			}
			seen[k] = true;
			ordered.push(k);
		}
		var fallbackOrder = ['last_name', 'first_name', 'patronymic', 'gender', 'birthdate', 'email', 'phone', 'order_notes'];
		var genderOptions = getGenderOptions();
		var notesCfg = getOrderNotesSettings();
		for (oi = 0; oi < fallbackOrder.length; oi++) {
			if (!seen[fallbackOrder[oi]]) {
				ordered.push(fallbackOrder[oi]);
			}
		}
		var layout = block.layout && typeof block.layout === 'object' ? block.layout : {};
		var desktopCols = Math.max(1, Number(layout.desktop_columns || 3));
		var tabletCols = Math.max(1, Number(layout.tablet_columns || 2));
		var mobileCols = Math.max(1, Number(layout.mobile_columns || 1));
		var gridGap = trimNonEmpty(layout.grid_gap) || '0.75rem 1rem';
		var stateStyles = block.field_state_styles && typeof block.field_state_styles === 'object' ? block.field_state_styles : {};
		var invalidStyle = trimNonEmpty(stateStyles.invalid_style) || 'default';
		var hintStyle = trimNonEmpty(stateStyles.hint_style) || 'default';
		var focusStyle = trimNonEmpty(stateStyles.focus_style) || 'default';
		var disabledStyle = trimNonEmpty(stateStyles.disabled_style) || 'default';
		var html = '';
		html += '<section class="mp-cc-contact mp-cc-contact--invalid-' + escapeHtml(invalidStyle) + ' mp-cc-contact--hint-' + escapeHtml(hintStyle) + ' mp-cc-contact--focus-' + escapeHtml(focusStyle) + ' mp-cc-contact--disabled-' + escapeHtml(disabledStyle) + '"' + buildRecipientStylesAttr(state) + ' aria-labelledby="mp-cc-contact-title">';
		html += '<header class="mp-cc-contact__header">';
		html += '<h3 class="mp-cc-contact__title" id="mp-cc-contact-title">' + escapeHtml(title) + '</h3>';
		if (intro) {
			html += '<p class="mp-cc-contact__intro" id="mp-cc-contact-intro">' + escapeHtml(intro) + '</p>';
		}
		html += '</header>';
		html += '<div class="mp-cc-contact__grid" style="--mp-cc-contact-cols:' + escapeHtml(String(desktopCols)) + ';--mp-cc-contact-cols-tablet:' + escapeHtml(String(tabletCols)) + ';--mp-cc-contact-cols-mobile:' + escapeHtml(String(mobileCols)) + ';--mp-cc-contact-gap:' + escapeHtml(gridGap) + ';">';
		for (oi = 0; oi < ordered.length; oi++) {
			var field = ordered[oi];
			if (!isContactFieldVisible(field)) {
				continue;
			}
			if (field === 'last_name') {
				html += '<div class="mp-cc-contact__field mp-cc-contact__field--span-1">';
		html += '<label class="mp-cc-field-label" for="mp-cc-contact-last-name">' + escapeHtml(getContactLabel('last_name')) + '</label>';
		html += '<input type="text" class="mp-cc-input' + (errLast ? ' is-invalid' : '') + '" id="mp-cc-contact-last-name" name="billing_last_name" autocomplete="family-name" ';
		html += 'value="' + escapeHtml(String(contact.billing_last_name || '')) + '" ';
		html += 'data-contact-field="billing_last_name"' + (isContactFieldRequired('last_name') ? ' aria-required="true"' : '');
		var pLast = getContactPlaceholder('last_name');
		if (pLast) { html += ' placeholder="' + escapeHtml(pLast) + '"'; }
		html += errLast ? ' aria-invalid="true" aria-describedby="mp-cc-contact-last-name-err"' : '';
		html += '/>';
		if (errLast) {
			html += '<p class="mp-cc-field-error" id="mp-cc-contact-last-name-err" role="alert">' + escapeHtml(trimNonEmpty(vm.required) || getUiText('step_4.contact_error_required', 'Заполните это поле.')) + '</p>';
		}
		html += '</div>';
				continue;
			}
			if (field === 'first_name') {
				html += '<div class="mp-cc-contact__field mp-cc-contact__field--span-1">';
		html += '<label class="mp-cc-field-label" for="mp-cc-contact-first-name">' + escapeHtml(getContactLabel('first_name')) + '</label>';
		html += '<input type="text" class="mp-cc-input' + (errFirst ? ' is-invalid' : '') + '" id="mp-cc-contact-first-name" name="billing_first_name" autocomplete="given-name" ';
		html += 'value="' + escapeHtml(String(contact.billing_first_name || '')) + '" ';
		html += 'data-contact-field="billing_first_name"' + (isContactFieldRequired('first_name') ? ' aria-required="true"' : '');
		var pFirst = getContactPlaceholder('first_name');
		if (pFirst) { html += ' placeholder="' + escapeHtml(pFirst) + '"'; }
		html += errFirst ? ' aria-invalid="true" aria-describedby="mp-cc-contact-first-name-err"' : '';
		html += '/>';
		if (errFirst) {
			html += '<p class="mp-cc-field-error" id="mp-cc-contact-first-name-err" role="alert">' + escapeHtml(trimNonEmpty(vm.required) || getUiText('step_4.contact_error_required', 'Заполните это поле.')) + '</p>';
		}
		html += '</div>';
				continue;
			}
			if (field === 'patronymic') {
				html += '<div class="mp-cc-contact__field mp-cc-contact__field--span-1">';
		html += '<label class="mp-cc-field-label" for="mp-cc-contact-patronymic">' + escapeHtml(getContactLabel('patronymic')) + '</label>';
		html += '<input type="text" class="mp-cc-input' + (errPat ? ' is-invalid' : '') + '" id="mp-cc-contact-patronymic" name="billing_patronymic" autocomplete="additional-name" ';
		html += 'value="' + escapeHtml(String(contact.billing_patronymic || '')) + '" ';
		html += 'data-contact-field="billing_patronymic"';
		html += (block.patronymic_required || isContactFieldRequired('patronymic')) ? ' aria-required="true"' : '';
		var pPatr = getContactPlaceholder('patronymic');
		if (pPatr) { html += ' placeholder="' + escapeHtml(pPatr) + '"'; }
		html += ' aria-describedby="' + escapeHtml(errPat ? 'mp-cc-contact-patronymic-hint mp-cc-contact-patronymic-err' : 'mp-cc-contact-patronymic-hint') + '"';
		html += errPat ? ' aria-invalid="true"' : '';
		html += '/>';
		html += '<p class="mp-cc-field-hint" id="mp-cc-contact-patronymic-hint">' + escapeHtml(getContactHint('patronymic')) + '</p>';
		if (errPat) {
			html += '<p class="mp-cc-field-error" id="mp-cc-contact-patronymic-err" role="alert">' + escapeHtml(trimNonEmpty(vm.required) || getUiText('step_4.contact_error_required', 'Заполните это поле.')) + '</p>';
		}
		html += '</div>';
				continue;
			}
			if (field === 'gender') {
				html += '<div class="mp-cc-contact__field mp-cc-contact__field--span-1">';
				html += '<label class="mp-cc-field-label" for="mp-cc-contact-gender">' + escapeHtml(getContactLabel('gender')) + '</label>';
				html += '<select id="mp-cc-contact-gender" class="mp-cc-select' + (errGender ? ' is-invalid' : '') + '" data-contact-field="billing_gender"';
				html += isContactFieldRequired('gender') ? ' aria-required="true"' : '';
				html += errGender ? ' aria-invalid="true"' : '';
				{
					var genderDescIds = [];
					if (trimNonEmpty(getContactHint('gender'))) {
						genderDescIds.push('mp-cc-contact-gender-hint');
					}
					if (errGender) {
						genderDescIds.push('mp-cc-contact-gender-err');
					}
					if (genderDescIds.length) {
						html += ' aria-describedby="' + escapeHtml(genderDescIds.join(' ')) + '"';
					}
				}
				html += '>';
				html += '<option value="">' + escapeHtml(genderOptions.placeholder) + '</option>';
				html += '<option value="male"' + (String(contact.billing_gender || '') === 'male' ? ' selected' : '') + '>' + escapeHtml(genderOptions.male) + '</option>';
				html += '<option value="female"' + (String(contact.billing_gender || '') === 'female' ? ' selected' : '') + '>' + escapeHtml(genderOptions.female) + '</option>';
				html += '</select>';
				if (trimNonEmpty(getContactHint('gender'))) {
					html += '<p class="mp-cc-field-hint" id="mp-cc-contact-gender-hint">' + escapeHtml(getContactHint('gender')) + '</p>';
				}
				if (errGender) {
					html += '<p class="mp-cc-field-error" id="mp-cc-contact-gender-err" role="alert">' + escapeHtml(trimNonEmpty(vm.required) || getUiText('step_4.contact_error_required', 'Заполните это поле.')) + '</p>';
				}
				html += '</div>';
				continue;
			}
			if (field === 'birthdate') {
				html += '<div class="mp-cc-contact__field mp-cc-contact__field--span-1">';
				html += '<label class="mp-cc-field-label" for="mp-cc-contact-birthdate">' + escapeHtml(getContactLabel('birthdate')) + '</label>';
				html += '<input type="date" class="mp-cc-input' + (errBirth ? ' is-invalid' : '') + '" id="mp-cc-contact-birthdate" name="billing_birthdate" autocomplete="bday" ';
				html += 'value="' + escapeHtml(String(contact.billing_birthdate || '')) + '" ';
				html += 'data-contact-field="billing_birthdate"' + (isContactFieldRequired('birthdate') ? ' aria-required="true"' : '');
				var pBirth = getContactPlaceholder('birthdate');
				if (pBirth) { html += ' placeholder="' + escapeHtml(pBirth) + '"'; }
				html += ' max="' + escapeHtml((new Date()).toISOString().slice(0, 10)) + '"';
				html += ' aria-describedby="' + escapeHtml(errBirth ? 'mp-cc-contact-birthdate-hint mp-cc-contact-birthdate-err' : 'mp-cc-contact-birthdate-hint') + '"';
				html += errBirth ? ' aria-invalid="true"' : '';
				html += '/>';
				html += '<p class="mp-cc-field-hint" id="mp-cc-contact-birthdate-hint">' + escapeHtml(getContactHint('birthdate')) + '</p>';
				if (errBirth) {
					html += '<p class="mp-cc-field-error" id="mp-cc-contact-birthdate-err" role="alert">' + escapeHtml(getBirthdateErrorText(errBirth)) + '</p>';
				}
				html += '</div>';
				continue;
			}
			if (field === 'email') {
				html += '<div class="mp-cc-contact__field mp-cc-contact__field--span-2">';
		html += '<label class="mp-cc-field-label" for="mp-cc-contact-email">' + escapeHtml(getContactLabel('email')) + '</label>';
		html += '<input type="email" class="mp-cc-input' + (errEmail ? ' is-invalid' : '') + '" id="mp-cc-contact-email" name="billing_email" autocomplete="email" inputmode="email" ';
		html += 'value="' + escapeHtml(String(contact.billing_email || '')) + '" ';
		html += 'data-contact-field="billing_email"' + (isContactFieldRequired('email') ? ' aria-required="true"' : '');
		var pEmail = getContactPlaceholder('email');
		if (pEmail) { html += ' placeholder="' + escapeHtml(pEmail) + '"'; }
		html += ' aria-describedby="' + escapeHtml(errEmail ? 'mp-cc-contact-email-hint mp-cc-contact-email-err' : 'mp-cc-contact-email-hint') + '"';
		html += errEmail ? ' aria-invalid="true"' : '';
		html += '/>';
		html += '<p class="mp-cc-field-hint" id="mp-cc-contact-email-hint">' + escapeHtml(getContactHint('email')) + '</p>';
		if (errEmail) {
			html += '<p class="mp-cc-field-error" id="mp-cc-contact-email-err" role="alert">' + escapeHtml(errEmail === 'format' ? (trimNonEmpty(vm.email_invalid) || getUiText('step_4.contact_error_email', 'Введите корректный email.')) : (trimNonEmpty(vm.required) || getUiText('step_4.contact_error_required', 'Заполните это поле.'))) + '</p>';
		}
		html += '</div>';
				continue;
			}
			if (field === 'phone') {
				html += '<div class="mp-cc-contact__field mp-cc-contact__field--span-2 mp-cc-contact__field--phone">';
		html += '<span class="mp-cc-field-label" id="mp-cc-contact-phone-label">' + escapeHtml(getContactLabel('phone')) + '</span>';
		html += '<div class="mp-cc-contact__phone-row" role="group" aria-labelledby="mp-cc-contact-phone-label">';
		html += '<div class="mp-cc-contact__country">';
		html += '<label class="mp-cc-visually-hidden" for="mp-cc-contact-phone-country">' + escapeHtml(getContactLabel('country_code')) + '</label>';
		html += '<select id="mp-cc-contact-phone-country" class="mp-cc-select mp-cc-select--phone-country" data-contact-phone-country="1"';
		html += ' aria-describedby="' + escapeHtml(errPhone ? 'mp-cc-contact-phone-hint mp-cc-contact-phone-digits-hint mp-cc-contact-phone-err' : 'mp-cc-contact-phone-hint mp-cc-contact-phone-digits-hint') + '"';
		html += errPhone ? ' aria-invalid="true"' : '';
		html += '>';
		var ci;
		for (ci = 0; ci < codes.length; ci++) {
			var opt = codes[ci];
			if (!opt) {
				continue;
			}
			var iso = String(opt.iso || '');
			var dial = String(opt.dial || '');
			var sel = iso.toUpperCase() === String(contact.phone_country_iso || '').toUpperCase();
			var flagEmoji = isoToFlagEmoji(iso);
			var countryLabel = trimNonEmpty(opt.label) ? String(opt.label) : iso;
			var optLabel = (flagEmoji ? flagEmoji + ' ' : '') + dial + ' ' + countryLabel;
			html += '<option value="' + escapeHtml(iso) + '"' + (sel ? ' selected' : '') + '>' + escapeHtml(optLabel) + '</option>';
		}
		html += '</select>';
		html += '</div>';
		html += '<div class="mp-cc-contact__national">';
		html += '<label class="mp-cc-visually-hidden" for="mp-cc-contact-phone-national">' + escapeHtml(getContactLabel('phone')) + '</label>';
		html += '<input type="tel" class="mp-cc-input' + (errPhone ? ' is-invalid' : '') + '" id="mp-cc-contact-phone-national" name="billing_phone_national" autocomplete="tel-national" inputmode="numeric" ';
		html += 'value="' + escapeHtml(displayPhone) + '" ';
		html += 'data-contact-phone-national="1"' + (isContactFieldRequired('phone') ? ' aria-required="true"' : '');
		var pPhone = getContactPlaceholder('phone');
		if (pPhone) { html += ' placeholder="' + escapeHtml(pPhone) + '"'; }
		html += ' aria-describedby="' + escapeHtml(errPhone ? 'mp-cc-contact-phone-hint mp-cc-contact-phone-digits-hint mp-cc-contact-phone-err' : 'mp-cc-contact-phone-hint mp-cc-contact-phone-digits-hint') + '"';
		html += errPhone ? ' aria-invalid="true"' : '';
		html += '/>';
		html += '</div>';
		html += '</div>';
		var needHintDigits = constraints.phoneDigitsOverride > 0 ? constraints.phoneDigitsOverride : (meta.national_digits || 10);
		var digitsHint = 'Для ' + (trimNonEmpty(meta.label) ? meta.label : meta.iso) + ': ' + String(needHintDigits) + ' ' + ruDigitsWord(needHintDigits) + ' без кода страны (' + meta.dial + ').';
		html += '<p class="mp-cc-field-hint" id="mp-cc-contact-phone-hint">' + escapeHtml(getContactHint('phone')) + '</p>';
		html += '<p class="mp-cc-field-hint mp-cc-field-hint--sub" id="mp-cc-contact-phone-digits-hint">' + escapeHtml(digitsHint) + '</p>';
		if (errPhone) {
			var phoneMsg = errPhone === 'required'
				? (trimNonEmpty(vm.phone_required) || getUiText('step_4.contact_error_phone_required', 'Укажите номер телефона.'))
				: (trimNonEmpty(vm.phone_format) || getUiText('step_4.contact_error_phone', 'Введите номер полностью.'));
			html += '<p class="mp-cc-field-error" id="mp-cc-contact-phone-err" role="alert">' + escapeHtml(phoneMsg) + '</p>';
		}
				html += '</div>';
				continue;
			}
			if (field === 'order_notes') {
				if (!isContactFieldVisible('order_notes')) {
					continue;
				}
				var notesCfgInline = getOrderNotesSettings();
				var notesValue = String(contact.order_notes || '');
				if (notesValue.length > notesCfgInline.maxLength) {
					notesValue = notesValue.slice(0, notesCfgInline.maxLength);
				}
				var remainNotes = notesCfgInline.maxLength - notesValue.length;
				html += '<div class="mp-cc-contact__field mp-cc-contact__field--span-2">';
				html += '<label class="mp-cc-field-label" for="mp-cc-contact-order-notes">' + escapeHtml(getContactLabel('order_notes')) + '</label>';
				html += '<textarea class="mp-cc-input' + (errNotes ? ' is-invalid' : '') + '" id="mp-cc-contact-order-notes" name="order_notes" rows="4"';
				html += ' data-contact-field="order_notes" maxlength="' + escapeHtml(String(notesCfgInline.maxLength)) + '"';
				var pNotesInline = getContactPlaceholder('order_notes');
				if (pNotesInline) { html += ' placeholder="' + escapeHtml(pNotesInline) + '"'; }
				html += errNotes ? ' aria-invalid="true" aria-describedby="mp-cc-contact-order-notes-err"' : '';
				html += '>';
				html += escapeHtml(notesValue);
				html += '</textarea>';
				if (trimNonEmpty(getContactHint('order_notes'))) {
					html += '<p class="mp-cc-field-hint">' + escapeHtml(getContactHint('order_notes')) + '</p>';
				}
				if (notesCfgInline.showCounter) {
					html += '<p class="mp-cc-field-hint" data-order-notes-counter="1">' + escapeHtml('Осталось символов: ' + String(remainNotes)) + '</p>';
				}
				if (errNotes) {
					html += '<p class="mp-cc-field-error" id="mp-cc-contact-order-notes-err" role="alert">' + escapeHtml(notesCfgInline.lengthErrorText) + '</p>';
				}
				html += '</div>';
				continue;
			}
		}
		html += '</div>';
		if (includePayment) {
			html += buildPaymentGatewaysHtml(state);
		}
		html += '</section>';
		return html;
	}

	function normalizePaymentCardSurface(raw) {
		var s = String(raw || '').toLowerCase().trim();
		if (s === 'classic' || s === 'visual' || s === 'in_card' || s === 'segment_preview') {
			return s;
		}
		return 'visual';
	}

	function resolvePaymentRenderSurface(state, pb) {
		var requested = normalizePaymentCardSurface(pb.card_surface);
		if (requested === 'segment_preview') {
			return requested;
		}
		if (requested === 'classic') {
			return requested;
		}
		if (pb.auto_classic_on_empty_gateway_fields === false) {
			return requested;
		}
		var p = state.frontendStore && state.frontendStore.payment ? state.frontendStore.payment : {};
		if (String(p.state || '') === 'syncing') {
			return requested;
		}
		var selected = trimNonEmpty(p.gateway);
		if (!selected) {
			return requested;
		}
		var brand = classifyPaymentGatewayBrand(selected);
		if (brand === 'bank') {
			return requested;
		}
		if (!shouldEmbedGatewayPaymentFields(requested, brand)) {
			return requested;
		}
		if (String(p.fieldsHydration || '') !== 'settled') {
			return requested;
		}
		if (trimNonEmpty(p.fieldsHtml)) {
			return requested;
		}
		if (String(p.fieldsGatewayId || '') !== String(selected)) {
			return requested;
		}
		return 'classic';
	}

	function classifyPaymentGatewayBrand(gatewayId) {
		var s = String(gatewayId || '').toLowerCase();
		if (s.indexOf('gift') !== -1 || s.indexOf('coupon') !== -1) {
			return 'generic';
		}
		if (s.indexOf('robokassa') !== -1) {
			return 'robokassa';
		}
		if (s.indexOf('yookassa') !== -1 || s.indexOf('yandex_kassa') !== -1 || s.indexOf('yandex') !== -1) {
			return 'yookassa';
		}
		if (
			s === 'bacs' ||
			s.indexOf('bank_transfer') !== -1 ||
			s.indexOf('direct_bank_transfer') !== -1 ||
			s.indexOf('bank') !== -1
		) {
			return 'bank';
		}
		if (s.indexOf('stripe') !== -1 || s.indexOf('woocommerce_payments') !== -1 || s.indexOf('woopayments') !== -1 || s.indexOf('ppcp') !== -1 || s.indexOf('square') !== -1 || s.indexOf('mollie') !== -1 || s.indexOf('card') !== -1 || s.indexOf('tinkoff') !== -1 || s.indexOf('tbank') !== -1 || s.indexOf('cloudpayments') !== -1 || s.indexOf('dolyame') !== -1 || s.indexOf('sber') !== -1 || s.indexOf('sbp') !== -1) {
			return 'bank';
		}
		return 'generic';
	}

	function getPaymentCardArtUrls() {
		var box = window.mpCcCheckout && window.mpCcCheckout.paymentCardArt ? window.mpCcCheckout.paymentCardArt : {};
		return {
			bank: trimNonEmpty(box.bank),
			generic: trimNonEmpty(box.generic) || trimNonEmpty(box.bank),
			robokassa: trimNonEmpty(box.robokassa),
			yookassa: trimNonEmpty(box.yookassa)
		};
	}

	function getGatewayArtUrl(gatewayId) {
		var brand = classifyPaymentGatewayBrand(gatewayId);
		var art = getPaymentCardArtUrls();
		if (brand === 'robokassa') {
			return art.robokassa || '';
		}
		if (brand === 'yookassa') {
			return art.yookassa || '';
		}
		if (brand === 'bank') {
			return art.bank || '';
		}
		return art.generic || '';
	}

	function isSegmentPreviewEligible(gateways) {
		if (!Array.isArray(gateways) || gateways.length !== 2) {
			return false;
		}
		var brands = {};
		var i;
		for (i = 0; i < gateways.length; i += 1) {
			brands[classifyPaymentGatewayBrand(gateways[i].id)] = true;
		}
		return !!(brands.robokassa && brands.yookassa);
	}

	function buildPaymentSegmentPreviewMarkup(gateways, selected, errPayment, payRadioA11y) {
		var html = '';
		var previewGateway = null;
		var i;
		for (i = 0; i < gateways.length; i += 1) {
			if (String(gateways[i].id) === String(selected)) {
				previewGateway = gateways[i];
				break;
			}
		}
		if (!previewGateway && gateways.length) {
			previewGateway = gateways[0];
		}
		html += '<div class="mp-cc-payment-segment" role="tablist" aria-label="' + escapeHtml(getUiText('step_4.payment_method_group_label', 'Выбор способа оплаты')) + '">';
		for (i = 0; i < gateways.length; i += 1) {
			var g = gateways[i];
			var isSelected = String(g.id) === String(selected);
			var pseudoSelected = !trimNonEmpty(selected) && i === 0;
			html += '<label class="mp-cc-payment-segment__tab' + (isSelected || pseudoSelected ? ' is-active' : '') + '" role="tab" aria-selected="' + (isSelected || pseudoSelected ? 'true' : 'false') + '">';
			html += '<input type="radio" class="mp-cc-payment-segment__radio mp-cc-payment-card__radio' + (errPayment ? ' is-invalid' : '') + '" name="mp_cc_payment_gateway" value="' + escapeHtml(g.id) + '" data-payment-gateway="1"' + payRadioA11y + (isSelected ? ' checked' : '') + ' autocomplete="off" />';
			html += '<span class="mp-cc-payment-segment__label">' + escapeHtml(g.title) + '</span>';
			html += '</label>';
		}
		html += '</div>';
		if (previewGateway) {
			var previewArt = getGatewayArtUrl(previewGateway.id);
			html += '<article class="mp-cc-payment-preview">';
			html += '<div class="mp-cc-payment-preview__art-wrap">';
			if (previewArt) {
				html += '<img class="mp-cc-payment-preview__art" src="' + escapeHtml(previewArt) + '" alt="" decoding="async" loading="lazy" />';
			}
			html += '</div>';
			html += '<p class="mp-cc-payment-preview__title">' + escapeHtml(previewGateway.title) + '</p>';
			html += '</article>';
		}
		if (gateways.length > 1) {
			for (i = 0; i < gateways.length; i += 1) {
				if (previewGateway && String(gateways[i].id) === String(previewGateway.id)) {
					continue;
				}
				var ghost = gateways[i];
				var ghostArt = getGatewayArtUrl(ghost.id);
				html += '<div class="mp-cc-payment-preview-ghost" aria-hidden="true">';
				html += '<div class="mp-cc-payment-preview-ghost__thumb">';
				if (ghostArt) {
					html += '<img class="mp-cc-payment-preview-ghost__art" src="' + escapeHtml(ghostArt) + '" alt="" decoding="async" loading="lazy" />';
				}
				html += '</div>';
				html += '<span class="mp-cc-payment-preview-ghost__name">' + escapeHtml(ghost.title) + '</span>';
				html += '<span class="mp-cc-payment-preview-ghost__percent">40%</span>';
				html += '</div>';
				break;
			}
		}
		return html;
	}

	function buildPaymentCardShellMarkup(g, brand, surfaceMode) {
		var art = getPaymentCardArtUrls();
		var artUrl = '';
		if (brand === 'robokassa' && art.robokassa) {
			artUrl = art.robokassa;
		} else if (brand === 'yookassa' && art.yookassa) {
			artUrl = art.yookassa;
		} else if (brand === 'bank' && art.bank) {
			artUrl = art.bank;
		} else if (brand === 'generic' && art.generic) {
			artUrl = art.generic;
		}
		var badge = '';
		if (brand === 'robokassa') {
			badge = '<span class="mp-cc-payment-card__mark">Robokassa</span>';
		} else if (brand === 'yookassa') {
			badge = '<span class="mp-cc-payment-card__mark">YooKassa</span>';
		} else if (brand === 'bank') {
			badge = '<span class="mp-cc-payment-card__chip" aria-hidden="true"></span><span class="mp-cc-payment-card__mark">' + escapeHtml(getUiText('step_4.payment_brand_bank', 'Банковская карта')) + '</span>';
		} else {
			badge = '<span class="mp-cc-payment-card__mark">' + escapeHtml(String(g.title || 'Pay').slice(0, 22)) + '</span>';
		}
		var faux = '';
		if (brand === 'robokassa' || brand === 'yookassa') {
			faux += '<p class="mp-cc-payment-card__redirect-hint" aria-hidden="true">' + escapeHtml(getUiText('step_4.payment_redirect_hint', 'Оплата на защищённой странице шлюза после «Оформить заказ»')) + '</p>';
		} else if (brand === 'bank' || brand === 'generic') {
			if (surfaceMode === 'in_card') {
				faux += '<p class="mp-cc-payment-card__redirect-hint mp-cc-payment-card__redirect-hint--subtle" aria-hidden="true">' + escapeHtml(getUiText('step_4.payment_card_fields_in_card', 'Реквизиты — в защищённых полях модуля в блоке на карте')) + '</p>';
			} else {
				faux += '<p class="mp-cc-payment-card__redirect-hint mp-cc-payment-card__redirect-hint--subtle" aria-hidden="true">' + escapeHtml(getUiText('step_4.payment_card_fields_below', 'Реквизиты — в полях платёжного модуля ниже')) + '</p>';
			}
		}
		var shellMods = ' mp-cc-payment-card__shell--brand-' + escapeHtml(brand);
		if (artUrl) {
			shellMods += ' mp-cc-payment-card__shell--has-art';
		}
		var html = '';
		html += '<div class="mp-cc-payment-card__shell' + shellMods + '" aria-hidden="true">';
		if (artUrl) {
			html += '<img class="mp-cc-payment-card__shell-art" src="' + escapeHtml(artUrl) + '" alt="" decoding="async" loading="lazy" />';
		}
		html += '<div class="mp-cc-payment-card__shell-bg"></div>';
		html += '<div class="mp-cc-payment-card__shell-body">';
		html += badge;
		html += faux;
		html += '</div></div>';
		return html;
	}

	function shouldEmbedGatewayPaymentFields(surface, brand) {
		if (surface === 'classic') {
			return false;
		}
		if (brand === 'robokassa' || brand === 'yookassa') {
			return false;
		}
		return true;
	}

	function buildPaymentGatewayFieldsBlock(state, selectedGateway, surface, layout) {
		var brand = classifyPaymentGatewayBrand(selectedGateway);
		if (!shouldEmbedGatewayPaymentFields(surface, brand)) {
			return '';
		}
		if (layout === 'in_card' && surface !== 'in_card') {
			return '';
		}
		if (layout === 'below_grid' && surface === 'in_card') {
			return '';
		}
		var p = state.frontendStore && state.frontendStore.payment ? state.frontendStore.payment : {};
		var htmlBlock = String(p.fieldsHtml || '');
		if (!trimNonEmpty(htmlBlock)) {
			return '';
		}
		if (String(p.fieldsGatewayId || '') !== String(selectedGateway)) {
			return '';
		}
		var lead = getUiText('step_4.payment_gateway_fields_lead', 'Данные карты вводятся в защищённых полях выбранного способа оплаты (модуль WooCommerce):');
		var gwClass = 'payment_method_' + String(selectedGateway).replace(/[^a-z0-9_\-]/gi, '');
		var wrapClass = layout === 'in_card' ? 'mp-cc-payment-gateway-fields mp-cc-payment-gateway-fields--in-card' : 'mp-cc-payment-gateway-fields';
		var compat = '';
		if (String(p.gatewayCompatIssue || '') === 'no_interactive_fields') {
			compat = '<p class="mp-cc-payment-gateway-fields__compat" role="status">' + escapeHtml(getUiText('step_4.payment_gateway_compat_hint', 'Поля шлюза не обнаружены автоматически: при проблемах с оплатой выберите другой способ или обновите страницу. Мы не подменяем ввод шлюза собственными масками.')) + '</p>';
		}
		var gwRegionLabel = getUiText('step_4.payment_gateway_fields_region', 'Поля выбранного способа оплаты');
		return '<div class="' + wrapClass + '" id="mp-cc-payment-gateway-fields" tabindex="-1" role="region" aria-label="' + escapeHtml(gwRegionLabel) + '">' +
			'<p class="mp-cc-payment-gateway-fields__lead">' + escapeHtml(lead) + '</p>' +
			compat +
			'<div class="wc_payment_box payment_box ' + escapeHtml(gwClass) + ' mp-cc-payment-gateway-fields__inner">' +
			htmlBlock +
			'</div></div>';
	}

	/**
	 * Проверка «есть ли живые контролы шлюза» после init/updated_checkout.
	 * Не вешаем маски/валидацию на поля — только диагностика и подсказка (21.1 / SDK).
	 */
	function scheduleGatewayFieldsCompatProbe(state, $app) {
		if (!state || !$app || !$app.length) {
			return;
		}
		window.clearTimeout(state.__mpCcCompatTimer);
		state.__mpCcCompatTimer = window.setTimeout(function () {
			state.__mpCcCompatTimer = 0;
			if (!state.frontendStore || !state.frontendStore.payment) {
				return;
			}
			var p = state.frontendStore.payment;
			var el = $app.find('#mp-cc-payment-gateway-fields .mp-cc-payment-gateway-fields__inner').get(0);
			if (!el || !el.querySelector) {
				return;
			}
			if (!trimNonEmpty(p.fieldsHtml)) {
				return;
			}
			var brand = classifyPaymentGatewayBrand(p.gateway);
			if (brand === 'robokassa' || brand === 'yookassa') {
				return;
			}
			var interactive = el.querySelector('input:not([type="hidden"]):not([disabled]),select:not([disabled]),textarea:not([disabled]),iframe');
			var prevIssue = String(p.gatewayCompatIssue || '');
			var nextIssue = interactive ? '' : 'no_interactive_fields';
			if (prevIssue === nextIssue) {
				return;
			}
			p.gatewayCompatIssue = nextIssue;
			if (nextIssue) {
				maybeSendGatewayRenderDiagnostics(state, [
					'mp_cc_payment_fields: no interactive controls after init_checkout/updated_checkout for gateway ' + String(p.gateway || '')
				]);
			}
			render(state, $app);
		}, 750);
	}

	function mountPaymentGatewayFields(state, $app) {
		if (!window.jQuery) {
			return;
		}
		var $inner = $app.find('#mp-cc-payment-gateway-fields .mp-cc-payment-gateway-fields__inner');
		if (!$inner.length || !$inner.children().length) {
			if (state) {
				state.__mpCcPaymentMount = state.__mpCcPaymentMount || { sig: '' };
				state.__mpCcPaymentMount.sig = '';
			}
			if (state && state.frontendStore && state.frontendStore.payment) {
				state.frontendStore.payment.gatewayCompatIssue = '';
			}
			return;
		}
		var p = state && state.frontendStore && state.frontendStore.payment ? state.frontendStore.payment : {};
		var sig = String(p.gateway || '') + '|' + String((p.fieldsHtml || '').length);
		state.__mpCcPaymentMount = state.__mpCcPaymentMount || { sig: '' };
		if (state.__mpCcPaymentMount.sig === sig) {
			return;
		}
		state.__mpCcPaymentMount.sig = sig;
		var $body = $(document.body);
		$body.trigger('init_checkout');
		$body.trigger('updated_checkout');
		scheduleGatewayFieldsCompatProbe(state, $app);
	}

	function paymentFieldPayloadFromAjaxData(data) {
		if (!data || typeof data !== 'object') {
			return null;
		}
		if (!Object.prototype.hasOwnProperty.call(data, 'payment_fields_html')) {
			return null;
		}
		return {
			payment_fields_html: data.payment_fields_html,
			payment_fields_gateway: data.payment_fields_gateway
		};
	}

	function patchPaymentFieldsFromContext(state, context) {
		if (!state || !state.frontendStore || !state.frontendStore.payment || !context || typeof context !== 'object') {
			return;
		}
		if (!Object.prototype.hasOwnProperty.call(context, 'payment_fields_html')) {
			return;
		}
		state.frontendStore.payment.fieldsHtml = String(context.payment_fields_html || '');
		state.frontendStore.payment.fieldsGatewayId = String(
			context.payment_fields_gateway || state.frontendStore.payment.gateway || ''
		);
		state.frontendStore.payment.fieldsHydration = 'settled';
		state.frontendStore.payment.gatewayCompatIssue = '';
	}

	function buildGiftCardPeerCardHtml(state) {
		if (!isGiftCardPeerNextToPaymentConfigured()) {
			return '';
		}
		ensureDiscountDefaults(state);
		var copy = getGiftCardPeerCopy();
		var pwOk = isGiftCardPwRuntimeAvailable();
		var cartSummary = state.frontendStore && state.frontendStore.cart && state.frontendStore.cart.summary && typeof state.frontendStore.cart.summary === 'object'
			? state.frontendStore.cart.summary
			: {};
		var appliedGiftCards = Array.isArray(cartSummary.applied_gift_cards) ? cartSummary.applied_gift_cards : [];
		var rt = state.frontendStore && state.frontendStore.discounts && state.frontendStore.discounts.gift_card_runtime
			? state.frontendStore.discounts.gift_card_runtime
			: { code: '', state: 'empty', message: '' };
		var code = String(rt.code || '');
		var runtimeState = String(rt.state || 'empty');
		var msg = trimNonEmpty(rt.message);
		var hasApplied = appliedGiftCards.length > 0;
		var styles = getStepFourConfig().discount_block_styles || {};
		var stateClass = String(styles.state_empty || 'default');
		if (runtimeState === 'loading') {
			stateClass = 'busy';
		} else if (hasApplied || runtimeState === 'success') {
			stateClass = String(styles.state_success || 'success');
		} else if (runtimeState === 'error') {
			stateClass = String(styles.state_error || 'error');
		}
		var artMap = window.mpCcCheckout && window.mpCcCheckout.paymentCardArt ? window.mpCcCheckout.paymentCardArt : {};
		var giftArt = artMap && artMap.gift_card ? String(artMap.gift_card) : '';
		var html = '';
		html += '<article class="mp-cc-gift-peer mp-cc-gift-peer--' + escapeHtml(stateClass) + (!pwOk ? ' mp-cc-gift-peer--unavailable' : '') + '" data-gift-peer-card="1">';
		html += '<div class="mp-cc-gift-peer__shell">';
		html += '<div class="mp-cc-gift-peer__shell-bg" aria-hidden="true"></div>';
		if (giftArt) {
			html += '<img class="mp-cc-gift-peer__shell-art" src="' + escapeHtml(giftArt) + '" alt="" decoding="async" loading="lazy" />';
		}
		html += '<div class="mp-cc-gift-peer__shell-body">';
		html += '<span class="mp-cc-visually-hidden">' + escapeHtml(getUiText('step_4.gift_card_peer_badge', 'Подарок')) + '</span>';
		html +=
			'<span class="mp-cc-gift-peer__bow" aria-hidden="true">' +
			'<svg class="mp-cc-gift-peer__bow-svg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 56 40" width="48" height="34" focusable="false" aria-hidden="true">' +
			'<path fill="none" stroke="currentColor" stroke-width="1.45" stroke-linecap="round" stroke-linejoin="round" d="M28 6c-7-2-16 1-16 10 0 6 6 10 16 8m0-18c7-2 16 1 16 10 0 6-6 10-16 8M28 16v16M18 20c-4 4-6 10-6 16M38 20c4 4 6 10 6 16"/>' +
			'</svg></span>';
		html += '<p class="mp-cc-gift-peer__headline">' + escapeHtml(copy.cardTitle) + '</p>';
		if (pwOk && copy.cardSubtitle) {
			html += '<p class="mp-cc-gift-peer__lede">' + escapeHtml(copy.cardSubtitle) + '</p>';
		}
		if (pwOk) {
			html += '<span class="mp-cc-gift-peer__rule" aria-hidden="true"></span>';
		}
		if (!pwOk) {
			html += '<p class="mp-cc-gift-peer__blocked">' + escapeHtml(copy.unavailableMessage) + '</p>';
		}
		html += '</div></div>';
		if (pwOk) {
			html += '<div class="mp-cc-gift-peer__inlay">';
			if (hasApplied) {
				html += '<div class="mp-cc-gift-peer__applied" data-gift-peer-applied="1">';
				for (var gi = 0; gi < appliedGiftCards.length; gi += 1) {
					var gc = String(appliedGiftCards[gi] || '');
					if (!gc) {
						continue;
					}
					html += '<span class="mp-cc-gift-peer__chip">';
					html += '<span class="mp-cc-gift-peer__chip-code">' + escapeHtml(gc) + '</span>';
					if (isGiftCardRemoveAllowed()) {
						html += '<button type="button" class="mp-cc-gift-peer__chip-remove" data-gift-card-remove="1" data-code="' + escapeHtml(gc) + '" aria-label="' + escapeHtml(getUiText('step_4.gift_card_remove', 'Снять подарочную карту')) + '">×</button>';
					}
					html += '</span>';
				}
				html += '</div>';
			}
			if (!hasApplied) {
				html += '<div class="mp-cc-gift-peer__controls">';
				html += '<label class="mp-cc-visually-hidden" for="mp-cc-gift-peer-code">' + escapeHtml(copy.inputLabel) + '</label>';
				var inpClass = 'mp-cc-input mp-cc-gift-peer__input' + (runtimeState === 'error' ? ' is-invalid' : '');
				var busyAttr = runtimeState === 'loading' ? ' disabled' : '';
				html += '<input type="text" class="' + escapeHtml(inpClass) + '" id="mp-cc-gift-peer-code" data-gift-card-peer-code="1" value="' + escapeHtml(code) + '" placeholder="' + escapeHtml(copy.placeholder) + '" autocomplete="off"' + busyAttr + '/>';
				html += '<button type="button" class="mp-cc-nav__btn mp-cc-nav__btn--next mp-cc-gift-peer__apply" data-gift-card-peer-apply="1"' + busyAttr + '>' + escapeHtml(copy.applyLabel) + '</button>';
				html += '</div>';
			}
			if (msg) {
				var errCls = runtimeState === 'error' ? ' mp-cc-gift-peer__hint--error' : (runtimeState === 'success' ? ' mp-cc-gift-peer__hint--success' : '');
				html += '<p class="mp-cc-gift-peer__hint' + errCls + '" data-gift-card-peer-message="1">' + escapeHtml(msg) + '</p>';
			} else if (hasApplied && runtimeState !== 'error') {
				html += '<p class="mp-cc-gift-peer__hint mp-cc-gift-peer__hint--success" data-gift-card-peer-message="1">' + escapeHtml(copy.successMessage) + '</p>';
			}
			html += '</div>';
		}
		html += '</article>';
		return html;
	}

	function getBankCardVisualConfig() {
		var cfg = getStepFourConfig();
		var pb = cfg.payment_block && typeof cfg.payment_block === 'object' ? cfg.payment_block : {};
		var raw = pb.bank_card_visual && typeof pb.bank_card_visual === 'object' ? pb.bank_card_visual : {};
		return {
			enabled: raw.enabled !== false,
			confirmOnClickOnly: raw.confirm_on_click_only !== false,
			allowDeselect: raw.allow_deselect !== false,
			cardMaxWidth: trimNonEmpty(raw.card_max_width) || '100%',
			glowColor: trimNonEmpty(raw.glow_color) || '#a78bfa',
			glowIntensity: trimNonEmpty(raw.glow_intensity) || 'medium',
			showCheckPill: raw.show_check_pill !== false
		};
	}

	function buildBankCardCssVarsStyle(visualCfg) {
		if (!visualCfg || !visualCfg.enabled) {
			return '';
		}
		var blurStrong = '28px';
		var blurSoft = '10px';
		var alphaStrong = '70%';
		var alphaSoft = '55%';
		if (visualCfg.glowIntensity === 'soft') {
			blurStrong = '18px';
			blurSoft = '6px';
			alphaStrong = '50%';
			alphaSoft = '35%';
		} else if (visualCfg.glowIntensity === 'strong') {
			blurStrong = '38px';
			blurSoft = '14px';
			alphaStrong = '85%';
			alphaSoft = '70%';
		}
		var styles = [];
		styles.push('--mp-cc-pay-glow:' + visualCfg.glowColor);
		styles.push('--mp-cc-pay-card-max:' + visualCfg.cardMaxWidth);
		styles.push('--mp-cc-pay-glow-blur-strong:' + blurStrong);
		styles.push('--mp-cc-pay-glow-blur-soft:' + blurSoft);
		styles.push('--mp-cc-pay-glow-strong-alpha:' + alphaStrong);
		styles.push('--mp-cc-pay-glow-soft-alpha:' + alphaSoft);
		return styles.join(';');
	}

	function isPaymentUserConfirmed(state) {
		return !!(state && state.frontendStore && state.frontendStore.payment && state.frontendStore.payment.user_confirmed === true);
	}

	function isGlowPaymentBrand(brand) {
		var b = String(brand || '');
		return b === 'bank' || b === 'robokassa' || b === 'yookassa';
	}

	function buildPaymentGatewaysHtml(state) {
		var cfg = getStepFourConfig();
		var pb = cfg.payment_block && typeof cfg.payment_block === 'object' ? cfg.payment_block : {};
		var gateways = getAvailablePaymentGateways();
		if (!gateways.length) {
			var emptyTitle = trimNonEmpty(pb.title) || getUiText('step_4.payment_title', 'Способ оплаты');
			var emptyMsg = getUiText('step_4.payment_gateways_empty', 'Способы оплаты не настроены в WooCommerce или недоступны для этой корзины. Проверьте раздел «Платежи» и условия шлюзов.');
			var htmlEmpty = '';
			htmlEmpty += '<section class="mp-cc-payment mp-cc-payment--empty" aria-labelledby="mp-cc-payment-title">';
			htmlEmpty += '<header class="mp-cc-payment__header">';
			htmlEmpty += '<h4 class="mp-cc-payment__title" id="mp-cc-payment-title">' + escapeHtml(emptyTitle) + '</h4>';
			htmlEmpty += '</header>';
			htmlEmpty += '<p class="mp-cc-field-error" role="alert">' + escapeHtml(emptyMsg) + '</p>';
			var peerWhenEmpty = buildGiftCardPeerCardHtml(state);
			if (trimNonEmpty(peerWhenEmpty)) {
				htmlEmpty += '<div class="mp-cc-payment__deck mp-cc-payment__deck--solo-gift">';
				htmlEmpty += '<aside class="mp-cc-payment__deck-aside" aria-label="' + escapeHtml(getGiftCardPeerCopy().cardTitle) + '">';
				htmlEmpty += peerWhenEmpty;
				htmlEmpty += '</aside></div>';
			}
			htmlEmpty += '</section>';
			return htmlEmpty;
		}
		var selected = trimNonEmpty(state.frontendStore && state.frontendStore.payment ? state.frontendStore.payment.gateway : '');
		var errPayment = getContactFieldError(state, 'payment_gateway');
		var title = trimNonEmpty(pb.title) || getUiText('step_4.payment_title', 'Способ оплаты');
		var intro = trimNonEmpty(pb.intro) || getUiText('step_4.payment_intro', 'Выберите удобный способ оплаты.');
		var showDescription = pb.show_description !== false;
		var layout = pb.layout && typeof pb.layout === 'object' ? pb.layout : {};
		var desktopCols = Math.max(1, Number(layout.desktop_columns || 2));
		var tabletCols = Math.max(1, Number(layout.tablet_columns || 2));
		var mobileCols = Math.max(1, Number(layout.mobile_columns || 1));
		if (gateways.length === 1) {
			desktopCols = 1;
			tabletCols = 1;
			mobileCols = 1;
		}
		var messages = pb.messages && typeof pb.messages === 'object' ? pb.messages : {};
		var paymentState = state.frontendStore && state.frontendStore.payment ? String(state.frontendStore.payment.state || 'idle') : 'idle';
		var twoUpBrands = isSegmentPreviewEligible(gateways);
		var diagnosticsIssues = [];
		if (trimNonEmpty(selected)) {
			var selectedPresent = false;
			for (var si = 0; si < gateways.length; si += 1) {
				if (String(gateways[si].id) === String(selected)) {
					selectedPresent = true;
					break;
				}
			}
			if (!selectedPresent) {
				diagnosticsIssues.push('Selected gateway is missing from available list: ' + selected);
			}
		}
		maybeSendGatewayRenderDiagnostics(state, diagnosticsIssues);
		var surface = resolvePaymentRenderSurface(state, pb);
		if (surface === 'segment_preview' && twoUpBrands) {
			// For current redesign keep two-up cards even if old preset remains in settings.
			surface = 'visual';
		}
		var surfaceClass = surface === 'classic'
			? 'mp-cc-payment--surface-classic'
			: (surface === 'in_card'
				? 'mp-cc-payment--surface-in-card'
				: (surface === 'segment_preview' ? 'mp-cc-payment--surface-segment-preview' : 'mp-cc-payment--surface-visual'));
		if (surface === 'visual' && twoUpBrands) {
			// Two-up layout: keep cards clean, avoid duplicate long text below each card.
			showDescription = false;
		}
		var layoutClass = (surface === 'visual' && twoUpBrands) ? ' mp-cc-payment--layout-two-up' : '';
		var stateClass = paymentState === 'success' ? ' mp-cc-payment--state-success' : (paymentState === 'error' ? ' mp-cc-payment--state-error' : '');
		var errClass = errPayment ? ' mp-cc-payment--has-field-error' : '';
		var html = '';
		html += '<section class="mp-cc-payment mp-cc-payment--' + escapeHtml(trimNonEmpty(pb.card_style) || 'default') + ' mp-cc-payment--radio-' + escapeHtml(trimNonEmpty(pb.radio_style) || 'default') + ' mp-cc-payment--desc-' + escapeHtml(trimNonEmpty(pb.description_style) || 'muted') + ' ' + surfaceClass + layoutClass + stateClass + errClass + (paymentState === 'syncing' ? ' is-loading' : '') + '" aria-labelledby="mp-cc-payment-title">';
		html += '<header class="mp-cc-payment__header">';
		html += '<h4 class="mp-cc-payment__title" id="mp-cc-payment-title">' + escapeHtml(title) + '</h4>';
		if (intro) {
			html += '<p class="mp-cc-payment__intro">' + escapeHtml(intro) + '</p>';
		}
		html += '</header>';
		if (errPayment) {
			html += '<p class="mp-cc-field-error" id="mp-cc-payment-gateway-err" role="alert">' + escapeHtml(getUiText('step_4.payment_error_required', 'Выберите способ оплаты.')) + '</p>';
		}
		var payRadioA11y = errPayment ? ' aria-invalid="true" aria-describedby="mp-cc-payment-gateway-err"' : '';
		var peerHtml = buildGiftCardPeerCardHtml(state);
		var hasPeer = trimNonEmpty(peerHtml);
		if (hasPeer) {
			html += '<div class="mp-cc-payment__deck">';
			html += '<div class="mp-cc-payment__deck-main">';
		}
		var gi;
		if (surface === 'segment_preview') {
			html += buildPaymentSegmentPreviewMarkup(gateways, selected, errPayment, payRadioA11y);
		} else {
			html += '<div class="mp-cc-payment__grid" role="group" aria-label="' + escapeHtml(getUiText('step_4.payment_method_group_label', 'Выбор способа оплаты')) + '" style="--mp-cc-payment-cols:' + escapeHtml(String(desktopCols)) + ';--mp-cc-payment-cols-tablet:' + escapeHtml(String(tabletCols)) + ';--mp-cc-payment-cols-mobile:' + escapeHtml(String(mobileCols)) + ';--mp-cc-payment-gap:' + escapeHtml(trimNonEmpty(layout.grid_gap) || '0.6rem 0.75rem') + ';">';
			for (gi = 0; gi < gateways.length; gi += 1) {
				var g = gateways[gi];
				var isSelected = String(g.id) === String(selected);
				var brand = classifyPaymentGatewayBrand(g.id);
				var activeMod = escapeHtml(trimNonEmpty(pb.card_active_style) || 'accent');
				if (surface === 'classic') {
					html += '<label class="mp-cc-payment-card mp-cc-payment-card--active-' + activeMod + (isSelected ? ' is-active' : '') + '">';
					html += '<input type="radio" class="mp-cc-payment-card__radio' + (errPayment ? ' is-invalid' : '') + '" name="mp_cc_payment_gateway" value="' + escapeHtml(g.id) + '" data-payment-gateway="1"' + payRadioA11y + (isSelected ? ' checked' : '') + ' />';
					html += '<span class="mp-cc-payment-card__title">' + escapeHtml(g.title) + '</span>';
					if (showDescription && g.description) {
						html += '<span class="mp-cc-payment-card__desc">' + escapeHtml(g.description) + '</span>';
					}
					html += '</label>';
				} else {
					var shellRt = '';
					if (isSelected) {
						if (paymentState === 'syncing') {
							shellRt = ' mp-cc-payment-card--rt-loading';
						} else if (paymentState === 'error') {
							shellRt = ' mp-cc-payment-card--rt-error';
						} else if (paymentState === 'success') {
							shellRt = ' mp-cc-payment-card--rt-success';
						}
					}
					var bankVisualCfg = isGlowPaymentBrand(brand) ? getBankCardVisualConfig() : null;
					var userConfirmedClass = '';
					if (bankVisualCfg && bankVisualCfg.enabled && isSelected) {
						var requireClick = bankVisualCfg.confirmOnClickOnly;
						if (!requireClick || isPaymentUserConfirmed(state)) {
							userConfirmedClass = ' is-user-confirmed';
						}
					}
					var bankInlineStyle = '';
					if (bankVisualCfg && bankVisualCfg.enabled) {
						var bankCssVars = buildBankCardCssVarsStyle(bankVisualCfg);
						if (bankCssVars) {
							bankInlineStyle = ' style="' + escapeHtml(bankCssVars) + '"';
						}
					}
					html += '<label class="mp-cc-payment-card mp-cc-payment-card--surface mp-cc-payment-card--brand-' + escapeHtml(brand) + ' mp-cc-payment-card--active-' + activeMod + (isSelected ? ' is-active' : '') + userConfirmedClass + shellRt + '"' + bankInlineStyle + '>';
					html += '<input type="radio" class="mp-cc-payment-card__radio' + (errPayment ? ' is-invalid' : '') + '" name="mp_cc_payment_gateway" value="' + escapeHtml(g.id) + '" data-payment-gateway="1"' + payRadioA11y + (isSelected ? ' checked' : '') + ' autocomplete="off" />';
					html += buildPaymentCardShellMarkup(g, brand, surface);
					if (bankVisualCfg && bankVisualCfg.enabled && bankVisualCfg.showCheckPill) {
						html += '<span class="mp-cc-payment-card__check-pill" aria-hidden="true"></span>';
					}
					html += '<span class="mp-cc-payment-card__title mp-cc-payment-card__title--text">' + escapeHtml(g.title) + '</span>';
					if (showDescription && g.description) {
						html += '<span class="mp-cc-payment-card__desc">' + escapeHtml(g.description) + '</span>';
					}
					if (surface === 'in_card' && isSelected) {
						html += buildPaymentGatewayFieldsBlock(state, selected, surface, 'in_card');
					}
					html += '</label>';
				}
			}
			html += '</div>';
		}
		if (hasPeer) {
			html += '</div>';
			html += '<aside class="mp-cc-payment__deck-aside" aria-label="' + escapeHtml(getGiftCardPeerCopy().cardTitle) + '">' + peerHtml + '</aside>';
			html += '</div>';
		}
		if (surface === 'visual') {
			html += buildPaymentGatewayFieldsBlock(state, selected, surface, 'below_grid');
		}
		if (paymentState === 'syncing') {
			html += '<p class="mp-cc-payment__state mp-cc-payment__state--loading">' + escapeHtml(trimNonEmpty(messages.loading) || getUiText('step_4.payment_loading', 'Сохраняем выбранный способ оплаты...')) + '</p>';
		} else if (paymentState === 'success') {
			html += '<p class="mp-cc-payment__state mp-cc-payment__state--success">' + escapeHtml(trimNonEmpty(messages.success) || getUiText('step_4.payment_success', 'Способ оплаты обновлён.')) + '</p>';
		} else if (paymentState === 'error') {
			html += '<p class="mp-cc-payment__state mp-cc-payment__state--error">' + escapeHtml(trimNonEmpty(messages.error) || getUiText('step_4.payment_error_switch', 'Не удалось переключить способ оплаты.')) + '</p>';
		}
		if (errPayment) {
			html += '<p class="mp-cc-field-error" role="alert">' + escapeHtml(trimNonEmpty(pb.error_message) || getUiText('step_4.payment_error_required', 'Выберите способ оплаты.')) + '</p>';
		}
		html += '</section>';
		return html;
	}

	function buildAddressBlockHtml(state) {
		ensureContactDefaults(state);
		var contact = state.frontendStore.form.contact || {};
		var vis = contact.__address_visibility;
		if (vis && vis.hide_address_fields) {
			return '';
		}
		var cfg = getStepFourConfig();
		var vm = getStepFourValidationMessages();
		var ab = cfg.address_block || {};
		var geo = getAddressGeoMerged();
		var title = trimNonEmpty(ab.title) || getUiText('step_4.address_block_title', 'Адрес доставки');
		var intro = trimNonEmpty(ab.intro) || getUiText('step_4.address_block_intro', '');
		var order = Array.isArray(ab.subfields_order)
			? ab.subfields_order
			: ['country', 'state', 'city', 'address_1', 'address_2', 'postcode'];
		var pi;
		var hasAnyAddressField = false;
		for (pi = 0; pi < order.length; pi++) {
			if (shouldRenderAddressSubfield(order[pi], contact)) {
				hasAnyAddressField = true;
				break;
			}
		}
		if (!hasAnyAddressField) {
			return '';
		}
		var html = '';
		html += '<section class="mp-cc-address"' + buildRecipientStylesAttr(state) + ' aria-labelledby="mp-cc-address-title">';
		html += '<header class="mp-cc-address__header">';
		html += '<h3 class="mp-cc-address__title" id="mp-cc-address-title">' + escapeHtml(title) + '</h3>';
		if (intro) {
			html += '<p class="mp-cc-address__intro" id="mp-cc-address-intro">' + escapeHtml(intro) + '</p>';
		}
		if (cfg.geo_preview && cfg.geo_preview.enabled) {
			var regionsCount = getRegionsForCountry(geo, String(contact.country || '')).length;
			var settlementsCount = getSettlementsForRegion(geo, String(contact.country || ''), String(contact.state || '')).length;
			html += '<p class="mp-cc-address__intro"><strong>Geo debug:</strong> country=' + escapeHtml(String(contact.country || '')) + ', region=' + escapeHtml(String(contact.state || '')) + ', regions=' + escapeHtml(String(regionsCount)) + ', settlements=' + escapeHtml(String(settlementsCount)) + '</p>';
		}
		html += '</header>';
		html += '<div class="mp-cc-address__grid">';
		var idx;
		for (idx = 0; idx < order.length; idx++) {
			var key = order[idx];
			if (!shouldRenderAddressSubfield(key, contact)) {
				continue;
			}
			var err = getContactFieldError(state, key);
			if (key === 'country') {
				html += '<div class="mp-cc-address__field mp-cc-address__field--country">';
				html += '<label class="mp-cc-field-label" for="mp-cc-address-country">' + escapeHtml(getAddressLabel('country')) + '</label>';
				html += '<input type="text" class="mp-cc-input' + (err ? ' is-invalid' : '') + '" id="mp-cc-address-country" name="country" autocomplete="country-name" ';
				html += 'value="' + escapeHtml(String(contact.country || '')) + '" ';
				html += 'placeholder="' + escapeHtml(getUiText('step_4.address_country_placeholder', 'Например: RU или полное название')) + '" ';
				html += 'data-contact-field="country" aria-required="true"';
				html += err ? ' aria-invalid="true"' : '';
				html += '/>';
				if (err) {
					html += '<p class="mp-cc-field-error" id="mp-cc-address-country-err" role="alert">' + escapeHtml(trimNonEmpty(vm.address_required) || getUiText('step_4.address_error_required', 'Заполните это поле.')) + '</p>';
				}
				html += '</div>';
				continue;
			}
			if (key === 'state') {
				html += '<div class="mp-cc-address__field mp-cc-address__field--region">';
				html += '<label class="mp-cc-field-label" for="mp-cc-address-region">' + escapeHtml(getAddressLabel('state')) + '</label>';
				html += '<input type="text" class="mp-cc-input' + (err ? ' is-invalid' : '') + '" id="mp-cc-address-region" name="state" autocomplete="address-level1" ';
				html += 'value="' + escapeHtml(String(contact.state || '')) + '" ';
				html += 'placeholder="' + escapeHtml(getUiText('step_4.address_region_placeholder', 'Регион, область, край')) + '" ';
				html += 'data-contact-field="state" aria-required="true"';
				html += err ? ' aria-invalid="true"' : '';
				html += '/>';
				if (err) {
					var regionMsg = err === 'region'
						? (trimNonEmpty(vm.address_region) || getUiText('step_4.address_error_region', 'Выберите корректный регион.'))
						: (trimNonEmpty(vm.address_required) || getUiText('step_4.address_error_required', 'Заполните это поле.'));
					html += '<p class="mp-cc-field-error" id="mp-cc-address-region-err" role="alert">' + escapeHtml(regionMsg) + '</p>';
				}
				html += '</div>';
				continue;
			}
			if (key === 'city') {
				html += '<div class="mp-cc-address__field mp-cc-address__field--city">';
				html += '<label class="mp-cc-field-label" for="mp-cc-address-city">' + escapeHtml(getAddressLabel('city')) + '</label>';
				html += '<input type="text" class="mp-cc-input' + (err ? ' is-invalid' : '') + '" id="mp-cc-address-city" name="city" autocomplete="address-level2" ';
				html += 'value="' + escapeHtml(String(contact.city || '')) + '" ';
				html += 'placeholder="' + escapeHtml(getUiText('step_4.address_city_placeholder', 'Город, посёлок')) + '" ';
				html += 'data-contact-field="city" aria-required="true"';
				html += err ? ' aria-invalid="true"' : '';
				html += '/>';
				if (err) {
					var cityMsg = err === 'city'
						? (trimNonEmpty(vm.address_city) || getUiText('step_4.address_error_city', 'Выберите населённый пункт из списка.'))
						: (trimNonEmpty(vm.address_required) || getUiText('step_4.address_error_required', 'Заполните это поле.'));
					html += '<p class="mp-cc-field-error" id="mp-cc-address-city-err" role="alert">' + escapeHtml(cityMsg) + '</p>';
				}
				html += '</div>';
				continue;
			}
			if (key === 'address_1') {
				html += '<div class="mp-cc-address__field mp-cc-address__field--line1">';
				html += '<label class="mp-cc-field-label" for="mp-cc-address-line1">' + escapeHtml(getAddressLabel('address_1')) + '</label>';
				html += '<input type="text" class="mp-cc-input' + (err ? ' is-invalid' : '') + '" id="mp-cc-address-line1" name="address_1" autocomplete="address-line1" ';
				html += 'value="' + escapeHtml(String(contact.address_1 || '')) + '" ';
				html += 'data-contact-field="address_1" aria-required="true"';
				html += err ? ' aria-invalid="true"' : '';
				html += '/>';
				if (err) {
					html += '<p class="mp-cc-field-error" id="mp-cc-address-line1-err" role="alert">' + escapeHtml(trimNonEmpty(vm.address_required) || getUiText('step_4.address_error_required', 'Заполните это поле.')) + '</p>';
				}
				html += '</div>';
				continue;
			}
			if (key === 'address_2') {
				html += '<div class="mp-cc-address__field mp-cc-address__field--line2">';
				html += '<label class="mp-cc-field-label" for="mp-cc-address-line2">' + escapeHtml(getAddressLabel('address_2')) + '</label>';
				html += '<input type="text" class="mp-cc-input" id="mp-cc-address-line2" name="address_2" autocomplete="address-line2" ';
				html += 'value="' + escapeHtml(String(contact.address_2 || '')) + '" ';
				html += 'data-contact-field="address_2"';
				html += '/>';
				html += '</div>';
				continue;
			}
			if (key === 'postcode') {
				var pcMsg = err === 'postcode'
					? (trimNonEmpty(vm.address_postcode) || getUiText('step_4.address_error_postcode', 'Слишком длинный индекс.'))
					: (trimNonEmpty(vm.address_required) || getUiText('step_4.address_error_required', 'Заполните это поле.'));
				html += '<div class="mp-cc-address__field mp-cc-address__field--postcode">';
				html += '<label class="mp-cc-field-label" for="mp-cc-address-postcode">' + escapeHtml(getAddressLabel('postcode')) + '</label>';
				html += '<input type="text" class="mp-cc-input' + (err ? ' is-invalid' : '') + '" id="mp-cc-address-postcode" name="postcode" autocomplete="postal-code" inputmode="text" ';
				html += 'value="' + escapeHtml(String(contact.postcode || '')) + '" ';
				html += 'data-contact-field="postcode" aria-required="true"';
				html += err ? ' aria-invalid="true"' : '';
				html += '/>';
				if (err) {
					html += '<p class="mp-cc-field-error" id="mp-cc-address-postcode-err" role="alert">' + escapeHtml(pcMsg) + '</p>';
				}
				html += '</div>';
				continue;
			}
		}
		html += '</div>';
		html += '</section>';
		return html;
	}

	function buildRecipientStylesAttr(state) {
		var cfg = getStepFourConfig();
		var styles = cfg.recipient_styles && typeof cfg.recipient_styles === 'object' ? cfg.recipient_styles : {};
		var vars = {
			'--mp-cc-contact-card-bg': styles.contact_card_bg,
			'--mp-cc-contact-card-border': styles.contact_card_border,
			'--mp-cc-contact-card-radius': styles.contact_card_radius,
			'--mp-cc-contact-card-padding': styles.contact_card_padding,
			'--mp-cc-contact-header-divider': styles.contact_header_divider,
			'--mp-cc-contact-title-size': styles.contact_title_size,
			'--mp-cc-contact-intro-size': styles.contact_intro_size,
			'--mp-cc-contact-label-size': styles.contact_label_size,
			'--mp-cc-contact-input-border': styles.contact_input_border,
			'--mp-cc-contact-input-radius': styles.contact_input_radius,
			'--mp-cc-address-card-bg': styles.address_card_bg,
			'--mp-cc-address-card-border': styles.address_card_border,
			'--mp-cc-address-card-radius': styles.address_card_radius,
			'--mp-cc-address-card-padding': styles.address_card_padding,
			'--mp-cc-address-header-divider': styles.address_header_divider,
			'--mp-cc-address-title-size': styles.address_title_size,
			'--mp-cc-address-intro-size': styles.address_intro_size
		};
		var out = [];
		Object.keys(vars).forEach(function (key) {
			var v = trimNonEmpty(vars[key]);
			if (!v) {
				return;
			}
			out.push(key + ': ' + v);
		});
		return out.length ? ' style="' + escapeHtml(out.join('; ')) + '"' : '';
	}

	function buildDiscountToolsHtml(state, opts) {
		opts = opts || {};
		var cartStep = opts.cartStep === true;
		ensureDiscountDefaults(state);
		var cfg = getStepFourConfig();
		var layout = cfg.discount_layout && typeof cfg.discount_layout === 'object' ? cfg.discount_layout : {};
		var placement = trimNonEmpty(layout.placement) || 'step_4';
		var separateStepEnabled = layout.separate_step_enabled === true;
		var order = Array.isArray(layout.order) ? layout.order : ['coupon'];
		var filtered = [];
		for (var oi = 0; oi < order.length; oi += 1) {
			if (String(order[oi] || '') === 'coupon') {
				filtered.push('coupon');
			}
		}
		if (!filtered.length) {
			filtered = ['coupon'];
		}
		if (!cartStep && placement !== 'step_4' && separateStepEnabled) {
			return '';
		}
		var html = '<section class="mp-cc-discount-tools" data-coupon-step-ready="' + (separateStepEnabled ? '1' : '0') + '" data-coupon-placement="' + escapeHtml(placement) + '"' + (cartStep ? ' data-discount-on-cart="1"' : '') + '>';
		for (var i = 0; i < filtered.length; i += 1) {
			if (filtered[i] === 'coupon') {
				html += buildCouponBlockHtml(state, opts);
			}
		}
		html += '</section>';
		return html;
	}

	function buildCouponBlockHtml(state, opts) {
		opts = opts || {};
		var cartStep = opts.cartStep === true;
		var inSummary = opts.inSummary === true;
		var couponInputId = inSummary ? 'mp-cc-coupon-code-summary' : (cartStep ? 'mp-cc-coupon-code-cart' : 'mp-cc-coupon-code');
		var copy = getCouponCopy();
		var cfg = getStepFourConfig();
		var styles = cfg.discount_block_styles || {};
		var rt = state.frontendStore && state.frontendStore.discounts && state.frontendStore.discounts.coupon_runtime
			? state.frontendStore.discounts.coupon_runtime
			: { code: '', state: 'empty', message: '' };
		var cartSummary = state.frontendStore && state.frontendStore.cart && state.frontendStore.cart.summary && typeof state.frontendStore.cart.summary === 'object'
			? state.frontendStore.cart.summary
			: {};
		var appliedCoupons = Array.isArray(cartSummary.applied_coupons) && cartSummary.applied_coupons.length
			? cartSummary.applied_coupons
			: (state.frontendStore && state.frontendStore.discounts && Array.isArray(state.frontendStore.discounts.coupons) ? state.frontendStore.discounts.coupons : []);
		var appliedGiftCards = Array.isArray(cartSummary.applied_gift_cards) ? cartSummary.applied_gift_cards : [];
		var hideGiftInCoupon = shouldHideGiftChipsInCouponBlock(state, opts);
		var allowGiftRm = isGiftCardRemoveAllowed();
		var allowCouponRm = isCouponRemoveAllowed();
		var code = String(rt.code || '');
		var runtimeState = String(rt.state || 'empty');
		var msg = trimNonEmpty(rt.message);
		var html = '';
		var stateClass = runtimeState === 'success' ? String(styles.state_success || 'success') : (runtimeState === 'error' ? String(styles.state_error || 'error') : String(styles.state_empty || 'default'));
		html += '<article class="mp-cc-coupon mp-cc-coupon--' + escapeHtml(stateClass) + (inSummary ? ' mp-cc-coupon--in-summary' : '') + '" data-coupon-block="1">';
		html += '<h4 class="mp-cc-coupon__title">' + escapeHtml(copy.title) + '</h4>';
		if (copy.intro) {
			html += '<p class="mp-cc-coupon__intro">' + escapeHtml(copy.intro) + '</p>';
		}
		html += '<div class="mp-cc-coupon__row">';
		html += '<label class="mp-cc-field-label" for="' + escapeHtml(couponInputId) + '">' + escapeHtml(copy.inputLabel) + '</label>';
		html += '<input type="text" class="mp-cc-input' + (runtimeState === 'error' ? ' is-invalid' : '') + '" id="' + escapeHtml(couponInputId) + '" data-coupon-code="1" value="' + escapeHtml(code) + '" placeholder="' + escapeHtml(copy.placeholder) + '" />';
		html += '<button type="button" class="mp-cc-nav__btn mp-cc-nav__btn--next mp-cc-coupon__apply" data-coupon-apply="1">' + escapeHtml(copy.applyLabel) + '</button>';
		html += '</div>';
		if (msg) {
			html += '<p class="mp-cc-field-hint' + (runtimeState === 'error' ? ' mp-cc-field-error' : '') + '" data-coupon-message="1">' + escapeHtml(msg) + '</p>';
		}
		if (appliedCoupons.length) {
			html += '<div class="mp-cc-coupon__applied" data-coupon-list="1">';
			for (var i = 0; i < appliedCoupons.length; i += 1) {
				var cp = String(appliedCoupons[i] || '');
				if (!cp) {
					continue;
				}
				html += '<span class="mp-cc-coupon__chip">';
				html += '<span>' + escapeHtml(cp) + '</span>';
				if (allowCouponRm) {
					html += '<button type="button" class="mp-cc-coupon__chip-remove" data-coupon-remove="1" data-code="' + escapeHtml(cp) + '" aria-label="' + escapeHtml(getUiText('step_4.coupon_remove', 'Снять купон')) + '">×</button>';
				}
				html += '</span>';
			}
			html += '</div>';
		}
		if (!hideGiftInCoupon && appliedGiftCards.length) {
			html += '<div class="mp-cc-coupon__applied mp-cc-coupon__applied--gift" data-gift-card-list="1">';
			for (var gi = 0; gi < appliedGiftCards.length; gi += 1) {
				var gc = String(appliedGiftCards[gi] || '');
				if (!gc) {
					continue;
				}
				html += '<span class="mp-cc-coupon__chip mp-cc-coupon__chip--gift">';
				html += '<span>' + escapeHtml(gc) + '</span>';
				if (allowGiftRm) {
					html += '<button type="button" class="mp-cc-coupon__chip-remove" data-gift-card-remove="1" data-code="' + escapeHtml(gc) + '" aria-label="' + escapeHtml(getUiText('step_4.gift_card_remove', 'Снять подарочную карту')) + '">×</button>';
				}
				html += '</span>';
			}
			html += '</div>';
		}
		html += '</article>';
		return html;
	}

	function getStepThreeTitle() {
		var config = getStepThreeConfig();
		var title = config && config.copy && config.copy.title ? String(config.copy.title) : '';
		return title || getUiText('step_3.title', 'Выберите дату получения');
	}

	function getStepThreeHelperByScenario(scenario) {
		var config = getStepThreeConfig();
		var map = config && config.copy && config.copy.helper_by_scenario && typeof config.copy.helper_by_scenario === 'object'
			? config.copy.helper_by_scenario
			: {};
		var value = map[scenario] ? String(map[scenario]) : '';
		return value || getUiText('step_3.date_helper', 'Выберите дату из доступных слотов.');
	}

	function getStepThreeErrorCopy(key, fallback) {
		var config = getStepThreeConfig();
		var errors = config && config.copy && config.copy.errors && typeof config.copy.errors === 'object'
			? config.copy.errors
			: {};
		var value = errors[key] ? String(errors[key]) : '';
		return value || fallback;
	}

	function getStepThreeCalendarStyle() {
		var config = getStepThreeConfig();
		var style = config && config.calendar_style && typeof config.calendar_style === 'object' ? config.calendar_style : {};
		return {
			density: String(style.density || 'comfortable'),
			dayShape: String(style.day_shape || 'rounded'),
			highlightStyle: String(style.highlight_style || 'accent'),
			showWeekendTint: style.show_weekend_tint !== false
		};
	}

	function trimNonEmpty(value) {
		var s = String(value || '').trim();
		return s ? s : '';
	}

	function getConditionsCopyRoot() {
		var cfg = getStepThreeConfig();
		return cfg && cfg.conditions_copy && typeof cfg.conditions_copy === 'object' ? cfg.conditions_copy : {};
	}

	function getConditionsBlockForScenario(scenario) {
		var root = getConditionsCopyRoot();
		var key = String(scenario || '');
		if (!key || !root[key] || typeof root[key] !== 'object') {
			return {};
		}
		return root[key];
	}

	function getPickupPointForConditions(state) {
		var scenarioData = state.frontendStore && state.frontendStore.fulfillment ? (state.frontendStore.fulfillment.scenarioData || {}) : {};
		var point = scenarioData.pickup_point && typeof scenarioData.pickup_point === 'object' ? scenarioData.pickup_point : null;
		if (!point) {
			point = getPickupPointById('');
		}
		return point;
	}

	function getPickupMapConfig() {
		var pickupCfg = getPickupConfig();
		var mapCfg = pickupCfg && pickupCfg.mapWidget ? pickupCfg.mapWidget : {};
		return {
			enabled: mapCfg.enabled !== false,
			provider: String(mapCfg.provider || 'yandex'),
			apiKey: String(mapCfg.api_key || ''),
			centerLat: Number(mapCfg.center_lat || 56.010563),
			centerLng: Number(mapCfg.center_lng || 92.852572),
			zoom: Math.max(2, Math.min(19, Number(mapCfg.zoom || 14))),
			markerLabel: trimNonEmpty(mapCfg.marker_label) || 'Пункт самовывоза',
			markerHint: trimNonEmpty(mapCfg.marker_hint) || 'Заберите заказ в рабочие часы.',
			fallbackTitle: trimNonEmpty(mapCfg.fallback_title) || 'Карта временно недоступна',
			fallbackMessage: trimNonEmpty(mapCfg.fallback_message) || 'Посмотрите адрес пункта самовывоза выше и постройте маршрут в приложении карт.',
			desktopHeight: Math.max(160, Math.min(520, Number(mapCfg.desktop_height || 250))),
			mobileHeight: Math.max(120, Math.min(420, Number(mapCfg.mobile_height || 190))),
			diagnosticsEnabled: mapCfg.diagnostics_enabled !== false
		};
	}

	function buildPickupMapHtml(point, sourceLabel) {
		var cfg = getPickupMapConfig();
		if (!cfg.enabled || cfg.provider !== 'yandex') {
			return '';
		}
		var lat = Number(cfg.centerLat);
		var lng = Number(cfg.centerLng);
		if (point && point.lat && point.lng) {
			lat = Number(point.lat);
			lng = Number(point.lng);
		}
		var markerLabel = point && point.title ? String(point.title) : cfg.markerLabel;
		var markerHint = point && point.address ? String(point.address) : cfg.markerHint;
		var html = '';
		html += '<section class="mp-cc-pickup-map" data-pickup-map-root="1"';
		html += ' data-map-source="' + escapeHtml(String(sourceLabel || 'pickup')) + '"';
		html += ' data-map-lat="' + escapeHtml(String(lat)) + '"';
		html += ' data-map-lng="' + escapeHtml(String(lng)) + '"';
		html += ' data-map-zoom="' + escapeHtml(String(cfg.zoom)) + '"';
		html += ' data-map-marker-label="' + escapeHtml(markerLabel) + '"';
		html += ' data-map-marker-hint="' + escapeHtml(markerHint) + '"';
		html += ' data-map-fallback-title="' + escapeHtml(cfg.fallbackTitle) + '"';
		html += ' data-map-fallback-message="' + escapeHtml(cfg.fallbackMessage) + '"';
		html += ' data-map-height-desktop="' + escapeHtml(String(cfg.desktopHeight)) + '"';
		html += ' data-map-height-mobile="' + escapeHtml(String(cfg.mobileHeight)) + '"';
		html += '>';
		html += '<div class="mp-cc-pickup-map__canvas" data-pickup-map-canvas="1" aria-label="' + escapeHtml(markerLabel) + '"></div>';
		html += '<div class="mp-cc-pickup-map__fallback" data-pickup-map-fallback="1" hidden>';
		html += '<p class="mp-cc-pickup-map__fallback-title">' + escapeHtml(cfg.fallbackTitle) + '</p>';
		html += '<p class="mp-cc-pickup-map__fallback-message">' + escapeHtml(cfg.fallbackMessage) + '</p>';
		html += '</div>';
		html += '</section>';
		return html;
	}

	function buildConditionsReceiptPlainText(state) {
		var scenario = normalizeScenarioId(state.frontendStore.fulfillment.scenario || '');
		var root = getConditionsCopyRoot();
		var introMap = root.intro_by_scenario && typeof root.intro_by_scenario === 'object' ? root.intro_by_scenario : {};
		var lines = [];
		var intro = trimNonEmpty(introMap[scenario]) || getUiText('step_3.conditions_intro', 'Условия для выбранного способа получения.');
		if (intro) {
			lines.push(intro);
		}
		var block = getConditionsBlockForScenario(scenario);
		var i;
		if (scenario === 'krasnoyarsk_delivery') {
			var krBody = trimNonEmpty(block.body) || getUiText('step_3.krasnoyarsk_conditions', 'Доставка выполняется в пределах города в выбранную дату. Курьер связывается заранее для подтверждения интервала.');
			var krDay = trimNonEmpty(block.delivery_within_day) || getUiText('step_3.krasnoyarsk_delivery_day', 'Доставка в течение дня в выбранную дату. Интервал уточняется у курьера.');
			lines.push(krBody, krDay);
		} else if (scenario === 'other_city_delivery') {
			var ocBody = trimNonEmpty(block.body) || getUiText('step_3.other_city_conditions', 'Срок и стоимость уточняются после подтверждения заказа. Отправка выполняется через транспортного партнера по согласованным данным.');
			var ocLog = trimNonEmpty(block.logistics_note) || getUiText('step_3.other_city_logistics', 'Отправка выполняется через логистическую компанию после комплектации и согласования реквизитов.');
			lines.push(ocBody, ocLog);
		} else {
			var puBody = trimNonEmpty(block.body) || getUiText('step_3.pickup_conditions', 'Заказ выдается в точке самовывоза после подтверждения готовности. Пожалуйста, дождитесь уведомления перед визитом.');
			lines.push(puBody);
			var officeTitle = trimNonEmpty(block.office_block_title) || getUiText('step_3.pickup_office_block_title', 'Офис и график работы');
			lines.push(officeTitle);
			var point = getPickupPointForConditions(state);
			if (point && point.title) {
				lines.push(String(point.title));
			}
			var address = trimNonEmpty(block.office_address);
			if (!address && point && point.address) {
				address = String(point.address);
			}
			if (address) {
				lines.push(address);
			}
			var desc = trimNonEmpty(block.office_description);
			if (!desc && point && point.description) {
				desc = String(point.description);
			}
			if (!desc) {
				desc = getUiText('step_3.pickup_office_default', 'Выдача заказа в офисе самовывоза после уведомления о готовности.');
			}
			lines.push(desc);
			var plain = trimNonEmpty(block.office_hours_plain);
			var hoursLabel = getUiText('step_3.pickup_hours_label', 'Часы выдачи');
			if (plain) {
				var scheduleLines = plain.split(/\r?\n/).map(function (ln) {
					return trimNonEmpty(ln);
				}).filter(Boolean);
				lines.push(hoursLabel + ':\n' + scheduleLines.join('\n'));
			} else {
				var hours = Array.isArray(block.office_hours) ? block.office_hours : [];
				var hoursClean = [];
				for (i = 0; i < hours.length; i += 1) {
					var slot = trimNonEmpty(hours[i]);
					if (slot) {
						hoursClean.push(slot);
					}
				}
				if (!hoursClean.length) {
					hoursClean = ['10:00–13:00', '13:00–17:00', '17:00–20:00'];
				}
				lines.push(hoursLabel + ': ' + hoursClean.join(', '));
			}
			var critical = trimNonEmpty(block.critical_notice);
			if (critical) {
				lines.push(getUiText('step_3.critical_notice_prefix', 'Важно:') + ' ' + critical);
			}
			var helper = trimNonEmpty(block.convenience_helper) || getUiText('step_3.pickup_convenience_helper', 'Можно приехать в удобное время в рамках указанного расписания — уточните готовность заказа по уведомлению.');
			lines.push(helper);
		}
		var filtered = [];
		for (i = 0; i < lines.length; i += 1) {
			var line = trimNonEmpty(lines[i]);
			if (line) {
				filtered.push(lines[i]);
			}
		}
		return filtered.join('\n\n');
	}

	function formatConditionsReceiptHtmlFromPlain(text) {
		if (!trimNonEmpty(text)) {
			return '';
		}
		var paras = String(text).split(/\n\n+/);
		var out = '';
		var pi;
		for (pi = 0; pi < paras.length; pi += 1) {
			var p = trimNonEmpty(paras[pi]);
			if (!p) {
				continue;
			}
			var inner = escapeHtml(p).replace(/\n/g, '<br />');
			out += '<p class="mp-cc-summary-card__conditions-para">' + inner + '</p>';
		}
		return out;
	}

	function getConditionsIntroForScenario(scenario) {
		var root = getConditionsCopyRoot();
		var map = root.intro_by_scenario && typeof root.intro_by_scenario === 'object' ? root.intro_by_scenario : {};
		var custom = trimNonEmpty(map[scenario]);
		if (custom) {
			return custom;
		}
		return getUiText('step_3.conditions_intro', 'Условия для выбранного способа получения.');
	}

	function getConditionsSecondaryNotesList() {
		var root = getConditionsCopyRoot();
		var fromConfig = Array.isArray(root.secondary_notes) ? root.secondary_notes : [];
		var filtered = [];
		var i;
		for (i = 0; i < fromConfig.length; i += 1) {
			var line = trimNonEmpty(fromConfig[i]);
			if (line) {
				filtered.push(line);
			}
		}
		if (filtered.length) {
			return filtered;
		}
		return [
			getUiText('step_3.secondary_note_1', 'Проверяйте корректность телефона: статус заказа приходит в уведомления.'),
			getUiText('step_3.secondary_note_2', 'При изменении сценария условия и доступность дат обновляются автоматически.'),
			getUiText('step_3.secondary_note_3', 'Для вопросов по срокам и логистике используйте контакты поддержки магазина.')
		];
	}

	function getConditionsCardTitle(scenario, block, rules) {
		var custom = trimNonEmpty(block.title);
		if (custom) {
			return custom;
		}
		var cr = rules.copy_rules && typeof rules.copy_rules === 'object' ? rules.copy_rules : {};
		if (trimNonEmpty(cr.conditions_title)) {
			return trimNonEmpty(cr.conditions_title);
		}
		if (trimNonEmpty(rules.label)) {
			return trimNonEmpty(rules.label);
		}
		if (scenario === 'krasnoyarsk_delivery') {
			return getUiText('step_3.krasnoyarsk_title', 'Доставка по Красноярску');
		}
		if (scenario === 'other_city_delivery') {
			return getUiText('step_3.other_city_title', 'Доставка в другой город');
		}
		return getUiText('step_3.pickup_title', 'Самовывоз');
	}

	function getConditionsStepPanelTitle(state) {
		var scenario = normalizeScenarioId(state.frontendStore.fulfillment.scenario || '');
		var block = getConditionsBlockForScenario(scenario);
		var rules = getScenarioRulesById(scenario);
		return getConditionsCardTitle(scenario, block, rules);
	}

	function getPickupPointForConditions(state) {
		var scenarioData = state.frontendStore && state.frontendStore.fulfillment ? (state.frontendStore.fulfillment.scenarioData || {}) : {};
		var point = scenarioData.pickup_point && typeof scenarioData.pickup_point === 'object' ? scenarioData.pickup_point : null;
		if (!point) {
			point = getPickupPointById('');
		}
		return point;
	}

	function buildPickupOfficeBlockHtml(state, block) {
		var point = getPickupPointForConditions(state);
		var officeTitle = trimNonEmpty(block.office_block_title) || getUiText('step_3.pickup_office_block_title', 'Офис и график работы');
		var address = trimNonEmpty(block.office_address);
		if (!address && point && point.address) {
			address = String(point.address);
		}
		var pointName = point && point.title ? String(point.title) : '';
		var desc = trimNonEmpty(block.office_description);
		if (!desc && point && point.description) {
			desc = String(point.description);
		}
		if (!desc) {
			desc = getUiText('step_3.pickup_office_default', 'Выдача заказа в офисе самовывоза после уведомления о готовности.');
		}
		var plain = trimNonEmpty(block.office_hours_plain);
		var hours = Array.isArray(block.office_hours) ? block.office_hours : [];
		var hoursClean = [];
		var i;
		for (i = 0; i < hours.length; i += 1) {
			var slot = trimNonEmpty(hours[i]);
			if (slot) {
				hoursClean.push(slot);
			}
		}
		if (!plain && !hoursClean.length) {
			hoursClean = ['10:00–13:00', '13:00–17:00', '17:00–20:00'];
		}
		var helper = trimNonEmpty(block.convenience_helper) || getUiText('step_3.pickup_convenience_helper', 'Можно приехать в удобное время в рамках указанного расписания — уточните готовность заказа по уведомлению.');
		var critical = trimNonEmpty(block.critical_notice);
		var showMulti = block.show_multi_office_slot !== false;

		var html = '';
		html += '<div class="mp-cc-pickup-office">';
		html += '<div class="mp-cc-pickup-office__head">';
		html += '<h3 class="mp-cc-pickup-office__heading">' + escapeHtml(officeTitle) + '</h3>';
		html += '</div>';
		if (pointName) {
			html += '<p class="mp-cc-pickup-office__point-name">' + escapeHtml(pointName) + '</p>';
		}
		if (address) {
			html += '<p class="mp-cc-pickup-office__address">' + escapeHtml(address) + '</p>';
		}
		html += '<p class="mp-cc-pickup-office__description">' + escapeHtml(desc) + '</p>';
		html += '<div class="mp-cc-pickup-office__hours" aria-label="' + escapeHtml(getUiText('step_3.pickup_hours_label', 'Часы выдачи')) + '">';
		if (plain) {
			html += '<div class="mp-cc-pickup-office__schedule mp-cc-pickup-office__schedule--plain">';
			var lines = plain.split(/\r?\n/);
			var firstLine = true;
			for (i = 0; i < lines.length; i += 1) {
				var line = trimNonEmpty(lines[i]);
				if (!line) {
					continue;
				}
				html += '<p class="mp-cc-pickup-office__line' + (firstLine ? ' mp-cc-pickup-office__line--key' : '') + '">' + escapeHtml(line) + '</p>';
				firstLine = false;
			}
			html += '</div>';
		} else {
			html += '<div class="mp-cc-pickup-office__schedule mp-cc-pickup-office__schedule--chips">';
			html += '<div class="mp-cc-pickup-office__chips" role="list">';
			for (i = 0; i < hoursClean.length; i += 1) {
				html += '<span class="mp-cc-pickup-office__chip' + (i === 0 ? ' mp-cc-pickup-office__chip--key' : '') + '" role="listitem">' + escapeHtml(hoursClean[i]) + '</span>';
			}
			html += '</div>';
			html += '</div>';
		}
		html += '</div>';
		if (critical) {
			html += '<div class="mp-cc-pickup-office__critical" role="note">';
			html += '<span class="mp-cc-pickup-office__critical-icon" aria-hidden="true">!</span>';
			html += '<p class="mp-cc-pickup-office__critical-text">' + escapeHtml(critical) + '</p>';
			html += '</div>';
		}
		html += '<p class="mp-cc-pickup-office__helper">' + escapeHtml(helper) + '</p>';
		html += buildPickupMapHtml(point, 'conditions');
		if (showMulti) {
			html += '<div class="mp-cc-pickup-office__multi-slot" data-mp-cc-multi-office="1">';
			html += '<span class="mp-cc-pickup-office__multi-slot-label">' + escapeHtml(getUiText('step_3.pickup_multi_office_hint', 'Дополнительные точки самовывоза будут отображаться здесь при подключении.')) + '</span>';
			html += '</div>';
		}
		html += '</div>';
		return html;
	}

	function buildConditionsStepHtml(state) {
		var scenario = normalizeScenarioId(state.frontendStore.fulfillment.scenario || '');
		var block = getConditionsBlockForScenario(scenario);
		var scenarioRules = getScenarioRulesById(scenario);
		var cardTitle = getConditionsCardTitle(scenario, block, scenarioRules);
		var intro = getConditionsIntroForScenario(scenario);
		var notes = getConditionsSecondaryNotesList();
		var html = '';
		var i;

		html += '<section class="mp-cc-conditions-step" data-mp-cc-conditions-scenario="' + escapeHtml(scenario) + '" aria-label="' + escapeHtml(cardTitle) + '">';
		html += '<p class="mp-cc-conditions-step__intro">' + escapeHtml(intro) + '</p>';
		html += '<div class="mp-cc-conditions-step__grid mp-cc-conditions-step__grid--single">';
		html += '<article class="mp-cc-conditions-card is-active" data-conditions-scenario="' + escapeHtml(scenario) + '">';
		if (scenario === 'krasnoyarsk_delivery') {
			var krBody = trimNonEmpty(block.body) || getUiText('step_3.krasnoyarsk_conditions', 'Доставка выполняется в пределах города в выбранную дату. Курьер связывается заранее для подтверждения интервала.');
			var krDay = trimNonEmpty(block.delivery_within_day) || getUiText('step_3.krasnoyarsk_delivery_day', 'Доставка в течение дня в выбранную дату. Интервал уточняется у курьера.');
			html += '<p class="mp-cc-conditions-card__text">' + escapeHtml(krBody) + '</p>';
			html += '<p class="mp-cc-conditions-card__text mp-cc-conditions-card__accent">' + escapeHtml(krDay) + '</p>';
		} else if (scenario === 'other_city_delivery') {
			var ocBody = trimNonEmpty(block.body) || getUiText('step_3.other_city_conditions', 'Срок и стоимость уточняются после подтверждения заказа. Отправка выполняется через транспортного партнера по согласованным данным.');
			var ocLog = trimNonEmpty(block.logistics_note) || getUiText('step_3.other_city_logistics', 'Отправка выполняется через логистическую компанию после комплектации и согласования реквизитов.');
			html += '<p class="mp-cc-conditions-card__text">' + escapeHtml(ocBody) + '</p>';
			html += '<p class="mp-cc-conditions-card__text mp-cc-conditions-card__accent">' + escapeHtml(ocLog) + '</p>';
		} else {
			var puBody = trimNonEmpty(block.body) || getUiText('step_3.pickup_conditions', 'Заказ выдается в точке самовывоза после подтверждения готовности. Пожалуйста, дождитесь уведомления перед визитом.');
			html += '<p class="mp-cc-conditions-card__text">' + escapeHtml(puBody) + '</p>';
			html += buildPickupOfficeBlockHtml(state, block);
		}
		html += '</article>';
		html += '</div>';
		html += '<aside class="mp-cc-conditions-step__notes" aria-label="' + escapeHtml(getUiText('step_3.notes_title', 'Важные замечания')) + '">';
		html += '<h5 class="mp-cc-conditions-step__notes-title">' + escapeHtml(getUiText('step_3.notes_title', 'Важные замечания')) + '</h5>';
		html += '<ul class="mp-cc-conditions-step__notes-list">';
		for (i = 0; i < notes.length; i += 1) {
			if (!notes[i]) {
				continue;
			}
			html += '<li>' + escapeHtml(notes[i]) + '</li>';
		}
		html += '</ul>';
		html += '</aside>';
		html += '</section>';

		return html;
	}

	function getPickupPointById(pointId) {
		var pickup = getPickupConfig();
		var points = pickup.points || [];
		var safeId = String(pointId || '');
		var i;
		for (i = 0; i < points.length; i += 1) {
			if (String(points[i].id || '') === safeId) {
				return points[i];
			}
		}
		return points.length ? points[0] : null;
	}

	function normalizeScenarioId(scenarioId) {
		var map = getScenarioMap();
		var scenarios = map.scenarios || {};
		var key = String(scenarioId || '');
		if (key && Object.prototype.hasOwnProperty.call(scenarios, key)) {
			return key;
		}
		var config = getScenarioUiConfig();
		var fallback = String(config.default_scenario || 'pickup');
		return (fallback && Object.prototype.hasOwnProperty.call(scenarios, fallback)) ? fallback : 'pickup';
	}

	function getScenarioRulesById(scenarioId) {
		var map = getScenarioMap();
		var rulesMap = map.rules || {};
		var normalized = normalizeScenarioId(scenarioId);
		return rulesMap[normalized] && typeof rulesMap[normalized] === 'object' ? rulesMap[normalized] : {};
	}

	function startOfDay(date) {
		var value = new Date(date.getTime());
		value.setHours(0, 0, 0, 0);
		return value;
	}

	function addDays(date, days) {
		var value = new Date(date.getTime());
		value.setDate(value.getDate() + Number(days || 0));
		return value;
	}

	function isoDate(date) {
		return [
			date.getFullYear(),
			String(date.getMonth() + 1).padStart(2, '0'),
			String(date.getDate()).padStart(2, '0')
		].join('-');
	}

	function parseIsoDate(value) {
		var raw = String(value || '');
		var matched = raw.match(/^(\d{4})-(\d{2})-(\d{2})$/);
		if (!matched) {
			return null;
		}
		var year = Number(matched[1]);
		var month = Number(matched[2]) - 1;
		var day = Number(matched[3]);
		var date = new Date(year, month, day);
		if (date.getFullYear() !== year || date.getMonth() !== month || date.getDate() !== day) {
			return null;
		}
		return startOfDay(date);
	}

	function formatIsoDateForUi(value) {
		var date = parseIsoDate(value);
		if (!date) {
			return String(value || '');
		}
		return date.toLocaleDateString('ru-RU', { day: '2-digit', month: '2-digit', year: 'numeric' });
	}

	function monthKeyFromDate(date) {
		return [date.getFullYear(), String(date.getMonth() + 1).padStart(2, '0')].join('-');
	}

	function parseMonthKey(value) {
		var raw = String(value || '');
		var matched = raw.match(/^(\d{4})-(\d{2})$/);
		if (!matched) {
			return null;
		}
		var year = Number(matched[1]);
		var month = Number(matched[2]) - 1;
		if (!Number.isFinite(year) || !Number.isFinite(month) || month < 0 || month > 11) {
			return null;
		}
		return new Date(year, month, 1);
	}

	function buildDateCalendarModel(state) {
		var scenario = normalizeScenarioId(state.frontendStore.fulfillment.scenario || '');
		var rules = getScenarioRulesById(scenario);
		var dateRules = rules.date_rules && typeof rules.date_rules === 'object' ? rules.date_rules : {};
		var leadTime = Math.max(1, Number(dateRules.lead_time_days || 1));
		var maxDays = Math.max(1, Number(dateRules.max_days_ahead || 14));
		var allowWeekends = dateRules.allow_weekends !== false;
		var today = startOfDay(new Date());
		var minByRules = parseIsoDate(dateRules.min_date || '');
		var maxByRules = parseIsoDate(dateRules.max_date || '');
		var earliest = minByRules || addDays(today, leadTime);
		var latest = maxByRules || addDays(today, maxDays);
		if (earliest < addDays(today, 1)) {
			earliest = addDays(today, 1);
		}
		if (latest < earliest) {
			latest = earliest;
		}
		var allowedWeekdays = Array.isArray(dateRules.allowed_weekdays) ? dateRules.allowed_weekdays.map(function (value) {
			return Number(value);
		}).filter(function (value) {
			return Number.isFinite(value) && value >= 0 && value <= 6;
		}) : [];
		if (!allowedWeekdays.length) {
			allowedWeekdays = allowWeekends ? [0, 1, 2, 3, 4, 5, 6] : [1, 2, 3, 4, 5];
		}
		var blockedDates = Array.isArray(dateRules.blocked_dates) ? dateRules.blocked_dates : [];
		var blockedMap = {};
		for (var b = 0; b < blockedDates.length; b += 1) {
			blockedMap[String(blockedDates[b] || '')] = true;
		}
		var availableDates = Array.isArray(dateRules.available_dates) ? dateRules.available_dates : [];
		var availableMap = {};
		for (var a = 0; a < availableDates.length; a += 1) {
			availableMap[String(availableDates[a] || '')] = true;
		}
		var hasServerAvailability = availableDates.length > 0;
		var firstAvailable = null;
		var pointer = new Date(earliest.getTime());
		while (pointer <= latest) {
			var pointerIso = isoDate(pointer);
			var pointerWeekday = pointer.getDay();
			var allowedByWeekday = allowedWeekdays.indexOf(pointerWeekday) > -1;
			var allowedByServer = !hasServerAvailability || Boolean(availableMap[pointerIso]);
			var allowedByBlocked = !blockedMap[pointerIso];
			if (allowedByWeekday && allowedByServer && allowedByBlocked) {
				firstAvailable = new Date(pointer.getTime());
				break;
			}
			pointer = addDays(pointer, 1);
		}
		var hasAnyAvailable = Boolean(firstAvailable);
		if (!firstAvailable) {
			firstAvailable = new Date(earliest.getTime());
		}
		var earliestMonth = new Date(earliest.getFullYear(), earliest.getMonth(), 1);
		var latestMonth = new Date(latest.getFullYear(), latest.getMonth(), 1);
		var dateStore = state.frontendStore && state.frontendStore.fulfillment && state.frontendStore.fulfillment.date
			? state.frontendStore.fulfillment.date
			: {};
		var preferredMonth = parseMonthKey(dateStore.calendar_month || '');
		var monthStart = preferredMonth ? preferredMonth : new Date(earliestMonth.getTime());
		if (monthStart < earliestMonth) {
			monthStart = new Date(earliestMonth.getTime());
		}
		if (monthStart > latestMonth) {
			monthStart = new Date(latestMonth.getTime());
		}
		var monthLabel = monthStart.toLocaleDateString('ru-RU', { month: 'long', year: 'numeric' });
		var selectedDate = String(dateStore.selected_date || '');
		var selected = parseIsoDate(selectedDate);
		if (!selected || selected < earliest || selected > latest || allowedWeekdays.indexOf(selected.getDay()) === -1 || blockedMap[selectedDate] || (hasServerAvailability && !availableMap[selectedDate])) {
			selected = firstAvailable;
			selectedDate = isoDate(firstAvailable);
		}
		if (!hasAnyAvailable) {
			selectedDate = '';
		}

		var gridStart = addDays(monthStart, -((monthStart.getDay() + 6) % 7));
		var days = [];
		var i;
		for (i = 0; i < 42; i += 1) {
			var date = addDays(gridStart, i);
			var inMonth = date.getMonth() === monthStart.getMonth();
			var weekend = date.getDay() === 0 || date.getDay() === 6;
			var value = isoDate(date);
			var outOfRange = date < earliest || date > latest;
			var blockedByManualDate = Boolean(blockedMap[value]);
			var allowedByWeekdayDate = allowedWeekdays.indexOf(date.getDay()) > -1;
			var allowedByServerDate = !hasServerAvailability || Boolean(availableMap[value]);
			var disabled = outOfRange || blockedByManualDate || !allowedByWeekdayDate || !allowedByServerDate;
			days.push({
				value: value,
				label: date.toLocaleDateString('ru-RU', { day: 'numeric', month: 'short' }),
				dayOfMonth: date.getDate(),
				inMonth: inMonth,
				disabled: disabled,
				selected: value === selectedDate,
				weekend: weekend
			});
		}

		var helper = getStepThreeHelperByScenario(scenario);
		return {
			scenario: scenario,
			selectedDate: selectedDate,
			hasAnyAvailable: hasAnyAvailable,
			monthLabel: monthLabel,
			monthKey: monthKeyFromDate(monthStart),
			minMonthKey: monthKeyFromDate(earliestMonth),
			maxMonthKey: monthKeyFromDate(latestMonth),
			canGoPrevMonth: monthStart > earliestMonth,
			canGoNextMonth: monthStart < latestMonth,
			days: days,
			helper: helper
		};
	}

	function buildDateCalendarHtml(state) {
		var model = buildDateCalendarModel(state);
		var style = getStepThreeCalendarStyle();
		var weekdays = ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'];
		var formErrors = state.frontendStore && state.frontendStore.form && state.frontendStore.form.errors
			? state.frontendStore.form.errors
			: {};
		var dateError = formErrors.date || '';
		var html = '';
		var i;

		html += '<section class="mp-cc-date-step mp-cc-date-step--' + escapeHtml(style.density) + ' mp-cc-date-step--shape-' + escapeHtml(style.dayShape) + ' mp-cc-date-step--highlight-' + escapeHtml(style.highlightStyle) + (style.showWeekendTint ? ' mp-cc-date-step--weekend-tint' : '') + '" aria-labelledby="mp-cc-date-title">';
		html += '<header class="mp-cc-date-step__header">';
		html += '<h4 class="mp-cc-date-step__title" id="mp-cc-date-title">' + escapeHtml(getStepThreeTitle()) + '</h4>';
		html += '<div class="mp-cc-date-step__month-nav">';
		html += '<button type="button" class="mp-cc-date-step__month-btn" data-calendar-nav="-1" aria-label="' + escapeHtml(getUiText('step_3.prev_month', 'Предыдущий месяц')) + '"' + (model.canGoPrevMonth ? '' : ' disabled') + '>‹</button>';
		html += '<p class="mp-cc-date-step__month" aria-live="polite" data-calendar-month="' + escapeHtml(model.monthKey) + '">' + escapeHtml(model.monthLabel) + '</p>';
		html += '<button type="button" class="mp-cc-date-step__month-btn" data-calendar-nav="+1" aria-label="' + escapeHtml(getUiText('step_3.next_month', 'Следующий месяц')) + '"' + (model.canGoNextMonth ? '' : ' disabled') + '>›</button>';
		html += '</div>';
		html += '</header>';
		html += '<div class="mp-cc-calendar" role="group" aria-label="' + escapeHtml(getStepThreeTitle()) + '">';
		html += '<div class="mp-cc-calendar__weekdays" aria-hidden="true">';
		for (i = 0; i < weekdays.length; i += 1) {
			html += '<span class="mp-cc-calendar__weekday">' + escapeHtml(weekdays[i]) + '</span>';
		}
		html += '</div>';
		html += '<div class="mp-cc-calendar__grid" role="grid" aria-labelledby="mp-cc-date-title" data-calendar-grid="1">';
		var focusAssigned = false;
		for (i = 0; i < model.days.length; i += 1) {
			var day = model.days[i];
			var classes = ['mp-cc-calendar__day'];
			if (!day.inMonth) {
				classes.push('is-outside');
			}
			if (day.disabled) {
				classes.push('is-disabled');
			}
			if (day.selected) {
				classes.push('is-selected');
			}
			if (day.weekend) {
				classes.push('is-weekend');
			}
			var isFocusable = !day.disabled && (day.selected || !focusAssigned);
			var tabIndex = isFocusable ? '0' : '-1';
			if (isFocusable) {
				focusAssigned = true;
			}
			html += '<button type="button" class="' + classes.join(' ') + '"';
			html += ' role="gridcell"';
			html += ' data-calendar-date="' + escapeHtml(day.value) + '"';
			html += ' aria-label="' + escapeHtml(day.label) + '"';
			html += ' aria-selected="' + (day.selected ? 'true' : 'false') + '"';
			html += ' tabindex="' + tabIndex + '"';
			if (day.disabled) {
				html += ' disabled aria-disabled="true"';
			}
			html += '>';
			html += '<span>' + escapeHtml(day.dayOfMonth) + '</span>';
			html += '</button>';
		}
		html += '</div>';
		html += '</div>';
		if (dateError === 'required') {
			html += '<p class="mp-cc-date-step__helper is-error" id="mp-cc-date-helper" role="alert">' + escapeHtml(getStepThreeErrorCopy('empty_date', 'Выберите дату, чтобы продолжить.')) + '</p>';
		} else if (dateError === 'invalid') {
			html += '<p class="mp-cc-date-step__helper is-error" id="mp-cc-date-helper" role="alert">' + escapeHtml(getStepThreeErrorCopy('invalid_date', 'Выбранная дата недоступна. Обновите шаг и выберите другую дату.')) + '</p>';
		} else if (!model.hasAnyAvailable) {
			html += '<p class="mp-cc-date-step__helper" id="mp-cc-date-helper">' + escapeHtml(getStepThreeErrorCopy('invalid_date', 'Нет доступных дат. Выберите другой сценарий или свяжитесь с поддержкой.')) + '</p>';
		} else {
			html += '<p class="mp-cc-date-step__helper" id="mp-cc-date-helper">' + escapeHtml(model.helper) + '</p>';
		}
		html += '</section>';
		return html;
	}

	function parseContext() {
		var boot = document.getElementById('mp-cc-bootstrap-context');
		if (boot && boot.textContent) {
			try {
				var parsed = JSON.parse(boot.textContent);
				if (parsed && typeof parsed === 'object') {
					return parsed;
				}
			} catch (e1) {
				// fall through
			}
		}

		var localized = window.mpCcCheckout || {};
		if (localized.initialContext && typeof localized.initialContext === 'object') {
			var keys = Object.keys(localized.initialContext);
			if (keys.length) {
				return localized.initialContext;
			}
		}

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
		} catch (e2) {
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
		visible = normalizeVisibleCheckoutStepsOrder(visible);

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
			featureFlags: $.extend({}, contextFlags, localizedFlags),
			stepOneConfig: getStepOneConfig()
		};
	}

	function normalizeVisibleCheckoutStepsOrder(visibleSteps) {
		var steps = Array.isArray(visibleSteps) ? visibleSteps.slice() : [];
		if (!steps.length) {
			return steps;
		}
		var confirmIdx = -1;
		var recipientIdx = -1;
		var paymentIdx = -1;
		for (var i = 0; i < steps.length; i += 1) {
			var stepId = steps[i] && steps[i].id ? String(steps[i].id) : '';
			if (stepId === 'confirm') {
				confirmIdx = i;
			} else if (stepId === 'recipient') {
				recipientIdx = i;
			} else if (stepId === 'payment') {
				paymentIdx = i;
			}
		}
		if (confirmIdx < 0) {
			return steps;
		}
		var mustMove =
			(recipientIdx >= 0 && confirmIdx < recipientIdx) ||
			(paymentIdx >= 0 && confirmIdx < paymentIdx);
		if (!mustMove) {
			return steps;
		}
		var confirmStep = steps.splice(confirmIdx, 1)[0];
		steps.push(confirmStep);
		return steps;
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
				scenarioData: $.extend({}, answers.scenario || {}, { rules: flow.scenario_rules || {} })
			},
			discounts: answers.discounts || { coupons: [], gift_card: [] },
			payment: {
				gateway: paymentGateway || '',
				state: 'idle',
				fieldsHtml: '',
				fieldsGatewayId: '',
				fieldsHydration: 'pending',
				gatewayCompatIssue: ''
			},
			runtime: {
				loading: false,
				success: false,
				blocked: false,
				dirty: false,
				lastSyncAt: Date.now(),
				summaryHydrated: false,
				paymentSubmitting: false
			},
			meta: {
				contextId: flow.context_id || '',
				expiresAt: flow.expires_at || 0,
				lastSummarySignature: ''
			}
		};
	}

	function ensurePickupScenarioData(state) {
		if (!state || !state.frontendStore || !state.frontendStore.fulfillment) {
			return;
		}
		var scenario = normalizeScenarioId(state.frontendStore.fulfillment.scenario || 'pickup');
		if (scenario !== 'pickup') {
			return;
		}
		var existing = state.frontendStore.fulfillment.scenarioData || {};
		var pickupPoint = existing.pickup_point && typeof existing.pickup_point === 'object' ? existing.pickup_point : null;
		if (!pickupPoint) {
			var fallback = getPickupPointById('');
			if (fallback) {
				existing.pickup_point = fallback;
				state.frontendStore.fulfillment.scenarioData = existing;
			}
		}
	}

	function ensureDateSelection(state) {
		if (!state || !state.frontendStore || !state.frontendStore.fulfillment) {
			return;
		}
		var dateBox = state.frontendStore.fulfillment.date && typeof state.frontendStore.fulfillment.date === 'object'
			? state.frontendStore.fulfillment.date
			: {};
		if (dateBox.selected_date) {
			if (!dateBox.calendar_month) {
				var parsedDate = parseIsoDate(dateBox.selected_date);
				if (parsedDate) {
					dateBox.calendar_month = monthKeyFromDate(parsedDate);
					state.frontendStore.fulfillment.date = dateBox;
				}
			}
			return;
		}
		var model = buildDateCalendarModel(state);
		if (!model || !model.selectedDate || !model.hasAnyAvailable) {
			return;
		}
		dateBox.selected_date = model.selectedDate;
		if (!dateBox.calendar_month) {
			dateBox.calendar_month = model.monthKey;
		}
		state.frontendStore.fulfillment.date = dateBox;
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
					total: '',
					discount: '',
					applied_coupons: [],
					catalog_url: fallbackCatalogUrl,
					shipping_total: 0,
					shipping_deferred: false,
					fee_lines: []
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

	function getRailProgress01(state) {
		if (!state) {
			return 1;
		}
		ensureV2ScreenState(state);
		var total = 1;
		var idx = 0;
		if (isV2CheckoutUiEnabled(state)) {
			total = Math.max(1, state.v2Screens && state.v2Screens.length ? state.v2Screens.length : 1);
			idx = typeof state.v2CurrentIndex === 'number' ? state.v2CurrentIndex : 0;
			idx = Math.max(0, Math.min(total - 1, idx));
		} else {
			total = Math.max(1, state.visibleSteps && state.visibleSteps.length ? state.visibleSteps.length : 1);
			idx = Math.max(0, getStepIndex(state.visibleSteps, state.currentStepId));
		}
		var raw = (idx + 1) / total;
		return Math.min(1, Math.max(0.08, raw));
	}

	function applyMotionFromState(state) {
		var root = document.querySelector(selectors.root);
		if (!root) {
			return;
		}
		syncMotionRuntimeVars();
		var m = getMotionConfig();
		var toggles = m.toggles || {};
		if (toggles.rail === false) {
			return;
		}
		var next = getRailProgress01(state);
		var snap = shouldThrottleMotion('rail');
		if (snap) {
			root.classList.add('mp-cc-motion-rail-snap');
		}
		root.style.setProperty('--mp-cc-rail-progress', String(next));
		if (snap) {
			window.requestAnimationFrame(function () {
				root.classList.remove('mp-cc-motion-rail-snap');
			});
		}
	}

	function initFieldErrorMotion($root) {
		var root = document.querySelector(selectors.root);
		if (!root || root.getAttribute('data-mp-cc-field-motion') !== '1' || !$root || !$root.length) {
			return;
		}
		var m = getMotionConfig();
		if (!m.toggles || m.toggles.field_state === false || useCheckoutReducedMotion()) {
			return;
		}
		var $errs = $root.find('.mp-cc-field-error').filter(function () {
			return $.trim($(this).text()) !== '';
		});
		if (!$errs.length) {
			return;
		}
		$errs.removeClass('mp-cc-field-error--entering mp-cc-field-error--enter-active');
		window.requestAnimationFrame(function () {
			$errs.addClass('mp-cc-field-error--entering');
			window.requestAnimationFrame(function () {
				$errs.addClass('mp-cc-field-error--enter-active');
			});
		});
	}

	function postCheckout(subAction, payload) {
		var localized = window.mpCcCheckout || {};
		if (!localized.ajaxUrl || !localized.nonce) {
			return $.Deferred().resolve({ success: true }).promise();
		}

		var request = $.ajax({
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
		if (subAction !== 'ajax_error_log' && subAction !== 'client_error_log') {
			request.fail(function (xhr, statusText, errorThrown) {
				var responseSnippet = '';
				if (xhr && xhr.responseText) {
					responseSnippet = String(xhr.responseText).slice(0, 300);
				}
				postCheckout('ajax_error_log', {
					operation: subAction,
					status: xhr && typeof xhr.status === 'number' ? xhr.status : 0,
					error: String(errorThrown || statusText || 'ajax_failed'),
					response_snippet: responseSnippet
				});
			});
		}
		return request;
	}

	/**
	 * Сброс сессии checkout при уходе со страницы (не держим «черновик» после выхода из оформления).
	 * sendBeacon / fetch keepalive — запрос успевает уйти при закрытии вкладки.
	 */
	function bindAbandonCheckoutOnPageLeave() {
		var sent = false;
		function sendAbandonBeacon() {
			if (sent) {
				return;
			}
			var localized = window.mpCcCheckout || {};
			if (!localized.ajaxUrl || !localized.nonce) {
				return;
			}
			var nav = typeof performance !== 'undefined' && performance.getEntriesByType ? performance.getEntriesByType('navigation')[0] : null;
			if (nav && nav.type === 'reload') {
				return;
			}
			sent = true;
			var ctx = typeof window.__mpCcCheckoutContextId === 'string' ? window.__mpCcCheckoutContextId : '';
			var fd = new FormData();
			fd.append('action', 'mp_cc_checkout');
			fd.append('nonce', localized.nonce);
			fd.append('sub_action', 'session_abandon');
			fd.append('context_id', ctx);
			if (typeof navigator !== 'undefined' && typeof navigator.sendBeacon === 'function' && navigator.sendBeacon(localized.ajaxUrl, fd)) {
				return;
			}
			if (typeof fetch === 'function') {
				fetch(localized.ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin', keepalive: true });
			}
		}
		window.addEventListener('pagehide', function (ev) {
			if (ev.persisted) {
				return;
			}
			var nav = typeof performance !== 'undefined' && performance.getEntriesByType ? performance.getEntriesByType('navigation')[0] : null;
			if (nav && nav.type === 'reload') {
				return;
			}
			sendAbandonBeacon();
		});
	}

	function reportClientError(type, message, stack, state) {
		postCheckout('client_error_log', {
			error_type: String(type || 'js_error'),
			message: String(message || ''),
			stack: String(stack || ''),
			state: String(state || '')
		});
	}

	function bindClientErrorLogging() {
		if (isClientErrorLoggingBound) {
			return;
		}
		isClientErrorLoggingBound = true;
		window.addEventListener('error', function (event) {
			var msg = event && event.message ? String(event.message) : 'Unknown JS error';
			var stack = event && event.error && event.error.stack ? String(event.error.stack) : '';
			reportClientError('window_error', msg, stack, 'runtime');
		});
		window.addEventListener('unhandledrejection', function (event) {
			var reason = event && event.reason ? event.reason : 'Unhandled promise rejection';
			var message = (typeof reason === 'string') ? reason : (reason && reason.message ? String(reason.message) : 'Unhandled rejection');
			var stack = reason && reason.stack ? String(reason.stack) : '';
			reportClientError('unhandled_rejection', message, stack, 'runtime');
		});
	}

	function stepKeyById(stepId) {
		if (stepId === 'address_delivery') {
			return 'step_one';
		}
		if (stepId === 'date' || stepId === 'conditions') {
			return 'date_conditions';
		}
		if (stepId === 'recipient' || stepId === 'payment' || stepId === 'confirm' || stepId === 'contact_payment') {
			return 'contact_billing';
		}
		return stepId;
	}

	function syncFromFlow(state, flow, cartPayload, paymentInject) {
		var nextFlow = flow || {};
		if (Array.isArray(nextFlow)) {
			nextFlow = {};
		}
		var prevRuntime = state.frontendStore && state.frontendStore.discounts ? state.frontendStore.discounts.coupon_runtime : null;
		var prevGiftRuntime = state.frontendStore && state.frontendStore.discounts ? state.frontendStore.discounts.gift_card_runtime : null;
		var prevPaymentFields = state.frontendStore && state.frontendStore.payment ? {
			html: state.frontendStore.payment.fieldsHtml,
			gatewayId: state.frontendStore.payment.fieldsGatewayId,
			hydration: state.frontendStore.payment.fieldsHydration,
			compat: state.frontendStore.payment.gatewayCompatIssue
		} : null;
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
		var payExtras = paymentInject && typeof paymentInject === 'object' ? paymentInject : null;
		if (payExtras && Object.prototype.hasOwnProperty.call(payExtras, 'payment_fields_html')) {
			state.frontendStore.payment.fieldsHtml = String(payExtras.payment_fields_html || '');
			state.frontendStore.payment.fieldsGatewayId = String(
				payExtras.payment_fields_gateway || state.frontendStore.payment.gateway || ''
			);
			state.frontendStore.payment.fieldsHydration = 'settled';
			state.frontendStore.payment.gatewayCompatIssue = '';
		} else if (prevPaymentFields && trimNonEmpty(prevPaymentFields.html) && String(prevPaymentFields.gatewayId || '') === String(state.frontendStore.payment.gateway || '')) {
			state.frontendStore.payment.fieldsHtml = prevPaymentFields.html;
			state.frontendStore.payment.fieldsGatewayId = prevPaymentFields.gatewayId;
			state.frontendStore.payment.fieldsHydration = prevPaymentFields.hydration || 'settled';
			state.frontendStore.payment.gatewayCompatIssue = prevPaymentFields.compat || '';
		} else {
			state.frontendStore.payment.fieldsHtml = '';
			state.frontendStore.payment.fieldsGatewayId = '';
			state.frontendStore.payment.fieldsHydration = 'pending';
			state.frontendStore.payment.gatewayCompatIssue = '';
		}
		state.frontendStore.runtime.loading = false;
		state.frontendStore.runtime.blocked = false;
		state.frontendStore.runtime.dirty = false;
		state.frontendStore.runtime.lastSyncAt = Date.now();
		state.frontendStore.runtime.summaryHydrated = true;
		var contextCart = normalizeCartPayload(state.context && state.context.cart ? state.context.cart : {});
		state.frontendStore.cart.items = contextCart.items;
		state.frontendStore.cart.summary = contextCart.summary;
		ensureDiscountDefaults(state);
		if (prevRuntime && typeof prevRuntime === 'object') {
			state.frontendStore.discounts.coupon_runtime = $.extend({}, prevRuntime);
		}
		if (prevGiftRuntime && typeof prevGiftRuntime === 'object') {
			state.frontendStore.discounts.gift_card_runtime = $.extend({}, prevGiftRuntime);
		}
		applyScenarioFieldAvailability(state);
		var dateBox = state.frontendStore && state.frontendStore.fulfillment ? (state.frontendStore.fulfillment.date || {}) : {};
		if (trimNonEmpty(dateBox.shipping_method_id)) {
			var selection = resolveShippingSelection(
				getV2ShippingCatalog(state),
				String(dateBox.shipping_method_id || ''),
				String(dateBox.shipping_tariff_id || '')
			);
			if (selection) {
				applyShippingSelectionToState(state, selection);
			}
		}
	}

	function stripAddressFieldErrors(state) {
		if (!state || !state.frontendStore || !state.frontendStore.form || !state.frontendStore.form.errors) {
			return;
		}
		var errors = state.frontendStore.form.errors.contact;
		if (!errors || typeof errors !== 'object') {
			return;
		}
		var contact = state.frontendStore.form.contact || {};
		var vis = contact.__address_visibility;
		var keys = ['country', 'state', 'city', 'address_1', 'address_2', 'postcode'];
		var i;
		for (i = 0; i < keys.length; i++) {
			var k = keys[i];
			if (!vis || vis.hide_address_fields) {
				delete errors[k];
				continue;
			}
			if (k === 'country' && vis.hide_country) {
				delete errors[k];
			} else if (k === 'state' && vis.hide_region) {
				delete errors[k];
			} else if (k === 'city' && vis.hide_city) {
				delete errors[k];
			} else if ((k === 'address_1' || k === 'address_2') && vis.hide_address_lines) {
				delete errors[k];
			} else if (k === 'postcode' && vis.hide_postcode) {
				delete errors[k];
			}
		}
	}

	function applyScenarioFieldAvailability(state) {
		if (!state || !state.frontendStore || !state.frontendStore.fulfillment) {
			return;
		}
		var scenarioId = normalizeScenarioId(state.frontendStore.fulfillment.scenario || 'pickup');
		var rules = getScenarioRulesById(scenarioId);
		var fieldRules = rules.field_rules && typeof rules.field_rules === 'object' ? rules.field_rules : {};
		var hideAll = Boolean(fieldRules.hide_address_fields);
		var contact = state.frontendStore.form && state.frontendStore.form.contact ? state.frontendStore.form.contact : {};
		contact.__address_visibility = {
			hide_address_fields: hideAll,
			required_address_fields: Boolean(fieldRules.required_address_fields),
			visible_groups: Array.isArray(fieldRules.visible_groups) ? fieldRules.visible_groups : [],
			hide_country: Object.prototype.hasOwnProperty.call(fieldRules, 'hide_country') ? Boolean(fieldRules.hide_country) : hideAll,
			hide_region: Object.prototype.hasOwnProperty.call(fieldRules, 'hide_region') ? Boolean(fieldRules.hide_region) : hideAll,
			hide_city: Object.prototype.hasOwnProperty.call(fieldRules, 'hide_city') ? Boolean(fieldRules.hide_city) : hideAll,
			hide_address_lines: Object.prototype.hasOwnProperty.call(fieldRules, 'hide_address_lines') ? Boolean(fieldRules.hide_address_lines) : hideAll,
			hide_postcode: Object.prototype.hasOwnProperty.call(fieldRules, 'hide_postcode') ? Boolean(fieldRules.hide_postcode) : hideAll
		};
		state.frontendStore.form.contact = contact;
		stripAddressFieldErrors(state);
		document.dispatchEvent(
			new CustomEvent('mp_cc_address_visibility_changed', {
				detail: {
					scenario: scenarioId,
					fieldRules: contact.__address_visibility
				}
			})
		);
	}

	function resetDependentStateForScenario(state, scenarioId) {
		if (!state || !state.frontendStore) {
			return;
		}
		state.frontendStore.fulfillment.date = {};
		state.frontendStore.fulfillment.scenario = scenarioId;
		state.frontendStore.fulfillment.scenarioData = {
			id: scenarioId,
			rules: getScenarioRulesById(scenarioId)
		};
		if (scenarioId === 'pickup') {
			var defaultPoint = getPickupPointById('');
			if (defaultPoint) {
				state.frontendStore.fulfillment.scenarioData.pickup_point = defaultPoint;
			}
		}

		var rules = getScenarioRulesById(scenarioId);
		var fieldRules = rules.field_rules && typeof rules.field_rules === 'object' ? rules.field_rules : {};
		if (fieldRules.hide_address_fields && state.frontendStore.form && state.frontendStore.form.contact) {
			var contact = $.extend({}, state.frontendStore.form.contact);
			var preserveCityOnAddressStep = state && state.currentStepId === 'address_delivery';
			delete contact.address_1;
			delete contact.address_2;
			if (!preserveCityOnAddressStep) {
				delete contact.city;
			}
			delete contact.state;
			delete contact.postcode;
			delete contact.country;
			delete contact.shipping_address;
			delete contact.shipping_city;
			delete contact.shipping_postcode;
			state.frontendStore.form.contact = contact;
		}
		applyScenarioFieldAvailability(state);
	}

	function setRuntimeFlag(state, key, value) {
		if (!state || !state.frontendStore || !state.frontendStore.runtime) {
			return;
		}
		state.frontendStore.runtime[key] = Boolean(value);
	}

	function applyContextCartToFrontendStore(frontendStore, context) {
		if (!frontendStore || !frontendStore.cart) {
			return;
		}
		var cartSnap = normalizeCartPayload(context && context.cart ? context.cart : {});
		frontendStore.cart.items = cartSnap.items;
		frontendStore.cart.summary = cartSnap.summary;
		frontendStore.runtime = frontendStore.runtime || {};
		frontendStore.runtime.summaryHydrated = cartSnap.items.length > 0;
	}

	function syncStoreWithBackend(state, $app) {
		var myGen = ++syncStoreGeneration;
		return postCheckout('session_get_state', { context_id: state.flowContextId }).then(function (response) {
			if (myGen !== syncStoreGeneration) {
				return;
			}
			if (!response || !response.success || !response.data) {
				return;
			}
			var flowPayload = response.data.flow;
			if (flowPayload === undefined || flowPayload === null) {
				return;
			}
			syncFromFlow(state, flowPayload, response.data.cart || {}, paymentFieldPayloadFromAjaxData(response.data));
			var mergedPaymentFields = state.frontendStore && state.frontendStore.payment ? {
				fieldsHtml: state.frontendStore.payment.fieldsHtml,
				fieldsGatewayId: state.frontendStore.payment.fieldsGatewayId,
				fieldsHydration: state.frontendStore.payment.fieldsHydration,
				gatewayCompatIssue: state.frontendStore.payment.gatewayCompatIssue
			} : null;
			var rehydrated = buildState(state.context);
			state.visibleSteps = rehydrated.visibleSteps;
			state.currentStepId = rehydrated.currentStepId;
			state.maxReachedIndex = Math.max(state.maxReachedIndex, rehydrated.maxReachedIndex);
			applyContextCartToFrontendStore(rehydrated.frontendStore, state.context);
			state.frontendStore = rehydrated.frontendStore;
			if (mergedPaymentFields && state.frontendStore.payment) {
				state.frontendStore.payment.fieldsHtml = mergedPaymentFields.fieldsHtml;
				state.frontendStore.payment.fieldsGatewayId = mergedPaymentFields.fieldsGatewayId;
				state.frontendStore.payment.fieldsHydration = mergedPaymentFields.fieldsHydration;
				state.frontendStore.payment.gatewayCompatIssue = mergedPaymentFields.gatewayCompatIssue;
			}
			state.flowContextId = rehydrated.flowContextId;
			var skipContactDomHydration = state.currentStepId === 'address_delivery';
			if (!skipContactDomHydration && isV2CheckoutUiEnabled(state)) {
				ensureV2ScreenState(state);
				if (state.v2CurrentIndex === 0) {
					skipContactDomHydration = true;
				}
			}
			if (!skipContactDomHydration) {
				flushContactFormFromDom(state, $app);
				ensureContactDefaults(state);
			}
			render(state, $app);
		}).fail(function (xhr) {
			var payload = xhr && xhr.responseJSON && xhr.responseJSON.data ? xhr.responseJSON.data : {};
			if (String(payload.code || '') === 'stale_context') {
				recoverFromInvalidSessionState(state, $app);
			}
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

		if (!lockCriticalRequest('stepTransition')) {
			return $.Deferred().reject().promise();
		}
		return withTransitionLock(state, $app, function () {
			runStepTransitionAnimation($app);
			return postCheckout('session_set_step', { step_id: targetStepId, context_id: state.flowContextId }).then(function (response) {
				if (!response || !response.success) {
					return $.Deferred().reject(response).promise();
				}
				if (response.data && response.data.flow) {
					syncFromFlow(state, response.data.flow, response.data.cart || {});
				}
				state.currentStepId = targetStepId;
				state.frontendStore.steps.current = targetStepId;
				state.maxReachedIndex = Math.max(state.maxReachedIndex, targetIndex);
				setRuntimeFlag(state, 'blocked', false);
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
				postCheckout('validation_log', {
					step_id: targetStepId,
					context_id: state.flowContextId,
					errors: {},
					marker: 'step_transition_ok'
				});
				return $.Deferred().resolve().promise();
			}).fail(function (xhr) {
				var payload = xhr && xhr.responseJSON && xhr.responseJSON.data ? xhr.responseJSON.data : {};
				var code = payload.code ? String(payload.code) : '';
				postCheckout('validation_log', {
					step_id: targetStepId,
					context_id: state.flowContextId,
					errors: { transition: code || 'step_transition_failed' },
					marker: 'step_transition_failed'
				});
				if (code === 'stale_context') {
					recoverFromInvalidSessionState(state, $app);
					return;
				}
				if (xhr && xhr.status === 422) {
					notify(payload.message || getUiText('common.error_generic', 'Произошла ошибка. Попробуйте ещё раз.'), 'error');
				} else {
					recoverFromStepAjaxFailure(state, $app, getStepFourAjaxMessage('step_sync_failed', 'step_4.contact_ajax_step_sync_failed', 'Не удалось синхронизировать шаг. Обновите страницу.'));
				}
			}).always(function () {
				unlockCriticalRequest('stepTransition');
			});
		});
	}

	function setCurrentV2Screen(state, $app, targetIndex) {
		ensureV2ScreenState(state);
		if (targetIndex < 0 || targetIndex >= state.v2Screens.length) {
			return $.Deferred().reject().promise();
		}
		var targetScreen = state.v2Screens[targetIndex];
		var legacyStepId = getV2LegacyStepId(targetScreen);
		var done = function () {
			state.v2CurrentIndex = targetIndex;
			state.v2MaxReachedIndex = Math.max(state.v2MaxReachedIndex, targetIndex);
			render(state, $app);
			scrollToStepTop();
			document.dispatchEvent(
				new CustomEvent('mp_cc_v2_step_changed', {
					detail: {
						screenId: targetScreen.id,
						index: targetIndex + 1,
						total: state.v2Screens.length,
						legacyStepId: legacyStepId
					}
				})
			);
			postCheckout('validation_log', {
				step_id: targetScreen.id,
				context_id: state.flowContextId,
				errors: {},
				marker: 'v2_step_transition'
			});
		};
		if (state.currentStepId !== legacyStepId) {
			return setCurrentStep(state, $app, legacyStepId).then(function () {
				done();
			});
		}
		done();
		return $.Deferred().resolve().promise();
	}

	function moveBackward(state, $app) {
		if (isV2CheckoutUiEnabled(state)) {
			ensureV2ScreenState(state);
			if (state.v2CurrentIndex <= 0) {
				return;
			}
			var nextIdx = state.v2CurrentIndex - 1;
			state.v2MaxReachedIndex = Math.min(state.v2MaxReachedIndex, nextIdx);
			setCurrentV2Screen(state, $app, nextIdx);
			return;
		}
		var currentIndex = getStepIndex(state.visibleSteps, state.currentStepId);
		if (currentIndex <= 0) {
			return;
		}
		var target = state.visibleSteps[currentIndex - 1];
		setCurrentStep(state, $app, target.id);
	}

	function moveForward(state, $app) {
		if (isV2CheckoutUiEnabled(state)) {
			ensureV2ScreenState(state);
			var v2Idx = state.v2CurrentIndex;
			var currentScreen = state.v2Screens[v2Idx];
			if (!currentScreen) {
				return;
			}
			if (currentScreen.id === 'delivery_screen') {
				var shipDateBox = state.frontendStore && state.frontendStore.fulfillment ? (state.frontendStore.fulfillment.date || {}) : {};
				if (!trimNonEmpty(shipDateBox.shipping_method_id)) {
					setV2StepInvalidState(state, currentScreen.id, true);
					notify('Выберите способ доставки, чтобы продолжить.', 'error');
					render(state, $app);
					scrollToFirstInvalidField($app);
					return;
				}
				var selectedDateV2 = state.frontendStore && state.frontendStore.fulfillment && state.frontendStore.fulfillment.date
					? String(state.frontendStore.fulfillment.date.selected_date || '')
					: '';
				if (!selectedDateV2 || !parseIsoDate(selectedDateV2)) {
					setV2StepInvalidState(state, currentScreen.id, true);
					notify(getStepThreeErrorCopy('empty_date', 'Выберите дату, чтобы продолжить.'), 'error');
					render(state, $app);
					scrollToFirstInvalidField($app);
					return;
				}
				setV2StepInvalidState(state, currentScreen.id, false);
				saveCurrentStepDraft(state);
				setCurrentV2Screen(state, $app, v2Idx + 1);
				return;
			}
			if (currentScreen.id === 'recipient_screen') {
				flushContactFormFromDom(state, $app);
				if (!validateRecipientStep(state)) {
					setV2StepInvalidState(state, currentScreen.id, true);
					logValidationFailure(state, 'recipient_screen', state.frontendStore.form.errors ? state.frontendStore.form.errors.contact : {});
					notify(getUiText('step_4.contact_error_all_required', 'Не все обязательные поля заполнены.'), 'error');
					render(state, $app);
					scrollToFirstInvalidField($app);
					return;
				}
				setV2StepInvalidState(state, currentScreen.id, false);
				saveCurrentStepDraft(state);
				setCurrentV2Screen(state, $app, v2Idx + 1);
				return;
			}
			if (currentScreen.id === 'payment_screen') {
				var selectedGatewayV2 = trimNonEmpty(state.frontendStore && state.frontendStore.payment ? state.frontendStore.payment.gateway : '');
				if (!selectedGatewayV2) {
					state.frontendStore.form.errors = state.frontendStore.form.errors || {};
					state.frontendStore.form.errors.contact = state.frontendStore.form.errors.contact || {};
					state.frontendStore.form.errors.contact.payment_gateway = 'required';
					setV2StepInvalidState(state, currentScreen.id, true);
					notify(getUiText('step_4.payment_error_required', 'Выберите способ оплаты.'), 'error');
					render(state, $app);
					scrollToFirstInvalidField($app);
					return;
				}
				setV2StepInvalidState(state, currentScreen.id, false);
				saveCurrentStepDraft(state).always(function () {
					setCurrentV2Screen(state, $app, v2Idx + 1);
				});
				return;
			}
			if (currentScreen.id === 'confirm_screen') {
				saveCurrentStepDraft(state).always(function () {
					submitFinalPayment(state, $app);
				});
				return;
			}
		}
		var currentIndex = getStepIndex(state.visibleSteps, state.currentStepId);
		if (currentIndex < 0) {
			return;
		}
		if (currentIndex >= state.visibleSteps.length - 1) {
			if (state.currentStepId === 'confirm') {
				ensureContactDefaults(state);
				if (!validateContactPaymentStep(state)) {
					setStepInvalidState(state, 'confirm', true);
					logValidationFailure(state, 'confirm', state.frontendStore.form.errors ? state.frontendStore.form.errors.contact : {});
					notify(getUiText('step_4.contact_error_all_required', 'Не все обязательные поля заполнены.'), 'error');
					render(state, $app);
					scrollToFirstInvalidField($app);
					return;
				}
				setStepInvalidState(state, 'confirm', false);
				saveCurrentStepDraft(state).fail(function () {
					notify(getStepFourAjaxMessage('draft_save_failed', 'step_4.contact_ajax_draft_save_failed', 'Не удалось сохранить данные.'), 'error');
				});
				state.frontendStore.runtime = state.frontendStore.runtime || {};
				document.dispatchEvent(
					new CustomEvent('mp_cc_pre_payment_confirmed', {
						detail: {
							context_id: state.flowContextId,
							payment_gateway: state.frontendStore && state.frontendStore.payment ? state.frontendStore.payment.gateway : ''
						}
					})
				);
				submitFinalPayment(state, $app);
			}
			return;
		}
		var target = state.visibleSteps[currentIndex + 1];
		var cartSummary = state.frontendStore && state.frontendStore.cart ? state.frontendStore.cart.summary || {} : {};
		if (state.currentStepId === 'address_delivery') {
			if (!isAddressDeliveryStepReady(state)) {
				state.frontendStore.form.errors = state.frontendStore.form.errors || {};
				state.frontendStore.form.errors.shipping_method_id = 'required';
				setStepInvalidState(state, 'address_delivery', true);
				logValidationFailure(state, 'address_delivery', { shipping_method_id: 'required' });
				notify(getShippingErrorCopy().methodRequired || 'Выберите способ доставки, чтобы продолжить.', 'error');
				render(state, $app);
				scrollToFirstInvalidField($app);
				return;
			}
			state.frontendStore.form.errors = state.frontendStore.form.errors || {};
			state.frontendStore.form.errors.date = '';
			state.frontendStore.form.errors.shipping_method_id = '';
			setStepInvalidState(state, 'address_delivery', false);
		}
		if (state.currentStepId === 'recipient') {
			flushContactFormFromDom(state, $app);
			ensureContactDefaults(state);
			if (!validateRecipientStep(state)) {
				setStepInvalidState(state, 'recipient', true);
				logValidationFailure(state, 'recipient', state.frontendStore.form.errors ? state.frontendStore.form.errors.contact : {});
				notify(getUiText('step_4.contact_error_all_required', 'Не все обязательные поля заполнены.'), 'error');
				render(state, $app);
				scrollToFirstInvalidField($app);
				return;
			}
			setStepInvalidState(state, 'recipient', false);
		}
		if (state.currentStepId === 'payment') {
			var selectedGateway = trimNonEmpty(state.frontendStore && state.frontendStore.payment ? state.frontendStore.payment.gateway : '');
			if (!selectedGateway) {
				state.frontendStore.form.errors = state.frontendStore.form.errors || {};
				state.frontendStore.form.errors.contact = state.frontendStore.form.errors.contact || {};
				state.frontendStore.form.errors.contact.payment_gateway = 'required';
				setStepInvalidState(state, 'payment', true);
				notify(getUiText('step_4.payment_error_required', 'Выберите способ оплаты.'), 'error');
				render(state, $app);
				scrollToFirstInvalidField($app);
				return;
			}
			setStepInvalidState(state, 'payment', false);
		}
		requestForwardValidation(state.currentStepId).then(function (valid) {
			if (!valid) {
				setRuntimeFlag(state, 'blocked', true);
				var vm = getStepFourValidationMessages();
				notify(trimNonEmpty(vm.step_blocked) || getUiText('step_4.contact_error_step_blocked', 'Заполните обязательные поля текущего шага.'), 'error');
				return;
			}
			setRuntimeFlag(state, 'blocked', false);
			setStepInvalidState(state, state.currentStepId, false);
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

	function scheduleCurrentStepDraftSave(state, onError) {
		if (draftSaveTimer) {
			window.clearTimeout(draftSaveTimer);
		}
		draftSaveTimer = window.setTimeout(function () {
			draftSaveTimer = null;
			saveCurrentStepDraft(state).fail(function () {
				if (typeof onError === 'function') {
					onError();
				}
			});
		}, 260);
	}

	function saveDiscountDraft(state) {
		ensureDiscountDefaults(state);
		return postCheckout('session_set_answers', {
			step_id: 'discounts',
			context_id: state.flowContextId,
			answers: state.frontendStore.discounts || {}
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
			var rawContact = state.frontendStore.form.contact || {};
			var out = {};
			var ck;
			for (ck in rawContact) {
				if (!Object.prototype.hasOwnProperty.call(rawContact, ck)) {
					continue;
				}
				if (ck.indexOf('__') === 0) {
					continue;
				}
				out[ck] = rawContact[ck];
			}
			return out;
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
		return '';
	}

	function buildParcelHeaderHtml(state) {
		var cart = state && state.frontendStore && state.frontendStore.cart ? state.frontendStore.cart : {};
		var items = Array.isArray(cart.items) ? cart.items : [];
		if (!items.length) {
			return '';
		}
		var html = '';
		html += '<ul class="mp-cc-parcel-head-list" role="list">';
		for (var i = 0; i < items.length; i += 1) {
			var item = items[i] && typeof items[i] === 'object' ? items[i] : {};
			var title = trimNonEmpty(item.name) || getUiText('step_1.title', 'Товар');
			var qty = Number(item.quantity || 0);
			var qtyLabel = qty > 0 ? String(qty) + ' шт' : '';
			var imageUrl = item.image_url ? String(item.image_url) : '';
			html += '<li class="mp-cc-parcel-head-list__item">';
			html += '<article class="mp-cc-parcel-head">';
			html += '<div class="mp-cc-parcel-head__media" aria-hidden="true">';
			if (imageUrl) {
				html += '<img class="mp-cc-parcel-head__img" src="' + escapeHtml(imageUrl) + '" alt="" loading="lazy" decoding="async" />';
			} else {
				html += '<span class="mp-cc-parcel-head__ph" aria-hidden="true"></span>';
			}
			html += '</div>';
			html += '<div class="mp-cc-parcel-head__body">';
			html += '<h3 class="mp-cc-parcel-head__title">' + escapeHtml(title) + '</h3>';
			if (qtyLabel) {
				html += '<p class="mp-cc-parcel-head__meta">' + escapeHtml(qtyLabel) + '</p>';
			}
			html += '</div>';
			html += '</article>';
			html += '</li>';
		}
		html += '</ul>';
		return html;
	}

	function buildConfirmationScreenHtml(state) {
		var payment = state.frontendStore && state.frontendStore.payment ? state.frontendStore.payment : {};
		var gateway = trimNonEmpty(payment.gateway) || getUiText('step_4.payment_title', 'способ оплаты');
		var html = '';
		html += '<section class="mp-cc-confirm-screen" aria-labelledby="mp-cc-confirm-title">';
		html += '<h3 id="mp-cc-confirm-title">' + escapeHtml(getUiText('common.confirm', 'Подтверждение')) + '</h3>';
		html += '<p class="mp-cc-step-panel__hint">' + escapeHtml(getUiText('order_review.final_hint', 'Проверьте данные справа и нажмите кнопку оформления заказа.')) + '</p>';
		html += '<p class="mp-cc-step-panel__hint">' + escapeHtml(getUiText('order_review.selected_gateway', 'Выбранный способ оплаты') + ': ' + gateway) + '</p>';
		html += '</section>';
		return html;
	}

	function buildStepPanelHtml(state) {
		if (isV2CheckoutUiEnabled(state)) {
			ensureV2ScreenState(state);
			var screen = state.v2Screens[state.v2CurrentIndex] || state.v2Screens[0];
			var screenLabel = screen ? screen.label : getUiText('checkout.progress_label', 'Оформление');
			var v2html = '';
			v2html += '<section class="mp-cc-step-panel mp-cc-step-screen" data-step-panel="' + escapeHtml(screen ? screen.id : '') + '">';
			v2html += '<header class="mp-cc-step-panel__header">';
			v2html += '<p class="mp-cc-step-panel__meta">' + escapeHtml(formatCheckoutStepMeta(state.v2CurrentIndex + 1, state.v2Screens.length)) + '</p>';
			v2html += '<h2 class="mp-cc-step-panel__title" id="mp-cc-step-heading" tabindex="-1">' + escapeHtml(screenLabel) + '</h2>';
			v2html += '</header>';
			v2html += '<div class="mp-cc-step-panel__content" data-mp-cc-step-slot="' + escapeHtml(screen ? screen.id : '') + '">';
			if (screen && screen.id === 'delivery_screen') {
				v2html += buildFulfillmentChoiceHtml(state);
			}
			if (screen && screen.id === 'recipient_screen') {
				v2html += buildContactPaymentHtml(state, { includePayment: false });
				v2html += buildAddressBlockHtml(state);
			}
			if (screen && screen.id === 'payment_screen') {
				v2html += buildPaymentGatewaysHtml(state);
			}
			if (screen && screen.id === 'confirm_screen') {
				v2html += buildConfirmationScreenHtml(state);
			}
			v2html += '</div>';
			v2html += '</section>';
			return v2html;
		}
		var html = '';
		var currentIndex = getStepIndex(state.visibleSteps, state.currentStepId);
		for (var i = 0; i < state.visibleSteps.length; i += 1) {
			var step = state.visibleSteps[i];
			var isActive = i === currentIndex;
			var isDone = i < currentIndex;
			var cardState = isActive ? 'active' : (isDone ? 'done' : 'future');
			var canOpen = isDone || isActive;
			var stepLabel = step ? (step.label || step.id) : '';
			html += '<article class="mp-cc-step-card mp-cc-step-card--' + cardState + '" data-step-id="' + escapeHtml(step.id) + '" data-step-state="' + cardState + '">';
			html += '<button type="button" class="mp-cc-step-card__head" data-step-open="' + escapeHtml(step.id) + '"' + (canOpen ? '' : ' disabled') + '>';
			html += '<span class="mp-cc-step-card__index">' + (i + 1) + '</span>';
			html += '<span class="mp-cc-step-card__title">' + escapeHtml(stepLabel) + '</span>';
			html += '</button>';
			if (isActive) {
				html += '<section class="mp-cc-step-panel mp-cc-step-screen" data-step-panel="' + escapeHtml(step.id) + '">';
				html += '<div class="mp-cc-step-panel__content" data-mp-cc-step-slot="' + escapeHtml(step.id) + '">';
				if (step.id === 'address_delivery') {
					html += buildAddressDeliveryFormHtml(state);
				}
				if (step.id === 'recipient') {
					html += buildContactPaymentHtml(state, { includePayment: false });
					html += buildAddressBlockHtml(state);
				}
				if (step.id === 'payment') {
					html += buildPaymentGatewaysHtml(state);
				}
				if (step.id === 'confirm') {
					html += buildConfirmationScreenHtml(state);
				}
				html += '</div>';
				if (step.id === 'recipient' || step.id === 'payment' || step.id === 'confirm') {
					html += '<div class="mp-cc-step-card__actions">';
					if (step.id === 'confirm') {
						html += '<button type="button" class="mp-cc-nav__btn mp-cc-nav__btn--next mp-cc-step-card__cta" data-nav="next">' + escapeHtml(getUiText('common.confirm', 'Оформить заказ')) + '</button>';
					} else {
						html += '<button type="button" class="mp-cc-nav__btn mp-cc-nav__btn--next mp-cc-step-card__cta" data-nav="next">' + escapeHtml(getUiText('common.next', 'Далее')) + '</button>';
					}
					html += '</div>';
				}
				html += '</section>';
			}
			html += '</article>';
		}

		return html;
	}

	function buildFulfillmentChoiceHtml(state) {
		var map = getScenarioMap();
		var ui = getScenarioUiConfig();
		var scenarios = map.scenarios || {};
		var rules = map.rules || {};
		var selectedScenario = String(state.frontendStore && state.frontendStore.fulfillment ? (state.frontendStore.fulfillment.scenario || '') : '');
		var selectedMethodId = String(state.frontendStore && state.frontendStore.fulfillment && state.frontendStore.fulfillment.date ? (state.frontendStore.fulfillment.date.shipping_method_id || '') : '');
		var methodScenario = scenarioByShippingMethod(selectedMethodId);
		var scenarioIds = Object.keys(scenarios);
		var pickupId = 'pickup';
		var deliveryIds = [];
		var i;

		for (i = 0; i < scenarioIds.length; i += 1) {
			if (scenarioIds[i] !== pickupId) {
				deliveryIds.push(scenarioIds[i]);
			}
		}
		var defaultDeliveryId = deliveryIds.length ? deliveryIds[0] : 'krasnoyarsk_delivery';
		var selectedGroup = (selectedScenario === pickupId || methodScenario === pickupId) ? pickupId : 'delivery';
		var deliveryLabel = scenarios[defaultDeliveryId] ? String(scenarios[defaultDeliveryId]) : 'Доставка';
		var pickupLabel = scenarios[pickupId] ? String(scenarios[pickupId]) : 'Самовывоз';
		var pickupCopy = rules[pickupId] && rules[pickupId].copy_rules ? String(rules[pickupId].copy_rules.hint || '') : '';
		var deliveryCopy = rules[defaultDeliveryId] && rules[defaultDeliveryId].copy_rules ? String(rules[defaultDeliveryId].copy_rules.hint || '') : '';

		var cardOrder = Array.isArray(ui.card_order) ? ui.card_order : ['pickup', 'delivery'];
		var cards = [];
		for (i = 0; i < cardOrder.length; i += 1) {
			var key = String(cardOrder[i] || '');
			if (key !== 'pickup' && key !== 'delivery') {
				continue;
			}
			var cardCfg = ui.cards && ui.cards[key] ? ui.cards[key] : {};
			if (key === 'pickup') {
				cards.push({
					group: 'pickup',
					target: pickupId,
					label: cardCfg.title ? String(cardCfg.title) : pickupLabel,
					copy: cardCfg.description ? String(cardCfg.description) : pickupCopy,
					helper: cardCfg.helper ? String(cardCfg.helper) : '',
					icon: cardCfg.icon_variant ? String(cardCfg.icon_variant) : 'pickup',
					iconStyle: cardCfg.icon_style ? String(cardCfg.icon_style) : 'soft'
				});
			} else {
				cards.push({
					group: 'delivery',
					target: defaultDeliveryId,
					label: cardCfg.title ? String(cardCfg.title) : deliveryLabel,
					copy: cardCfg.description ? String(cardCfg.description) : deliveryCopy,
					helper: cardCfg.helper ? String(cardCfg.helper) : '',
					icon: cardCfg.icon_variant ? String(cardCfg.icon_variant) : 'delivery',
					iconStyle: cardCfg.icon_style ? String(cardCfg.icon_style) : 'soft'
				});
			}
		}
		if (!cards.length) {
			cards.push({ group: 'pickup', target: pickupId, label: pickupLabel, copy: pickupCopy, helper: '', icon: 'pickup', iconStyle: 'soft' });
			cards.push({ group: 'delivery', target: defaultDeliveryId, label: deliveryLabel, copy: deliveryCopy, helper: '', icon: 'delivery', iconStyle: 'soft' });
		}

		var html = '';
		html += '<section class="mp-cc-fulfillment mp-cc-fulfillment--desktop-' + escapeHtml(ui.responsive.desktop_columns) + ' mp-cc-fulfillment--tablet-' + escapeHtml(ui.responsive.tablet_columns) + ' mp-cc-fulfillment--mobile-' + escapeHtml(ui.responsive.mobile_columns) + ' mp-cc-fulfillment--' + escapeHtml(ui.responsive.card_density || 'comfortable') + '" aria-labelledby="mp-cc-fulfillment-title">';
		html += '<header class="mp-cc-fulfillment__header">';
		html += '<h3 class="mp-cc-fulfillment__title" id="mp-cc-fulfillment-title">' + escapeHtml(getUiText('step_2.title', 'Выберите способ получения')) + '</h3>';
		html += '</header>';
		if (!isV2CheckoutUiEnabled(state)) {
			html += '<div class="mp-cc-fulfillment__cards" role="radiogroup" aria-label="' + escapeHtml(getUiText('step_2.title', 'Способ получения')) + '">';
			for (i = 0; i < cards.length; i += 1) {
				var card = cards[i];
				var isActive = selectedGroup === card.group;
				html += '<button type="button" class="mp-cc-fulfillment-card mp-cc-fulfillment-card--' + escapeHtml(card.iconStyle || 'soft') + (isActive ? ' is-active' : '') + '"';
				html += ' role="radio"';
				html += ' aria-checked="' + (isActive ? 'true' : 'false') + '"';
				html += ' tabindex="' + (isActive ? '0' : '-1') + '"';
				html += ' data-scenario-card="' + escapeHtml(card.group) + '"';
				html += ' data-scenario-target="' + escapeHtml(card.target) + '"';
				html += '>';
				html += '<span class="mp-cc-fulfillment-card__media" aria-hidden="true">';
				html += '<span class="mp-cc-fulfillment-card__icon mp-cc-fulfillment-card__icon--' + escapeHtml(card.icon) + '"></span>';
				html += '</span>';
				html += '<span class="mp-cc-fulfillment-card__body">';
				html += '<span class="mp-cc-fulfillment-card__label">' + escapeHtml(card.label) + '</span>';
				if (card.copy) {
					html += '<span class="mp-cc-fulfillment-card__copy">' + escapeHtml(card.copy) + '</span>';
				}
				if (card.helper) {
					html += '<span class="mp-cc-fulfillment-card__helper">' + escapeHtml(card.helper) + '</span>';
				}
				html += '</span>';
				html += '</button>';
			}
			html += '</div>';
		}
		if (selectedMethodId === 'pickup') {
			html += buildPickupPointHtml(state);
		}
		html += buildShippingMethodsHtml(state);
		html += buildDateCalendarHtml(state);
		html += '</section>';
		return html;
	}

	function isAddressDeliveryStepReady(state) {
		var methods = getV2ShippingCatalog(state);
		var dateBox = state.frontendStore && state.frontendStore.fulfillment ? (state.frontendStore.fulfillment.date || {}) : {};
		var methodId = String(dateBox.shipping_method_id || '');
		var tariffId = String(dateBox.shipping_tariff_id || '');
		if (!methodId) {
			return false;
		}
		var selected = null;
		var i;
		for (i = 0; i < methods.length; i += 1) {
			if (String(methods[i].id || '') === methodId) {
				selected = methods[i];
				break;
			}
		}
		if (!selected) {
			return false;
		}
		var tariffs = Array.isArray(selected.tariffs) ? selected.tariffs : [];
		if (tariffs.length && !tariffId) {
			return false;
		}
		if (tariffs.length) {
			var hasTariff = false;
			for (i = 0; i < tariffs.length; i += 1) {
				if (String(tariffs[i].id || '') === tariffId) {
					hasTariff = true;
					break;
				}
			}
			if (!hasTariff) {
				return false;
			}
		}
		return true;
	}

	function tryAutoAdvanceAddressStep(state, $app) {
		if (!state || state.currentStepId !== 'address_delivery' || !isAddressDeliveryStepReady(state)) {
			return;
		}
		var currentIdx = getStepIndex(state.visibleSteps, 'address_delivery');
		if (currentIdx < 0) {
			return;
		}
		var nextStep = state.visibleSteps[currentIdx + 1];
		var nextStepId = nextStep && nextStep.id ? String(nextStep.id) : '';
		if (!nextStepId) {
			return;
		}
		setStepInvalidState(state, 'address_delivery', false);
		if (isV2CheckoutUiEnabled(state)) {
			ensureV2ScreenState(state);
			var v2Target = -1;
			var vsi;
			for (vsi = 0; vsi < state.v2Screens.length; vsi += 1) {
				if (getV2LegacyStepId(state.v2Screens[vsi]) === nextStepId) {
					v2Target = vsi;
					break;
				}
			}
			if (v2Target >= 0) {
				setCurrentV2Screen(state, $app, v2Target);
				return;
			}
		}
		setCurrentStep(state, $app, nextStepId);
	}

	function buildAddressDeliveryFormHtml(state) {
		var dateBox = state.frontendStore && state.frontendStore.fulfillment ? (state.frontendStore.fulfillment.date || {}) : {};
		var methods = getV2ShippingCatalog(state);
		var methodOrder = ['pickup', 'post_russia', 'pvz', 'courier', 'krasnoyarsk_delivery'];
		var selectedMethodId = String(dateBox.shipping_method_id || '');
		var selectedTariffId = String(dateBox.shipping_tariff_id || '');
		var runtime = state.frontendStore && state.frontendStore.runtime && typeof state.frontendStore.runtime === 'object'
			? state.frontendStore.runtime
			: {};
		var contact = state.frontendStore && state.frontendStore.form ? (state.frontendStore.form.contact || {}) : {};
		var scenarioData = state.frontendStore && state.frontendStore.fulfillment ? (state.frontendStore.fulfillment.scenarioData || {}) : {};
		var point = scenarioData.pickup_point && typeof scenarioData.pickup_point === 'object' ? scenarioData.pickup_point : getPickupPointById('');
		var savedCity = trimNonEmpty(contact.city);
		var inferredCity = savedCity || (point && point.city ? String(point.city) : '');
		var cityEmptyHint = getStepOneLabel(state, 'address_form.city_empty_hint', '', 'Укажите населённый пункт');
		var cityPlaceholder = getStepOneLabel(state, 'address_form.city_placeholder', '', 'Укажите город');
		var officeNotSet = getStepOneLabel(state, 'address_form.office_not_set', '', 'Не выбран');
		var pointAddress = point && point.address ? String(point.address) : officeNotSet;
		var showPvzRow = selectedMethodId === 'pickup' || selectedMethodId === 'pvz';
		var cityEditMode = Boolean(runtime.step1_city_editing);
		var pvzEditMode = Boolean(runtime.step1_pvz_editing);
		var html = '';
		var i;
		var selectedMethodHasTariffs = false;
		for (i = 0; i < methods.length; i += 1) {
			var selectedMethodCandidate = methods[i] || {};
			if (String(selectedMethodCandidate.id || '') !== selectedMethodId) {
				continue;
			}
			selectedMethodHasTariffs = Array.isArray(selectedMethodCandidate.tariffs) && selectedMethodCandidate.tariffs.length > 0;
			break;
		}

		html += '<section class="mp-cc-address-form"' + buildAddressFormStyleAttr(state) + '>';
		html += '<div class="mp-cc-address-form__row" data-row="city">';
		html += '<span class="mp-cc-address-form__label">' + escapeHtml(getStepOneLabel(state, 'address_form.city_row', 'step_4.address_city', 'населённый пункт')) + '</span>';
		html += '<div class="mp-cc-address-form__control">';
		if (cityEditMode) {
			html += '<input type="text" class="mp-cc-address-form__input" data-city-input value="' + escapeHtml(savedCity) + '" placeholder="' + escapeHtml(cityPlaceholder) + '">';
		} else if (inferredCity) {
			html += '<span class="mp-cc-address-form__value">' + escapeHtml(inferredCity) + '</span>';
			html += '<button type="button" class="mp-cc-address-form__edit" data-city-edit>' + escapeHtml(getStepOneLabel(state, 'address_form.change_button', '', 'другой')) + '</button>';
		} else {
			html += '<span class="mp-cc-address-form__value mp-cc-address-form__value--placeholder">' + escapeHtml(cityEmptyHint) + '</span>';
			html += '<button type="button" class="mp-cc-address-form__edit" data-city-edit>' + escapeHtml(getStepOneLabel(state, 'address_form.change_button', '', 'другой')) + '</button>';
		}
		html += '</div>';
		html += '</div>';

		html += '<div class="mp-cc-address-form__row" data-row="method">';
		html += '<span class="mp-cc-address-form__label">' + escapeHtml(getStepOneLabel(state, 'address_form.method_row', '', 'способ доставки')) + '</span>';
		html += '<div class="mp-cc-address-form__methods' + (selectedMethodHasTariffs ? ' mp-cc-address-form__methods--focus-active' : '') + '">';
		for (i = 0; i < methodOrder.length; i += 1) {
			var methodId = methodOrder[i];
			var method = null;
			for (var m = 0; m < methods.length; m += 1) {
				if (String(methods[m].id || '') === methodId) {
					method = methods[m];
					break;
				}
			}
			if (!method) {
				continue;
			}
			var isMethodActive = selectedMethodId === methodId;
			var hasTariffsForMethod = Array.isArray(method.tariffs) && method.tariffs.length > 0;
			var methodTitle = String(method.title || methodId);
			var methodHint = '';
			if (methodId === 'pickup') {
				var pickupCfg = getPickupConfig();
				var pickupPoints = pickupCfg.points || [];
				if (pickupPoints.length && pickupPoints[0] && pickupPoints[0].address) {
					methodHint = String(pickupPoints[0].address);
				}
			} else {
				methodHint = trimNonEmpty(method.description) || trimNonEmpty(method.eta) || '';
			}
			html += '<div class="mp-cc-ship-option-group' + (isMethodActive ? ' is-active' : '') + (hasTariffsForMethod ? ' has-tariffs' : '') + '">';
			html += '<label class="mp-cc-ship-option' + (isMethodActive ? ' is-active' : '') + '">';
			html += '<input type="radio" name="mp-cc-ship-method" data-ship-method="' + escapeHtml(methodId) + '"' + (isMethodActive ? ' checked' : '') + '>';
			html += '<span class="mp-cc-ship-option__title">' + escapeHtml(methodTitle) + '</span>';
			if (methodHint) {
				html += '<span class="mp-cc-ship-option__hint">' + escapeHtml('(' + methodHint + ')') + '</span>';
			}
			html += '</label>';
			var tariffs = Array.isArray(method.tariffs) ? method.tariffs : [];
			if (isMethodActive && tariffs.length) {
				html += '<div class="mp-cc-ship-option__tariffs">';
				html += '<span class="mp-cc-ship-option__tariffs-label">' + escapeHtml(getStepOneLabel(state, 'address_form.tariff_intro', '', 'Выбрать вариант:')) + '</span>';
				for (var t = 0; t < tariffs.length; t += 1) {
					var tariff = tariffs[t] || {};
					var tariffId = String(tariff.id || '');
					var tariffChecked = selectedTariffId === tariffId;
					var tariffPriceText = String(Math.round(Number(tariff.price || 0))) + ' ₽';
					var tariffEtaSuffix = tariff.eta ? ' (' + String(tariff.eta) + ')' : '';
					var tariffLabel = String(tariff.title || tariffId) + tariffEtaSuffix + ': ';
					html += '<label class="mp-cc-ship-option__tariff-item">';
					html += '<input type="radio" name="mp-cc-ship-tariff-' + escapeHtml(methodId) + '" data-ship-tariff="' + escapeHtml(tariffId) + '" data-ship-tariff-method="' + escapeHtml(methodId) + '"' + (tariffChecked ? ' checked' : '') + '>';
					html += '<span>' + escapeHtml(tariffLabel) + '<strong>' + escapeHtml(tariffPriceText) + '</strong></span>';
					html += '</label>';
				}
				html += '</div>';
			}
			html += '</div>';
		}
		html += '</div>';
		html += '</div>';

		html += '<div class="mp-cc-address-form__row" data-row="pvz"' + (showPvzRow ? '' : ' hidden') + '>';
		var officeRowLabelKey = selectedMethodId === 'pvz' ? 'address_form.pvz_row' : 'address_form.office_row';
		var officeRowLabelDefault = selectedMethodId === 'pvz' ? 'адрес пвз' : 'адрес офиса';
		html += '<span class="mp-cc-address-form__label">' + escapeHtml(getStepOneLabel(state, officeRowLabelKey, '', officeRowLabelDefault)) + '</span>';
		html += '<div class="mp-cc-address-form__control">';
		if (pvzEditMode) {
			var pickupCfg = getPickupConfig();
			var points = pickupCfg.points || [];
			if (points.length > 1) {
				html += '<div class="mp-cc-address-form__pvz-list">';
				for (i = 0; i < points.length; i += 1) {
					var item = points[i] || {};
					var itemId = String(item.id || '');
					var isPointChecked = point && String(point.id || '') === itemId;
					html += '<label class="mp-cc-address-form__pvz-item">';
					html += '<input type="radio" name="mp-cc-pvz-point" data-pvz-point="' + escapeHtml(itemId) + '"' + (isPointChecked ? ' checked' : '') + '>';
					html += '<span>' + escapeHtml(String(item.address || item.title || itemId)) + '</span>';
					html += '</label>';
				}
				html += '</div>';
			} else {
				html += '<span class="mp-cc-address-form__value">' + escapeHtml(pointAddress) + '</span>';
			}
		} else {
			html += '<span class="mp-cc-address-form__value">' + escapeHtml(pointAddress) + '</span>';
		}
		html += '</div>';
		html += '</div>';
		html += '</section>';
		return html;
	}

	function buildShippingMethodsHtml(state) {
		var methods = getV2ShippingCatalog(state);
		var dateBox = state.frontendStore && state.frontendStore.fulfillment ? (state.frontendStore.fulfillment.date || {}) : {};
		var selectedMethodId = String(dateBox.shipping_method_id || '');
		var selectedTariffId = String(dateBox.shipping_tariff_id || '');
		var html = '';
		var i;
		html += '<section class="mp-cc-shipping-catalog" aria-labelledby="mp-cc-shipping-catalog-title">';
		html += '<h4 class="mp-cc-shipping-catalog__title" id="mp-cc-shipping-catalog-title">Метод доставки</h4>';
		html += '<div class="mp-cc-shipping-catalog__list" role="radiogroup" aria-label="Выбор метода доставки">';
		for (i = 0; i < methods.length; i += 1) {
			var method = methods[i] || {};
			var methodId = String(method.id || '');
			var hasTariffs = Array.isArray(method.tariffs) && method.tariffs.length > 0;
			var selected = resolveShippingSelection(methods, methodId, selectedMethodId === methodId ? selectedTariffId : '');
			var isActive = selectedMethodId === methodId;
			var title = selected && selected.tariff_title ? selected.tariff_title : String(method.title || methodId);
			var methodPrice = selected ? Number(selected.price || 0) : Number(method.price || 0);
			var priceText = String(Math.round(methodPrice)) + ' ₽';
			var etaText = selected && selected.eta ? String(selected.eta) : String(method.eta || '');
			html += '<button type="button" class="mp-cc-shipping-card' + (isActive ? ' is-active' : '') + '" role="radio"';
			html += ' aria-checked="' + (isActive ? 'true' : 'false') + '"';
			html += ' data-shipping-method="' + escapeHtml(methodId) + '">';
			html += '<span class="mp-cc-shipping-card__title">' + escapeHtml(String(method.title || methodId)) + '</span>';
			html += '<span class="mp-cc-shipping-card__meta">' + escapeHtml(priceText + (etaText ? ' · ' + etaText : '')) + '</span>';
			html += '</button>';
			if (isActive && hasTariffs) {
				var tariffs = method.tariffs || [];
				html += '<div class="mp-cc-shipping-card__tariffs" role="radiogroup" aria-label="Выбор тарифа доставки">';
				for (var t = 0; t < tariffs.length; t += 1) {
					var tariff = tariffs[t] || {};
					var tariffId = String(tariff.id || '');
					var isTariffActive = selectedTariffId === tariffId || (!selectedTariffId && t === 0);
					var tariffPrice = String(Math.round(Number(tariff.price || 0))) + ' ₽';
					var tariffEta = String(tariff.eta || '');
					html += '<button type="button" class="mp-cc-shipping-tariff' + (isTariffActive ? ' is-active' : '') + '" role="radio"';
					html += ' aria-checked="' + (isTariffActive ? 'true' : 'false') + '"';
					html += ' data-shipping-tariff="' + escapeHtml(tariffId) + '"';
					html += ' data-shipping-method-owner="' + escapeHtml(methodId) + '">';
					html += '<span class="mp-cc-shipping-tariff__title">' + escapeHtml(String(tariff.title || tariffId || title)) + '</span>';
					html += '<span class="mp-cc-shipping-tariff__meta">' + escapeHtml(tariffPrice + (tariffEta ? ' · ' + tariffEta : '')) + '</span>';
					html += '</button>';
				}
				html += '</div>';
			}
		}
		html += '</div>';
		html += '</section>';
		return html;
	}

	function buildPickupPointHtml(state) {
		var pickupConfig = getPickupConfig();
		var points = pickupConfig.points || [];
		var scenarioData = state.frontendStore && state.frontendStore.fulfillment ? (state.frontendStore.fulfillment.scenarioData || {}) : {};
		var selectedPoint = scenarioData.pickup_point && typeof scenarioData.pickup_point === 'object' ? scenarioData.pickup_point : getPickupPointById('');
		var selectedPointId = selectedPoint && selectedPoint.id ? String(selectedPoint.id) : '';
		var html = '';

		html += '<section class="mp-cc-pickup-point" aria-labelledby="mp-cc-pickup-title">';
		html += '<h4 class="mp-cc-pickup-point__title" id="mp-cc-pickup-title">' + escapeHtml('Точка самовывоза') + '</h4>';
		if (pickupConfig.enablePointSelection && points.length > 1) {
			html += '<div class="mp-cc-pickup-point__choices" role="radiogroup" aria-label="Выбор точки самовывоза">';
			for (var i = 0; i < points.length; i += 1) {
				var point = points[i] || {};
				var pointId = String(point.id || '');
				var active = pointId === selectedPointId;
				html += '<button type="button" class="mp-cc-pickup-point__choice' + (active ? ' is-active' : '') + '" role="radio"';
				html += ' aria-checked="' + (active ? 'true' : 'false') + '"';
				html += ' data-pickup-point="' + escapeHtml(pointId) + '">';
				html += '<span class="mp-cc-pickup-point__choice-title">' + escapeHtml(String(point.title || pointId)) + '</span>';
				if (point.address) {
					html += '<span class="mp-cc-pickup-point__choice-address">' + escapeHtml(String(point.address)) + '</span>';
				}
				html += '</button>';
			}
			html += '</div>';
		}

		if (selectedPoint) {
			html += '<div class="mp-cc-pickup-point__info">';
			html += '<p class="mp-cc-pickup-point__name">' + escapeHtml(String(selectedPoint.title || '')) + '</p>';
			if (selectedPoint.address) {
				html += '<p class="mp-cc-pickup-point__address">' + escapeHtml(String(selectedPoint.address)) + '</p>';
			}
			if (selectedPoint.description) {
				html += '<p class="mp-cc-pickup-point__description">' + escapeHtml(String(selectedPoint.description)) + '</p>';
			}
			html += '</div>';
			if (pickupConfig.mapSlotEnabled) {
				html += '<div class="mp-cc-pickup-point__map-slot" data-pickup-map-slot="1">';
				html += '<p>' + escapeHtml(String(selectedPoint.map_hint || 'Слот карты будет подключен позже.')) + '</p>';
				html += '</div>';
				html += buildPickupMapHtml(selectedPoint, 'delivery');
			}
		}
		html += '</section>';

		return html;
	}

	function buildCartItemsHtml(state) {
		var items = state.frontendStore && state.frontendStore.cart && Array.isArray(state.frontendStore.cart.items)
			? state.frontendStore.cart.items
			: [];
		var config = state.stepOneConfig || {};
		var visibility = config.product_meta_visibility || {};
		var quantityControls = config.quantity_controls || {};
		var layout = config.layout_order || {};
		var order = Array.isArray(layout.secondary_order) ? layout.secondary_order : ['price', 'sku', 'variation', 'quantity', 'subtotal', 'remove'];
		var html = '';

		html += '<section class="mp-cc-cart-list" aria-label="' + escapeHtml(getStepOneLabel(state, 'title', 'step_1.title', 'Cart items')) + '">';
		html += '<div class="mp-cc-cart-list__items" data-mp-cc-item-list="1">';

		if (!items.length) {
			var emptySummary = state.frontendStore && state.frontendStore.cart ? (state.frontendStore.cart.summary || {}) : {};
			var catalogUrl = emptySummary.catalog_url ? String(emptySummary.catalog_url) : '/';
			var emptyCtaEnabled = (config.empty_state && typeof config.empty_state.cta_enabled !== 'undefined') ? Boolean(config.empty_state.cta_enabled) : true;
			html += '<div class="mp-cc-cart-list__empty">';
			html += '<p class="mp-cc-empty">' + escapeHtml(getStepOneLabel(state, 'empty_title', 'step_1.empty_cart', 'Cart is empty')) + '</p>';
			if (config.empty_state && config.empty_state.message) {
				html += '<p class="mp-cc-cart-list__empty-message">' + escapeHtml(String(config.empty_state.message)) + '</p>';
			}
			if (emptyCtaEnabled) {
				html += '<a class="mp-cc-cart-list__cta" href="' + escapeHtml(catalogUrl) + '">' + escapeHtml(getStepOneLabel(state, 'return_label', 'step_1.return_to_shop', 'Return to catalog')) + '</a>';
			}
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
			if (visibility.show_image !== false && imageUrl) {
				html += '<img src="' + escapeHtml(imageUrl) + '" alt="" loading="lazy" />';
			} else if (visibility.show_image !== false) {
				html += '<div class="mp-cc-cart-item__placeholder" aria-hidden="true"></div>';
			}
			html += '</div>';
			html += '<div class="mp-cc-cart-item__body">';
			html += '<h3 class="mp-cc-cart-item__title" title="' + escapeHtml(title) + '">' + escapeHtml(title) + '</h3>';
			var topBlocks = {};
			topBlocks.price = visibility.show_price !== false && priceHtml ? '<div class="mp-cc-cart-item__price" data-secondary-block="price">' + priceHtml + '</div>' : '';
			topBlocks.sku = visibility.show_sku !== false && sku ? '<div class="mp-cc-cart-item__meta-row" data-secondary-block="sku"><dt>SKU</dt><dd>' + escapeHtml(sku) + '</dd></div>' : '';
			topBlocks.variation = visibility.show_variation !== false && variationText ? '<div class="mp-cc-cart-item__meta-row" data-secondary-block="variation"><dt>' + escapeHtml(getUiText('step_1.positions_count', 'Details')) + '</dt><dd>' + escapeHtml(variationText) + '</dd></div>' : '';
			for (var t = 0; t < order.length; t += 1) {
				var topKey = String(order[t] || '');
				if (topKey === 'price' && topBlocks.price) {
					html += topBlocks.price;
				}
			}
			var metaRows = '';
			for (var m = 0; m < order.length; m += 1) {
				var metaKey = String(order[m] || '');
				if (metaKey === 'sku' || metaKey === 'variation') {
					metaRows += topBlocks[metaKey] || '';
				}
			}
			if (metaRows) {
				html += '<dl class="mp-cc-cart-item__meta">' + metaRows + '</dl>';
			}
			html += '<div class="mp-cc-cart-item__footer">';
			var secondaryBlocks = {};
			secondaryBlocks.quantity = '';
			if (quantityControls.enabled !== false) {
				secondaryBlocks.quantity += '<div class="mp-cc-cart-item__qty-controls" data-secondary-block="quantity" role="group" aria-label="' + escapeHtml(getUiText('step_1.positions_count', 'Quantity')) + '">';
				if (quantityControls.show_decrement !== false) {
					secondaryBlocks.quantity += '<button type="button" class="mp-cc-qty-btn" data-qty-action="decrease" data-cart-qty-btn="-1" aria-label="Decrease quantity"' + (qty <= minQty ? ' disabled' : '') + '>−</button>';
				}
				if (quantityControls.allow_manual_input !== false) {
					secondaryBlocks.quantity += '<input class="mp-cc-qty-input" type="number" inputmode="numeric" min="' + escapeHtml(minQty) + '" max="' + escapeHtml(maxQty) + '" step="1" value="' + escapeHtml(qty) + '" data-cart-qty-input="1" aria-label="Quantity" />';
				} else {
					secondaryBlocks.quantity += '<span class="mp-cc-qty-static">' + escapeHtml(qty) + '</span>';
				}
				if (quantityControls.show_increment !== false) {
					secondaryBlocks.quantity += '<button type="button" class="mp-cc-qty-btn" data-qty-action="increase" data-cart-qty-btn="+1" aria-label="Increase quantity"' + (qty >= maxQty ? ' disabled' : '') + '>+</button>';
				}
				secondaryBlocks.quantity += '</div>';
			}
			secondaryBlocks.subtotal = visibility.show_subtotal !== false ? '<span class="mp-cc-cart-item__subtotal" data-secondary-block="subtotal">' + (subtotal || '—') + '</span>' : '';
			secondaryBlocks.remove = '<button type="button" class="mp-cc-cart-item__remove" data-secondary-block="remove" data-cart-remove="1" aria-label="Remove item">' + escapeHtml(getUiText('common.remove', 'Remove')) + '</button>';

			for (var o = 0; o < order.length; o += 1) {
				var blockKey = String(order[o] || '');
				if (secondaryBlocks[blockKey]) {
					html += secondaryBlocks[blockKey];
				}
			}
			html += '</div>';
			html += '</div>';
			html += '</article>';
		}

		html += '</div></section>';
		return html;
	}

	function buildNavHtml(state) {
		return '';
	}

	function buildSummaryHtml(state) {
		var currentIndex = getStepIndex(state.visibleSteps, state.currentStepId);
		var total = state.visibleSteps.length;
		var stepProg = getSummaryStepProgress(state);
		var snapshot = state.frontendStore && state.frontendStore.cart ? state.frontendStore.cart.snapshot || {} : {};
		var cartSummary = state.frontendStore && state.frontendStore.cart ? state.frontendStore.cart.summary || {} : {};
		var runtime = state.frontendStore && state.frontendStore.runtime ? state.frontendStore.runtime : {};
		var showPlaceholders = !runtime.summaryHydrated;
		var itemsCount = cartSummary.items_count || snapshot.items_count || 0;
		var subtotalText = cartSummary.subtotal || '';
		var totalText = cartSummary.total || snapshot.total || subtotalText || '';
		var displayAmount = state.currentStepId === 'address_delivery' ? subtotalText : totalText;
		var subtotalLineLabel = getStepOneLabel(state, 'subtotal_label', 'step_1.subtotal', 'Подытог');
		var totalLineLabel = getStepOneLabel(state, 'total_label', 'order_review.total', 'Итого');
		var amountLabel = state.currentStepId === 'address_delivery' ? subtotalLineLabel : totalLineLabel;
		var returnUrl = cartSummary.catalog_url ? String(cartSummary.catalog_url) : '/';
		var scenario = String(state.frontendStore && state.frontendStore.fulfillment ? (state.frontendStore.fulfillment.scenario || '') : '');
		var scenarioRules = getScenarioRulesById(scenario);
		var scenarioLabel = scenarioRules && scenarioRules.label ? String(scenarioRules.label) : '';
		var couponLines = Array.isArray(cartSummary.coupon_lines) ? cartSummary.coupon_lines : [];
		var giftCardCodes = Array.isArray(cartSummary.applied_gift_cards) ? cartSummary.applied_gift_cards : [];
		var giftCardLines = Array.isArray(cartSummary.gift_card_lines) ? cartSummary.gift_card_lines : [];
		var giftCardTotal = String(cartSummary.gift_card_total || '');
		var isPickup = scenario === 'pickup';
		var shippingTotalNum = typeof cartSummary.shipping_total === 'number' && !Number.isNaN(cartSummary.shipping_total)
			? cartSummary.shipping_total
			: null;
		if (shippingTotalNum === null) {
			shippingTotalNum = trimNonEmpty(cartSummary.shipping) ? 1 : 0;
		}
		var shippingText = shippingTotalNum > 0 ? String(cartSummary.shipping || '') : '';
		if (isPickup && shippingTotalNum <= 0) {
			shippingText = '';
		}
		var taxText = String(cartSummary.tax || '');
		var feeLines = Array.isArray(cartSummary.fee_lines) ? cartSummary.fee_lines : [];
		var shippingLabel = getStepOneLabel(state, 'shipping_label', 'order_review.shipping', 'Доставка');
		var discountLabel = getStepOneLabel(state, 'discount_label', 'order_review.discount', 'Скидка');
		var giftCardLabel = getStepOneLabel(state, 'gift_card_label', 'step_4.gift_card_title', 'Подарочная карта');
		var giftCardPrefixText = giftCardLabel;
		if (Array.isArray(giftCardCodes) && giftCardCodes.length) {
			giftCardPrefixText += ' ' + giftCardCodes.join(', ');
		}
		var taxLabel = getStepOneLabel(state, 'tax_label', 'order_review.tax', 'Налоги');
		var totalLabel = getStepOneLabel(state, 'total_label', 'order_review.total', 'Итого');
		var gatewayTitle = getSelectedGatewayTitle(state);
		var cartItems = state.frontendStore && state.frontendStore.cart && Array.isArray(state.frontendStore.cart.items) ? state.frontendStore.cart.items : [];
		var pickupPoint = state.frontendStore && state.frontendStore.fulfillment && state.frontendStore.fulfillment.scenarioData
			? (state.frontendStore.fulfillment.scenarioData.pickup_point || null)
			: null;
		var html = '';

		html += '<section class="mp-cc-summary-card mp-cc-summary-card--mobile-receipt" aria-label="Order summary panel">';
		html += '<h3 class="mp-cc-summary-card__title">' + escapeHtml(getStepOneLabel(state, 'summary_title', 'order_review.title', 'Order Summary')) + '</h3>';
		html += '<p class="mp-cc-summary-card__meta mp-cc-summary-card__meta--step">' + escapeHtml(formatCheckoutStepMeta(stepProg.cur, stepProg.total)) + '</p>';
		if (showPlaceholders) {
			html += '<div class="mp-cc-summary-card__placeholder" aria-hidden="true"></div>';
			html += '<div class="mp-cc-summary-card__placeholder mp-cc-summary-card__placeholder--sm" aria-hidden="true"></div>';
		} else {
			html += '<p class="mp-cc-summary-card__meta">' + escapeHtml(getStepOneLabel(state, 'items_label', 'step_1.positions_count', 'Items')) + ': <strong>' + escapeHtml(itemsCount) + '</strong></p>';
			if (displayAmount) {
				html += '<p class="mp-cc-summary-card__meta"><span class="mp-cc-summary-card__amount-label">' + escapeHtml(amountLabel) + ':</span> <span class="mp-cc-summary-card__amount" data-summary-amount="1">' + wcPriceHtmlFragment(displayAmount) + '</span></p>';
			}
		}
		if (state.currentStepId === 'address_delivery') {
			html += '<div class="mp-cc-summary-card__actions">';
			html += '<button type="button" class="mp-cc-summary-card__btn mp-cc-summary-card__btn--primary" data-summary-action="continue">' + escapeHtml(getStepOneLabel(state, 'continue_label', 'step_1.continue', 'Continue')) + '</button>';
			html += '<a href="' + escapeHtml(returnUrl) + '" class="mp-cc-summary-card__btn mp-cc-summary-card__btn--ghost">' + escapeHtml(getStepOneLabel(state, 'return_label', 'step_1.return_to_shop', 'Return to shop')) + '</a>';
			html += '</div>';
		}
		if (state.currentStepId !== 'confirm') {
			html += '<div class="mp-cc-summary-card__scenario" data-summary-financials="1">';
			html += '<p class="mp-cc-summary-card__scenario-title"><strong>' + escapeHtml(getUiText('order_review.financial', 'Итоги')) + '</strong></p>';
			if (trimNonEmpty(subtotalText)) {
				html += '<p class="mp-cc-summary-card__scenario-meta">' + escapeHtml(subtotalLineLabel) + ': <span class="mp-cc-summary-card__amount--inline" data-summary-amount="1">' + wcPriceHtmlFragment(subtotalText) + '</span></p>';
			}
			if (trimNonEmpty(shippingText)) {
				html += '<p class="mp-cc-summary-card__scenario-meta">' + escapeHtml(shippingLabel) + ': <span class="mp-cc-summary-card__amount--inline" data-summary-amount="1">' + wcPriceHtmlFragment(shippingText) + '</span></p>';
			}
			for (var fi = 0; fi < feeLines.length; fi += 1) {
				var feeRow = feeLines[fi] || {};
				var feeLabel = trimNonEmpty(feeRow.label) ? String(feeRow.label) : getUiText('order_review.fee_line', 'Сбор');
				html += '<p class="mp-cc-summary-card__scenario-meta">' + escapeHtml(feeLabel) + ': <span class="mp-cc-summary-card__amount--inline" data-summary-amount="1">' + wcPriceHtmlFragment(String(feeRow.amount || '')) + '</span></p>';
			}
			if (trimNonEmpty(cartSummary.discount)) {
				html += '<p class="mp-cc-summary-card__scenario-meta">' + escapeHtml(discountLabel) + ': <span class="mp-cc-summary-card__amount--inline" data-summary-amount="1">' + wcPriceHtmlFragment(String(cartSummary.discount)) + '</span></p>';
			}
			if (giftCardCodes.length || trimNonEmpty(giftCardTotal)) {
				html += '<p class="mp-cc-summary-card__scenario-meta">' + escapeHtml(giftCardPrefixText) + ': <span class="mp-cc-summary-card__amount--inline" data-summary-amount="1">' + wcPriceHtmlFragment(giftCardTotal || '—') + '</span></p>';
			}
			if (trimNonEmpty(taxText)) {
				html += '<p class="mp-cc-summary-card__scenario-meta">' + escapeHtml(taxLabel) + ': <span class="mp-cc-summary-card__amount--inline" data-summary-amount="1">' + wcPriceHtmlFragment(taxText) + '</span></p>';
			}
			html += buildCouponBlockHtml(state, { inSummary: true });
			if (trimNonEmpty(totalText)) {
				html += '<p class="mp-cc-summary-card__scenario-meta"><strong>' + escapeHtml(totalLabel) + ':</strong> <span class="mp-cc-summary-card__amount--inline" data-summary-amount="1">' + wcPriceHtmlFragment(totalText) + '</span></p>';
			}
			html += '</div>';
		}
		if (state.currentStepId === 'confirm') {
			html += '<div class="mp-cc-summary-card__scenario mp-cc-summary-card__scenario--final-review" data-final-review-block="1">';
			html += '<p class="mp-cc-summary-card__scenario-title"><strong>' + escapeHtml(getUiText('order_review.final_review_title', 'Сводка заказа')) + '</strong></p>';
			html += '<p class="mp-cc-summary-card__scenario-meta">' + escapeHtml(getUiText('order_review.final_review_lead', 'Проверьте данные и нажмите кнопку оплаты.')) + '</p>';
			html += '</div>';
			var contact = state.frontendStore && state.frontendStore.form ? (state.frontendStore.form.contact || {}) : {};
			var fullName = [contact.billing_last_name, contact.billing_first_name, contact.billing_patronymic]
				.filter(function (part) { return trimNonEmpty(part); })
				.join(' ');
			var gOpt = getGenderOptions();
			var genderLabel = String(contact.billing_gender || '') === 'male' ? gOpt.male : (String(contact.billing_gender || '') === 'female' ? gOpt.female : '');
			var contactAddress = [contact.country, contact.state, contact.city, contact.address_1, contact.address_2, contact.postcode]
				.filter(function (part) { return trimNonEmpty(part); })
				.join(', ');
			var hasContactReview = trimNonEmpty(fullName) || trimNonEmpty(genderLabel) || trimNonEmpty(contact.billing_birthdate) || trimNonEmpty(contact.billing_email) || trimNonEmpty(contact.billing_phone) || trimNonEmpty(contactAddress);
			if (hasContactReview) {
				html += '<div class="mp-cc-summary-card__scenario" data-final-review-contact="1">';
				html += '<p class="mp-cc-summary-card__scenario-title"><strong>' + escapeHtml(getUiText('step_4.title', 'Контактные данные')) + '</strong></p>';
				if (trimNonEmpty(fullName)) {
					html += '<p class="mp-cc-summary-card__scenario-meta"><strong>' + escapeHtml(getUiText('step_4.contact_first_name', 'Получатель')) + ':</strong> ' + escapeHtml(fullName) + '</p>';
				}
				if (trimNonEmpty(contact.billing_email)) {
					html += '<p class="mp-cc-summary-card__scenario-meta"><strong>' + escapeHtml(getUiText('step_4.contact_email', 'Email')) + ':</strong> ' + escapeHtml(String(contact.billing_email)) + '</p>';
				}
				if (trimNonEmpty(contact.billing_phone)) {
					html += '<p class="mp-cc-summary-card__scenario-meta"><strong>' + escapeHtml(getUiText('step_4.contact_phone', 'Телефон')) + ':</strong> ' + escapeHtml(String(contact.billing_phone)) + '</p>';
				}
				if (trimNonEmpty(genderLabel)) {
					html += '<p class="mp-cc-summary-card__scenario-meta"><strong>' + escapeHtml(getUiText('step_4.contact_gender', 'Пол')) + ':</strong> ' + escapeHtml(genderLabel) + '</p>';
				}
				if (trimNonEmpty(contact.billing_birthdate)) {
					html += '<p class="mp-cc-summary-card__scenario-meta"><strong>' + escapeHtml(getUiText('step_4.contact_birthdate', 'Дата рождения')) + ':</strong> ' + escapeHtml(formatIsoDateForUi(String(contact.billing_birthdate))) + '</p>';
				}
				if (trimNonEmpty(contactAddress)) {
					html += '<p class="mp-cc-summary-card__scenario-meta"><strong>' + escapeHtml(getUiText('step_4.address_block_title', 'Адрес')) + ':</strong> ' + escapeHtml(contactAddress) + '</p>';
				}
				html += '</div>';
			}
			var breakdownFragment = buildContactPaymentSummaryBreakdownHtml(state);
			if (trimNonEmpty(breakdownFragment)) {
				html += breakdownFragment;
			}
			if (cartItems.length) {
				html += '<div class="mp-cc-summary-card__scenario" data-final-review-cart="1">';
				html += '<p class="mp-cc-summary-card__scenario-title"><strong>' + escapeHtml(getUiText('order_review.items', 'Состав заказа')) + '</strong></p>';
				for (var ci = 0; ci < cartItems.length; ci += 1) {
					var item = cartItems[ci] || {};
					var rowTitle = String(item.name || getUiText('step_1.title', 'Товар'));
					var rowQty = Number(item.quantity || 0);
					var rowSubtotal = String(item.line_subtotal || '');
					html += '<p class="mp-cc-summary-card__scenario-meta">' + escapeHtml(rowTitle) + ' × ' + escapeHtml(String(rowQty)) + (rowSubtotal ? ' — ' + wcPriceHtmlFragment(rowSubtotal) : '') + '</p>';
				}
				html += '</div>';
			}
			html += '<div class="mp-cc-summary-card__scenario" data-final-review-financials="1">';
			html += '<p class="mp-cc-summary-card__scenario-title"><strong>' + escapeHtml(getUiText('order_review.financial', 'Итоги')) + '</strong></p>';
			if (trimNonEmpty(subtotalText)) {
				html += '<p class="mp-cc-summary-card__scenario-meta">' + escapeHtml(subtotalLineLabel) + ': ' + wcPriceHtmlFragment(subtotalText) + '</p>';
			}
			if (trimNonEmpty(shippingText)) {
				html += '<p class="mp-cc-summary-card__scenario-meta">' + escapeHtml(shippingLabel) + ': ' + wcPriceHtmlFragment(shippingText) + '</p>';
			}
			for (var fij = 0; fij < feeLines.length; fij += 1) {
				var feeRowJ = feeLines[fij] || {};
				var feeLabelJ = trimNonEmpty(feeRowJ.label) ? String(feeRowJ.label) : getUiText('order_review.fee_line', 'Сбор');
				html += '<p class="mp-cc-summary-card__scenario-meta">' + escapeHtml(feeLabelJ) + ': ' + wcPriceHtmlFragment(String(feeRowJ.amount || '')) + '</p>';
			}
			var hideAggDiscountInFinal = couponLines.length > 0;
			var hideGiftInFinalFinancials = giftCardLines.length > 0 || giftCardCodes.length > 0;
			if (!hideAggDiscountInFinal && trimNonEmpty(cartSummary.discount)) {
				html += '<p class="mp-cc-summary-card__scenario-meta">' + escapeHtml(discountLabel) + ': ' + wcPriceHtmlFragment(String(cartSummary.discount)) + '</p>';
			}
			if (!hideGiftInFinalFinancials && (giftCardCodes.length || trimNonEmpty(giftCardTotal))) {
				html += '<p class="mp-cc-summary-card__scenario-meta">' + escapeHtml(giftCardPrefixText) + ': ' + wcPriceHtmlFragment(giftCardTotal || '—') + '</p>';
			}
			if (trimNonEmpty(taxText)) {
				html += '<p class="mp-cc-summary-card__scenario-meta">' + escapeHtml(taxLabel) + ': ' + wcPriceHtmlFragment(taxText) + '</p>';
			}
			if (trimNonEmpty(totalText)) {
				html += '<p class="mp-cc-summary-card__scenario-meta"><strong>' + escapeHtml(totalLabel) + ':</strong> ' + wcPriceHtmlFragment(totalText) + '</p>';
			}
			html += '</div>';
			// По UI-задаче из сводки заказа убираем блок «Способ оплаты».
		}
		if (state.currentStepId === 'confirm' && scenarioLabel) {
			html += '<div class="mp-cc-summary-card__scenario" data-final-review-scenario="1">';
			html += '<p class="mp-cc-summary-card__scenario-title"><strong>' + escapeHtml(getUiText('step_2.title', 'Способ получения')) + ':</strong> ' + escapeHtml(scenarioLabel) + '</p>';
			var selectedDate = state.frontendStore && state.frontendStore.fulfillment && state.frontendStore.fulfillment.date
				? String(state.frontendStore.fulfillment.date.selected_date || '')
				: '';
			if (selectedDate) {
				html += '<p class="mp-cc-summary-card__scenario-meta"><strong>' + escapeHtml(getStepThreeTitle()) + ':</strong> ' + escapeHtml(formatIsoDateForUi(selectedDate)) + '</p>';
			}
			if (scenario === 'pickup' && pickupPoint && pickupPoint.title) {
				html += '<p class="mp-cc-summary-card__scenario-meta">' + escapeHtml(String(pickupPoint.title)) + '</p>';
				if (pickupPoint.address) {
					html += '<p class="mp-cc-summary-card__scenario-meta">' + escapeHtml(String(pickupPoint.address)) + '</p>';
				}
			}
			var receiptPlain = buildConditionsReceiptPlainText(state);
			if (receiptPlain) {
				html += '<div class="mp-cc-summary-card__conditions" data-final-review-conditions="1">';
				html += '<p class="mp-cc-summary-card__conditions-title"><strong>' + escapeHtml(getUiText('step_3.conditions_title', 'Условия получения')) + '</strong></p>';
				html += '<div class="mp-cc-summary-card__conditions-body">' + formatConditionsReceiptHtmlFromPlain(receiptPlain) + '</div>';
				html += '</div>';
			}
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
		var safeLevel = level === 'error' ? 'error' : (level === 'success' ? 'success' : 'info');
		var role = safeLevel === 'error' ? 'alert' : 'status';
		var live = safeLevel === 'error' ? 'assertive' : 'polite';
		container.innerHTML = '<div class="mp-cc-notice mp-cc-notice--' + safeLevel + '" role="' + role + '" aria-live="' + live + '" aria-atomic="true">' + safeMessage + '</div>';
	}

	function focusStepHeading($app) {
		var heading = $app.find('#mp-cc-step-heading').get(0);
		if (!heading || typeof heading.focus !== 'function') {
			return;
		}
		if (typeof heading.scrollIntoView === 'function') {
			try {
				heading.scrollIntoView({ block: 'nearest', inline: 'nearest' });
			} catch (e0) {
				// ignore
			}
		}
		try {
			heading.focus({ preventScroll: true });
		} catch (e) {
			heading.focus();
		}
	}

	function announceCheckoutStepFromDom($app) {
		var live = document.querySelector(selectors.a11yAnnouncer);
		if (!live) {
			return;
		}
		var title = $app.find('#mp-cc-step-heading').first().text().replace(/\s+/g, ' ').trim();
		if (!title) {
			return;
		}
		var meta = $app.find('.mp-cc-step-panel__meta').first().text().replace(/\s+/g, ' ').trim();
		var msg = (meta ? meta + ' — ' : '') + title;
		if (String(live.textContent || '') === msg) {
			return;
		}
		live.textContent = '';
		window.setTimeout(function () {
			live.textContent = msg;
		}, 40);
	}

	function scheduleFocusAndA11yAnnouncement($app, isStepChanged) {
		if (!isStepChanged) {
			return;
		}
		window.requestAnimationFrame(function () {
			window.requestAnimationFrame(function () {
				focusStepHeading($app);
				announceCheckoutStepFromDom($app);
			});
		});
	}

	function bindVisualViewportKeyboardInset() {
		if (viewportKeyboardBound || !window.visualViewport || !window.matchMedia) {
			return;
		}
		var root = document.querySelector(selectors.root);
		if (!root) {
			return;
		}
		viewportKeyboardBound = true;
		var vv = window.visualViewport;
		var apply = function () {
			if (!window.matchMedia('(max-width: 767px)').matches) {
				root.style.setProperty('--mp-cc-keyboard-inset', '0px');
				return;
			}
			var overlap = Math.max(0, window.innerHeight - vv.height - vv.offsetTop);
			root.style.setProperty('--mp-cc-keyboard-inset', overlap + 'px');
		};
		vv.addEventListener('resize', apply, { passive: true });
		vv.addEventListener('scroll', apply, { passive: true });
		window.addEventListener('orientationchange', apply, { passive: true });
		apply();
	}

	function runStepTransitionAnimation($app) {
		var m = getMotionConfig();
		if (m.toggles && m.toggles.step_transition_overlay === false) {
			return;
		}
		if (useCheckoutReducedMotion()) {
			return;
		}
		if (shouldThrottleMotion('step_transition')) {
			return;
		}
		var t0 = (window.performance && performance.now) ? performance.now() : Date.now();
		window.clearTimeout(stepTransitionTimer);
		window.requestAnimationFrame(function () {
			$app.addClass('is-step-transition');
		});
		var d = getEffectiveMotionDurations().step_transition;
		var ms = typeof d === 'number' && !Number.isNaN(d) ? Math.max(0, Math.round(Number(d))) : animationDurationMs;
		stepTransitionTimer = window.setTimeout(function () {
			$app.removeClass('is-step-transition');
			var t1 = (window.performance && performance.now) ? performance.now() : Date.now();
			logMotionInstrumentation('step_transition_overlay', t1 - t0);
		}, ms);
	}

	function applyStepOnePresentation(state) {
		var root = document.querySelector(selectors.root);
		if (!root || !state || !state.stepOneConfig) {
			return;
		}
		var cfg = state.stepOneConfig;
		var styles = cfg.style_controls || {};
		var responsive = cfg.responsive || {};
		root.classList.toggle('mp-cc-step1-card-compact', Boolean(styles.card_compact));
		root.classList.toggle('mp-cc-step1-hide-media-mobile', Boolean(responsive.hide_media_mobile));
		root.classList.toggle('mp-cc-step1-card-emphasis-elevated', String(styles.card_emphasis || '') === 'elevated');
		root.classList.toggle('mp-cc-step1-summary-emphasis-elevated', String(styles.summary_emphasis || '') === 'elevated');

		var responsiveClasses = [
			'mp-cc-step1-desktop-comfortable', 'mp-cc-step1-desktop-compact',
			'mp-cc-step1-tablet-comfortable', 'mp-cc-step1-tablet-compact',
			'mp-cc-step1-mobile-comfortable', 'mp-cc-step1-mobile-compact'
		];
		for (var i = 0; i < responsiveClasses.length; i += 1) {
			root.classList.remove(responsiveClasses[i]);
		}
		root.classList.add('mp-cc-step1-desktop-' + (responsive.desktop_mode === 'compact' ? 'compact' : 'comfortable'));
		root.classList.add('mp-cc-step1-tablet-' + (responsive.tablet_mode === 'compact' ? 'compact' : 'comfortable'));
		root.classList.add('mp-cc-step1-mobile-' + (responsive.mobile_mode === 'comfortable' ? 'comfortable' : 'compact'));
	}

	function logPickupMapIssue(state, code, message) {
		var cfg = getPickupMapConfig();
		if (!cfg.diagnosticsEnabled) {
			return;
		}
		var cacheKey = String(code || '') + '|' + String(message || '');
		if (pickupMapLogCache[cacheKey]) {
			return;
		}
		pickupMapLogCache[cacheKey] = true;
		reportClientError(String(code || 'pickup_map_issue'), String(message || 'pickup_map_error'), '', 'pickup_map');
		postCheckout('validation_log', {
			step_id: 'pickup_map',
			context_id: state && state.flowContextId ? state.flowContextId : '',
			errors: {
				map_error: String(code || 'unknown')
			}
		});
	}

	function setPickupMapFallback($root, title, message) {
		var $fallback = $root.find('[data-pickup-map-fallback]');
		$fallback.find('.mp-cc-pickup-map__fallback-title').text(String(title || 'Карта временно недоступна'));
		$fallback.find('.mp-cc-pickup-map__fallback-message').text(String(message || 'Посмотрите адрес пункта самовывоза выше.'));
		$fallback.prop('hidden', false);
	}

	function ensureYandexMapsApi(state) {
		var cfg = getPickupMapConfig();
		if (cfg.provider !== 'yandex') {
			return $.Deferred().reject(new Error('unsupported_map_provider')).promise();
		}
		if (window.ymaps && typeof window.ymaps.ready === 'function') {
			return $.Deferred().resolve(window.ymaps).promise();
		}
		if (pickupMapScriptPromise) {
			return pickupMapScriptPromise;
		}
		var deferred = $.Deferred();
		pickupMapScriptPromise = deferred.promise();
		var script = document.createElement('script');
		var src = 'https://api-maps.yandex.ru/2.1/?lang=ru_RU';
		if (cfg.apiKey) {
			src += '&apikey=' + encodeURIComponent(cfg.apiKey);
		}
		script.src = src;
		script.async = true;
		script.onerror = function () {
			logPickupMapIssue(state, 'pickup_map_script_failed', 'Не удалось загрузить скрипт Яндекс.Карт.');
			deferred.reject(new Error('yandex_maps_script_failed'));
		};
		script.onload = function () {
			if (!window.ymaps || typeof window.ymaps.ready !== 'function') {
				logPickupMapIssue(state, 'pickup_map_api_missing', 'API Яндекс.Карт не инициализировано.');
				deferred.reject(new Error('yandex_maps_api_missing'));
				return;
			}
			window.ymaps.ready(function () {
				deferred.resolve(window.ymaps);
			});
		};
		document.head.appendChild(script);
		return pickupMapScriptPromise;
	}

	function mountPickupMaps(state, $app) {
		var cfg = getPickupMapConfig();
		if (!cfg.enabled) {
			return;
		}
		var $roots = $app.find('[data-pickup-map-root]');
		if (!$roots.length) {
			return;
		}
		$roots.each(function () {
			var $root = $(this);
			var $canvas = $root.find('[data-pickup-map-canvas]');
			if (!$canvas.length || $root.attr('data-map-mounted') === '1') {
				return;
			}
			var desktopHeight = Number($root.attr('data-map-height-desktop') || cfg.desktopHeight);
			var mobileHeight = Number($root.attr('data-map-height-mobile') || cfg.mobileHeight);
			var mapHeight = window.matchMedia('(max-width: 767px)').matches ? mobileHeight : desktopHeight;
			$canvas.css('height', String(Math.max(120, mapHeight)) + 'px');
			ensureYandexMapsApi(state).then(function (ymaps) {
				try {
					var lat = Number($root.attr('data-map-lat') || cfg.centerLat);
					var lng = Number($root.attr('data-map-lng') || cfg.centerLng);
					var zoom = Number($root.attr('data-map-zoom') || cfg.zoom);
					var markerLabel = String($root.attr('data-map-marker-label') || cfg.markerLabel);
					var markerHint = String($root.attr('data-map-marker-hint') || cfg.markerHint);
					var map = new ymaps.Map($canvas.get(0), {
						center: [lat, lng],
						zoom: zoom,
						controls: ['zoomControl']
					});
					var placemark = new ymaps.Placemark(
						[lat, lng],
						{
							balloonContentHeader: markerLabel,
							balloonContentBody: markerHint,
							hintContent: markerLabel
						},
						{
							preset: 'islands#redDotIcon'
						}
					);
					map.geoObjects.add(placemark);
					$root.attr('data-map-mounted', '1');
				} catch (mapErr) {
					logPickupMapIssue(state, 'pickup_map_render_failed', mapErr && mapErr.message ? mapErr.message : 'Ошибка рендера карты.');
					setPickupMapFallback(
						$root,
						String($root.attr('data-map-fallback-title') || cfg.fallbackTitle),
						String($root.attr('data-map-fallback-message') || cfg.fallbackMessage)
					);
				}
			}).fail(function () {
				setPickupMapFallback(
					$root,
					String($root.attr('data-map-fallback-title') || cfg.fallbackTitle),
					String($root.attr('data-map-fallback-message') || cfg.fallbackMessage)
				);
			});
		});
	}

	function render(state, $app) {
		window.__mpCcCheckoutContextId = state && state.flowContextId ? String(state.flowContextId) : '';
		ensureV2ScreenState(state);
		var $parcel = $(selectors.parcel);
		var $progress = $(selectors.progress);
		var $actions = $(selectors.actions);
		var $summary = $(selectors.summary);
		state.__renderCache = state.__renderCache || { parcelHtml: '', stepHtml: '', summaryHtml: '', progressHtml: '', actionsHtml: '' };

		if (!state.visibleSteps.length) {
			$app.html('<p class="mp-cc-empty">No steps available.</p>');
			$parcel.empty();
			$progress.empty();
			$actions.empty();
			$summary.empty();
			return;
		}
		ensureDateSelection(state);
		ensureContactDefaults(state);
		ensureDiscountDefaults(state);
		applyMotionFromState(state);

		var nextParcelHtml = '';
		var nextStepHtml = '';
		var nextSummaryHtml = '';
		try {
			nextParcelHtml = buildParcelHeaderHtml(state);
		} catch (parcelErr) {
			reportClientError('render_parcel_failed', parcelErr && parcelErr.message ? parcelErr.message : 'parcel_render_failed', parcelErr && parcelErr.stack ? parcelErr.stack : '', 'render');
			nextParcelHtml = '';
		}
		try {
			nextStepHtml = buildStepPanelHtml(state);
		} catch (stepErr) {
			reportClientError('render_step_panel_failed', stepErr && stepErr.message ? stepErr.message : 'step_render_failed', stepErr && stepErr.stack ? stepErr.stack : '', 'render');
			nextStepHtml = '<section class="mp-cc-step-panel"><p class="mp-cc-empty">' + escapeHtml(getUiText('common.render_fallback', 'Часть интерфейса временно недоступна. Попробуйте обновить страницу.')) + '</p></section>';
		}
		try {
			nextSummaryHtml = buildSummaryHtml(state);
		} catch (summaryErr) {
			reportClientError('render_summary_failed', summaryErr && summaryErr.message ? summaryErr.message : 'summary_render_failed', summaryErr && summaryErr.stack ? summaryErr.stack : '', 'render');
			nextSummaryHtml = '<section class="mp-cc-summary-card"><p>' + escapeHtml(getUiText('common.summary_fallback', 'Сводка временно недоступна.')) + '</p></section>';
		}
		var nextProgressHtml = '';
		var nextActionsHtml = '';
		var isParcelChanged = state.__renderCache.parcelHtml !== nextParcelHtml;
		var isStepChanged = state.__renderCache.stepHtml !== nextStepHtml;
		var isSummaryChanged = state.__renderCache.summaryHtml !== nextSummaryHtml;
		var isProgressChanged = false;
		var isActionsChanged = false;
		if (isFlagEnabled(state, flagNames.multiStepFlow, true)) {
			try {
				nextProgressHtml = buildProgressHtml(state);
			} catch (progressErr) {
				reportClientError('render_progress_failed', progressErr && progressErr.message ? progressErr.message : 'progress_render_failed', progressErr && progressErr.stack ? progressErr.stack : '', 'render');
				nextProgressHtml = '';
			}
			try {
				nextActionsHtml = buildNavHtml(state);
			} catch (actionsErr) {
				reportClientError('render_actions_failed', actionsErr && actionsErr.message ? actionsErr.message : 'actions_render_failed', actionsErr && actionsErr.stack ? actionsErr.stack : '', 'render');
				nextActionsHtml = '';
			}
			isProgressChanged = state.__renderCache.progressHtml !== nextProgressHtml;
			isActionsChanged = state.__renderCache.actionsHtml !== nextActionsHtml;
		}
		if (isParcelChanged) {
			$parcel.html(nextParcelHtml);
			state.__renderCache.parcelHtml = nextParcelHtml;
		}
		var $shellParcel = $(selectors.shellParcelBadge);
		if ($shellParcel.length) {
			$shellParcel.html(buildShellParcelBadgeHtml(state));
		}
		if (isStepChanged) {
			$app.html(nextStepHtml);
			state.__renderCache.stepHtml = nextStepHtml;
		}
		if (isSummaryChanged) {
			$summary.html(nextSummaryHtml);
			state.__renderCache.summaryHtml = nextSummaryHtml;
			animateSummaryUpdate(state, $summary);
		}
		applyStepOnePresentation(state);
		if (isFlagEnabled(state, flagNames.multiStepFlow, true)) {
			if (isProgressChanged) {
				$progress.html(nextProgressHtml);
				state.__renderCache.progressHtml = nextProgressHtml;
			}
			if (isActionsChanged) {
				$actions.html(nextActionsHtml);
				state.__renderCache.actionsHtml = nextActionsHtml;
			}
		} else {
			$progress.empty();
			$actions.empty();
			state.__renderCache.progressHtml = '';
			state.__renderCache.actionsHtml = '';
		}
		if (isParcelChanged || isStepChanged || isSummaryChanged || isProgressChanged || isActionsChanged) {
			bindHandlers(state, $app, $progress, $actions);
		}
		if (isStepChanged) {
			scheduleFocusAndA11yAnnouncement($app, true);
			initFieldErrorMotion($app);
		}

		window.setTimeout(function () {
			mountPaymentGatewayFields(state, $app);
		}, 0);

		document.dispatchEvent(
			new CustomEvent('mp_cc_store_synced', {
				detail: {
					contextId: state.flowContextId,
					store: state.frontendStore
				}
			})
		);
	}

	function applyShippingMethodUserChoice(state, $app, methodId) {
		methodId = String(methodId || '');
		if (!methodId) {
			return;
		}
		// Защита от двойных кликов / параллельных AJAX-цепочек по способу доставки.
		if (shippingMutationInFlight) {
			return;
		}
		var methods = getV2ShippingCatalog(state);
		var dateBox = state.frontendStore && state.frontendStore.fulfillment ? (state.frontendStore.fulfillment.date || {}) : {};
		var selectedMethod = null;
		for (var mi = 0; mi < methods.length; mi += 1) {
			if (String(methods[mi].id || '') === methodId) {
				selectedMethod = methods[mi];
				break;
			}
		}
		var hasTariffs = !!(selectedMethod && Array.isArray(selectedMethod.tariffs) && selectedMethod.tariffs.length);
		var preferredTariffId = '';
		var previousTariffForMethod = '';
		if (hasTariffs && String(dateBox.shipping_method_id || '') === methodId) {
			previousTariffForMethod = String(dateBox.shipping_tariff_id || '');
			preferredTariffId = previousTariffForMethod;
		}
		var selection = resolveShippingSelection(methods, methodId, preferredTariffId);
		if (!selection) {
			notify(getShippingErrorCopy().methodUnavailable, 'error');
			return;
		}
		if (hasTariffs && !previousTariffForMethod) {
			selection.tariff_id = '';
			selection.tariff_title = '';
			selection.price = Number(selectedMethod.price || 0);
			selection.eta = String(selectedMethod.eta || '');
		}
		var nextScenario = scenarioByShippingMethod(methodId);
		var currentScenario = normalizeScenarioId(state.frontendStore && state.frontendStore.fulfillment ? (state.frontendStore.fulfillment.scenario || '') : '');
		if (nextScenario !== currentScenario) {
			resetDependentStateForScenario(state, nextScenario);
		}
		state.frontendStore.fulfillment.date = state.frontendStore.fulfillment.date && typeof state.frontendStore.fulfillment.date === 'object'
			? state.frontendStore.fulfillment.date
			: {};
		applyShippingSelectionToState(state, selection);
		invalidateV2DownstreamFrom(state, 0);
		render(state, $app);

		shippingMutationInFlight = true;
		var release = function () { shippingMutationInFlight = false; };
		var scenarioRequest = postCheckout('session_set_scenario', {
			scenario: nextScenario,
			context_id: state.flowContextId
		});
		if (!hasTariffs || selection.tariff_id) {
			scenarioRequest.then(function () {
				return postCheckout('session_set_answers', {
					step_id: 'address_delivery',
					context_id: state.flowContextId,
					answers: state.frontendStore.fulfillment.date || {}
				});
			}).then(function () {
				// Авто-переход выполняет setCurrentStep, который сам синхронизирует state из ответа.
				// Параллельный syncStoreWithBackend здесь создаёт гонку (старый снапшот перезаписывает выбор).
				tryAutoAdvanceAddressStep(state, $app);
			}).fail(function () {
				notify('Не удалось сохранить шаг доставки.', 'error');
				// При ошибке восстанавливаем состояние из бэкенда, чтобы UI не остался рассинхронизированным.
				syncStoreWithBackend(state, $app);
			}).always(release);
		} else {
			// Метод требует выбора тарифа — ждём клика по тарифу, ничего больше не отправляем.
			scenarioRequest.fail(function () {
				notify('Не удалось сохранить способ доставки.', 'error');
				syncStoreWithBackend(state, $app);
			}).always(function () {
				release();
				if (pendingShippingTariffChoice && String(pendingShippingTariffChoice.methodId || '') === methodId) {
					var queued = pendingShippingTariffChoice;
					pendingShippingTariffChoice = null;
					applyShippingTariffUserChoice(state, $app, queued.methodId, queued.tariffId);
				}
			});
		}
	}

	function applyShippingTariffUserChoice(state, $app, methodId, tariffId) {
		methodId = String(methodId || '');
		tariffId = String(tariffId || '');
		if (!methodId || !tariffId) {
			return;
		}
		if (shippingMutationInFlight) {
			pendingShippingTariffChoice = { methodId: methodId, tariffId: tariffId };
			return;
		}
		pendingShippingTariffChoice = null;
		var methods = getV2ShippingCatalog(state);
		var selection = resolveShippingSelection(methods, methodId, tariffId);
		if (!selection) {
			notify(getShippingErrorCopy().tariffUnavailable, 'error');
			return;
		}
		applyShippingSelectionToState(state, selection);
		invalidateV2DownstreamFrom(state, 0);
		render(state, $app);
		shippingMutationInFlight = true;
		postCheckout('session_set_answers', {
			step_id: 'address_delivery',
			context_id: state.flowContextId,
			answers: state.frontendStore.fulfillment.date || {}
		}).then(function () {
			tryAutoAdvanceAddressStep(state, $app);
		}).fail(function () {
			notify('Не удалось сохранить тариф доставки.', 'error');
			syncStoreWithBackend(state, $app);
		}).always(function () {
			shippingMutationInFlight = false;
			if (pendingShippingTariffChoice) {
				var queued = pendingShippingTariffChoice;
				pendingShippingTariffChoice = null;
				applyShippingTariffUserChoice(state, $app, queued.methodId, queued.tariffId);
			}
		});
	}

	function bindHandlers(state, $app, $progress, $actions) {
		$(selectors.exit).off('click').on('click', function () {
			var homeUrl = '/';
			if (window.mpCcCheckout && window.mpCcCheckout.initialContext && window.mpCcCheckout.initialContext.home_url) {
				homeUrl = String(window.mpCcCheckout.initialContext.home_url);
			}
			window.location.href = homeUrl;
		});
		mountPickupMaps(state, $app);

		$(selectors.root).off('click.mpCcRemoveCoupon', '[data-coupon-remove]').on('click.mpCcRemoveCoupon', '[data-coupon-remove]', function () {
			if (!isCouponRemoveAllowed()) {
				return;
			}
			var raw = String($(this).attr('data-code') || '');
			var rmCode = trimNonEmpty(raw);
			if (!rmCode) {
				return;
			}
			postCheckout('remove_coupon', {
				coupon_code: rmCode,
				context_id: state.flowContextId
			}).then(function (response) {
				var data = response && response.data ? response.data : {};
				if (data.flow || data.cart) {
					syncFromFlow(state, data.flow || {}, data.cart || {});
				}
				render(state, $app);
			}).fail(function (xhr) {
				var payload = xhr && xhr.responseJSON && xhr.responseJSON.data ? xhr.responseJSON.data : {};
				if (payload.flow || payload.cart) {
					syncFromFlow(state, payload.flow || {}, payload.cart || {});
				}
				render(state, $app);
				notify(trimNonEmpty(payload.message) || getUiText('step_4.coupon_remove_error', 'Не удалось снять купон.'), 'error');
			});
		});

		$(selectors.root).off('click.mpCcRemoveGift', '[data-gift-card-remove]').on('click.mpCcRemoveGift', '[data-gift-card-remove]', function () {
			if (!isGiftCardRemoveAllowed()) {
				return;
			}
			var raw = String($(this).attr('data-code') || '');
			var rmCode = trimNonEmpty(raw);
			if (!rmCode) {
				return;
			}
			postCheckout('remove_gift_card', {
				gift_card_code: rmCode,
				context_id: state.flowContextId
			}).then(function (response) {
				var data = response && response.data ? response.data : {};
				if (data.flow || data.cart) {
					syncFromFlow(state, data.flow || {}, data.cart || {});
				}
				var discountsAfter = state.frontendStore.discounts || {};
				var grtClear = discountsAfter.gift_card_runtime || { code: '', state: 'empty', message: '' };
				grtClear.state = 'empty';
				grtClear.message = '';
				grtClear.code = '';
				discountsAfter.gift_card_runtime = grtClear;
				state.frontendStore.discounts = discountsAfter;
				render(state, $app);
			}).fail(function (xhr) {
				var payload = xhr && xhr.responseJSON && xhr.responseJSON.data ? xhr.responseJSON.data : {};
				if (payload.flow || payload.cart) {
					syncFromFlow(state, payload.flow || {}, payload.cart || {});
				}
				render(state, $app);
				notify(trimNonEmpty(payload.message) || getUiText('step_4.gift_card_remove_error', 'Не удалось снять подарочную карту.'), 'error');
			});
		});

		if (!isFlagEnabled(state, flagNames.multiStepFlow, true)) {
			return;
		}

		$app.find('.mp-cc-step-card__cta').off('click').on('click', function () {
			saveCurrentStepDraft(state).always(function () {
				moveForward(state, $app);
			});
		});
		$app.find('.mp-cc-step-card__head[data-step-open]').off('click').on('click', function () {
			var targetStep = String($(this).attr('data-step-open') || '');
			if (!targetStep) {
				return;
			}
			var targetIndex = getStepIndex(state.visibleSteps, targetStep);
			if (targetIndex < 0 || targetIndex > state.maxReachedIndex) {
				return;
			}
			setCurrentStep(state, $app, targetStep);
		});

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
			if (isV2CheckoutUiEnabled(state)) {
				var v2Index = Number($(this).attr('data-step-index'));
				if (!Number.isFinite(v2Index)) {
					return;
				}
				if (v2Index < 0 || v2Index > state.v2MaxReachedIndex) {
					return;
				}
				setCurrentV2Screen(state, $app, v2Index);
				return;
			}
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
		$progress.find('.mp-cc-progress__btn').off('keydown').on('keydown', function (event) {
			var key = String(event.key || '');
			if (key !== 'ArrowRight' && key !== 'ArrowLeft' && key !== 'ArrowDown' && key !== 'ArrowUp' && key !== 'Home' && key !== 'End') {
				return;
			}
			var $buttons = $progress.find('.mp-cc-progress__btn');
			var idx = $buttons.index(this);
			if (idx < 0) {
				return;
			}
			var next = idx;
			if (key === 'ArrowRight' || key === 'ArrowDown') {
				next = Math.min($buttons.length - 1, idx + 1);
			} else if (key === 'ArrowLeft' || key === 'ArrowUp') {
				next = Math.max(0, idx - 1);
			} else if (key === 'Home') {
				next = 0;
			} else if (key === 'End') {
				next = $buttons.length - 1;
			}
			event.preventDefault();
			var $next = $buttons.eq(next);
			if ($next.length) {
				$next.trigger('focus');
			}
		});

		$app.find('[data-cart-qty-btn]').off('click').on('click', function () {
			var $btn = $(this);
			var $item = $btn.closest('[data-cart-item-key]');
			if (!$item.length) {
				return;
			}
			var localItem = getLocalCartItem(state, String($item.data('cart-item-key') || ''));
			var current = Number(localItem && localItem.quantity ? localItem.quantity : 0);
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
		$app.find('[data-cart-qty-input]').off('input').on('input', function () {
			var $input = $(this);
			var $item = $input.closest('[data-cart-item-key]');
			var itemKey = String($item.data('cart-item-key') || '');
			if (!$item.length || !itemKey) {
				return;
			}
			window.clearTimeout(qtyInputDebounceTimers[itemKey] || 0);
			qtyInputDebounceTimers[itemKey] = window.setTimeout(function () {
				applyQuantityChange(state, $app, $item, Number($input.val() || 0));
			}, 220);
		});

		$app.find('[data-cart-remove]').off('click').on('click', function () {
			var $btn = $(this);
			var $item = $btn.closest('[data-cart-item-key]');
			if (!$item.length) {
				return;
			}
			applyRemoveItem(state, $app, $item);
		});

		$app.find('[data-scenario-card]').off('click').on('click', function () {
			var targetScenario = String($(this).data('scenario-target') || '');
			if (!targetScenario) {
				return;
			}
			targetScenario = normalizeScenarioId(targetScenario);
			if (String(state.frontendStore.fulfillment.scenario || '') === targetScenario) {
				return;
			}
			resetDependentStateForScenario(state, targetScenario);
			invalidateV2DownstreamFrom(state, 0);
			render(state, $app);
			document.dispatchEvent(
				new CustomEvent('mp_cc_scenario_changed', {
					detail: { scenario: targetScenario }
				})
			);
		});

		$app.find('[data-scenario-card]').off('keydown').on('keydown', function (event) {
			var key = event.key || '';
			var $cards = $app.find('[data-scenario-card]');
			var currentIndex = $cards.index(this);
			var nextIndex = currentIndex;
			if (key === 'ArrowRight' || key === 'ArrowDown') {
				nextIndex = Math.min($cards.length - 1, currentIndex + 1);
				event.preventDefault();
			} else if (key === 'ArrowLeft' || key === 'ArrowUp') {
				nextIndex = Math.max(0, currentIndex - 1);
				event.preventDefault();
			} else if (key === ' ' || key === 'Enter') {
				$(this).trigger('click');
				event.preventDefault();
				return;
			} else {
				return;
			}
			var $next = $cards.eq(nextIndex);
			if ($next.length) {
				$next.trigger('click');
				$next.trigger('focus');
			}
		});

		// Радио: только change (иначе click+change = двойной вызов и гонки AJAX).
		$app.find('[data-ship-method]').off('change.mpCcShipMethod').on('change.mpCcShipMethod', function () {
			applyShippingMethodUserChoice(state, $app, String($(this).data('ship-method') || ''));
		});
		$app.find('[data-shipping-method]').off('click.mpCcShipMethod').on('click.mpCcShipMethod', function () {
			applyShippingMethodUserChoice(state, $app, String($(this).data('shipping-method') || ''));
		});

		$app.find('[data-ship-tariff]').off('change.mpCcShipTariff').on('change.mpCcShipTariff', function () {
			applyShippingTariffUserChoice(
				state,
				$app,
				String($(this).data('ship-tariff-method') || ''),
				String($(this).data('ship-tariff') || '')
			);
		});
		$app.find('[data-shipping-tariff]').off('click.mpCcShipTariff').on('click.mpCcShipTariff', function () {
			applyShippingTariffUserChoice(
				state,
				$app,
				String($(this).data('shipping-method-owner') || ''),
				String($(this).data('shipping-tariff') || '')
			);
		});

		$app.find('[data-city-edit]').off('click').on('click', function () {
			state.frontendStore.runtime = state.frontendStore.runtime || {};
			state.frontendStore.runtime.step1_city_editing = true;
			render(state, $app);
			$app.find('[data-city-input]').trigger('focus');
		});

		$app.find('[data-city-input]').off('keydown blur change').on('keydown blur change', function (event) {
			if (event.type === 'keydown' && event.key !== 'Enter') {
				return;
			}
			if (event.type === 'keydown') {
				event.preventDefault();
			}
			var rawCity = trimNonEmpty($(this).val());
			var city = rawCity || '';
			state.frontendStore.form = state.frontendStore.form || {};
			state.frontendStore.form.contact = state.frontendStore.form.contact || {};
			if (city) {
				state.frontendStore.form.contact.city = city;
			} else {
				delete state.frontendStore.form.contact.city;
			}
			state.frontendStore.fulfillment = state.frontendStore.fulfillment || {};
			state.frontendStore.fulfillment.scenarioData = state.frontendStore.fulfillment.scenarioData || {};
			var scenarioData = state.frontendStore.fulfillment.scenarioData;
			var pickupPoint = scenarioData.pickup_point && typeof scenarioData.pickup_point === 'object' ? scenarioData.pickup_point : getPickupPointById('');
			if (pickupPoint && city) {
				pickupPoint = $.extend({}, pickupPoint, { city: city });
				scenarioData.pickup_point = pickupPoint;
			}
			state.frontendStore.runtime = state.frontendStore.runtime || {};
			state.frontendStore.runtime.step1_city_editing = false;
			render(state, $app);
			postCheckout('session_set_answers', {
				step_id: 'contact_payment',
				context_id: state.flowContextId,
				answers: state.frontendStore.form.contact || {}
			}).fail(function () {
				notify('Не удалось сохранить город.', 'error');
			});
			postCheckout('session_set_answers', {
				step_id: 'scenario',
				context_id: state.flowContextId,
				answers: scenarioData
			}).fail(function () {
				notify('Не удалось сохранить город пункта выдачи.', 'error');
			});
		});

		$app.find('[data-pvz-edit]').off('click').on('click', function () {
			var pickupCfg = getPickupConfig();
			var points = pickupCfg.points || [];
			if (points.length <= 1) {
				return;
			}
			state.frontendStore.runtime = state.frontendStore.runtime || {};
			state.frontendStore.runtime.step1_pvz_editing = true;
			render(state, $app);
		});

		$app.find('[data-pvz-point]').off('change').on('change', function () {
			var pointId = String($(this).data('pvz-point') || '');
			if (!pointId) {
				return;
			}
			var point = getPickupPointById(pointId);
			if (!point) {
				return;
			}
			state.frontendStore.fulfillment = state.frontendStore.fulfillment || {};
			state.frontendStore.fulfillment.scenarioData = state.frontendStore.fulfillment.scenarioData || {};
			state.frontendStore.fulfillment.scenarioData.pickup_point = point;
			state.frontendStore.runtime = state.frontendStore.runtime || {};
			state.frontendStore.runtime.step1_pvz_editing = false;
			render(state, $app);
			postCheckout('session_set_answers', {
				step_id: 'scenario',
				context_id: state.flowContextId,
				answers: state.frontendStore.fulfillment.scenarioData || {}
			}).fail(function () {
				notify('Не удалось сохранить адрес ПВЗ.', 'error');
			});
			tryAutoAdvanceAddressStep(state, $app);
		});

		$app.find('[data-pickup-point]').off('click').on('click', function () {
			var pointId = String($(this).data('pickup-point') || '');
			if (!pointId) {
				return;
			}
			var point = getPickupPointById(pointId);
			if (!point) {
				return;
			}
			var scenarioData = state.frontendStore.fulfillment.scenarioData || {};
			scenarioData.pickup_point = point;
			state.frontendStore.fulfillment.scenarioData = scenarioData;
			invalidateV2DownstreamFrom(state, 0);
			render(state, $app);
			postCheckout('session_set_answers', {
				step_id: 'scenario',
				context_id: state.flowContextId,
				answers: scenarioData
			}).fail(function () {
				notify('Не удалось сохранить точку самовывоза.', 'error');
			});
		});

		$app.find('[data-calendar-date]').off('click').on('click', function () {
			var $btn = $(this);
			if ($btn.is(':disabled')) {
				return;
			}
			var value = String($btn.data('calendar-date') || '');
			if (!value) {
				return;
			}
			var dateBox = state.frontendStore.fulfillment.date && typeof state.frontendStore.fulfillment.date === 'object'
				? state.frontendStore.fulfillment.date
				: {};
			dateBox.selected_date = value;
			var parsed = parseIsoDate(value);
			if (parsed) {
				dateBox.calendar_month = monthKeyFromDate(parsed);
			}
			state.frontendStore.fulfillment.date = dateBox;
			invalidateV2DownstreamFrom(state, 0);
			state.frontendStore.form.errors = state.frontendStore.form.errors || {};
			state.frontendStore.form.errors.date = '';
			setStepInvalidState(state, 'address_delivery', false);
			render(state, $app);
			postCheckout('session_set_answers', {
				step_id: 'address_delivery',
				context_id: state.flowContextId,
				answers: dateBox
			}).then(function (response) {
				if (!response || !response.success || !response.data) {
					throw new Error('date_save_empty_response');
				}
				if (response.data.flow) {
					syncFromFlow(state, response.data.flow, response.data.cart || {});
					render(state, $app);
				}
			}).fail(function (xhr) {
				var payload = xhr && xhr.responseJSON && xhr.responseJSON.data ? xhr.responseJSON.data : {};
				var message = payload.message || getStepThreeErrorCopy('invalid_date', 'Не удалось сохранить выбранную дату.');
				notify(message, 'error');
				syncStoreWithBackend(state, $app);
			});
		});

		$app.find('[data-calendar-nav]').off('click').on('click', function () {
			var shift = Number($(this).data('calendar-nav') || 0);
			if (!shift) {
				return;
			}
			var dateBox = state.frontendStore.fulfillment.date && typeof state.frontendStore.fulfillment.date === 'object'
				? state.frontendStore.fulfillment.date
				: {};
			var model = buildDateCalendarModel(state);
			var currentMonth = parseMonthKey(dateBox.calendar_month || model.monthKey);
			if (!currentMonth) {
				return;
			}
			var shiftedMonth = new Date(currentMonth.getFullYear(), currentMonth.getMonth() + shift, 1);
			var minMonth = parseMonthKey(model.minMonthKey);
			var maxMonth = parseMonthKey(model.maxMonthKey);
			if (minMonth && shiftedMonth < minMonth) {
				shiftedMonth = minMonth;
			}
			if (maxMonth && shiftedMonth > maxMonth) {
				shiftedMonth = maxMonth;
			}
			dateBox.calendar_month = monthKeyFromDate(shiftedMonth);
			state.frontendStore.fulfillment.date = dateBox;
			render(state, $app);
			postCheckout('session_set_answers', {
				step_id: 'address_delivery',
				context_id: state.flowContextId,
				answers: dateBox
			}).fail(function () {
				notify(getStepThreeErrorCopy('invalid_date', 'Не удалось сохранить выбранную дату.'), 'error');
			});
		});

		$app.find('[data-calendar-grid]').off('keydown').on('keydown', function (event) {
			var key = String(event.key || '');
			var $cells = $app.find('[data-calendar-date]').filter(function () {
				return !$(this).is(':disabled');
			});
			var current = document.activeElement;
			var currentIndex = $cells.index(current);
			if (currentIndex < 0) {
				return;
			}
			var nextIndex = currentIndex;
			if (key === 'ArrowRight') {
				nextIndex = Math.min($cells.length - 1, currentIndex + 1);
			} else if (key === 'ArrowLeft') {
				nextIndex = Math.max(0, currentIndex - 1);
			} else if (key === 'ArrowDown') {
				nextIndex = Math.min($cells.length - 1, currentIndex + 7);
			} else if (key === 'ArrowUp') {
				nextIndex = Math.max(0, currentIndex - 7);
			} else if (key === 'Home') {
				nextIndex = 0;
			} else if (key === 'End') {
				nextIndex = $cells.length - 1;
			} else if (key === 'Enter' || key === ' ') {
				$(current).trigger('click');
				event.preventDefault();
				return;
			} else {
				return;
			}
			event.preventDefault();
			var $target = $cells.eq(nextIndex);
			if ($target.length) {
				$target.trigger('focus');
			}
		});

		$app.find('[data-contact-field]').off('input change blur').on('input change', function () {
			var key = String($(this).data('contact-field') || '');
			if (!key) {
				return;
			}
			var contact = state.frontendStore.form.contact || {};
			var val = $(this).val();
			if (key === 'order_notes') {
				var settings = getOrderNotesSettings();
				val = String(val || '');
				if (val.length > settings.maxLength) {
					val = val.slice(0, settings.maxLength);
					$(this).val(val);
				}
			}
			contact[key] = val;
			state.frontendStore.form.contact = contact;
			if (state.frontendStore.form.errors && state.frontendStore.form.errors.contact) {
				delete state.frontendStore.form.errors.contact[key];
			}
			contact.billing_phone = buildFullPhoneE164(contact);
			if (key === 'order_notes') {
				var cfgNotes = getOrderNotesSettings();
				var remain = Math.max(0, cfgNotes.maxLength - String(val || '').length);
				$app.find('[data-order-notes-counter="1"]').text('Осталось символов: ' + String(remain));
			}
			invalidateV2DownstreamFrom(state, 1);
			scheduleCurrentStepDraftSave(state, function () {
				notify(getStepFourAjaxMessage('draft_save_failed', 'step_4.contact_ajax_draft_save_failed', 'Не удалось сохранить данные.'), 'error');
			});
			if (key === 'country' || key === 'state' || key === 'city' || key === 'address_1' || key === 'address_2' || key === 'postcode') {
				syncStoreWithBackend(state, $app);
			}
		}).on('blur', function () {
			ensureContactDefaults(state);
			saveCurrentStepDraft(state).fail(function () {
				notify(getStepFourAjaxMessage('draft_save_failed', 'step_4.contact_ajax_draft_save_failed', 'Не удалось сохранить данные.'), 'error');
			});
		});

		$app.find('[data-contact-phone-national]').off('input change blur').on('input change', function () {
			var $inp = $(this);
			var cfg = getStepFourConfig();
			var codes = cfg.contact_block && cfg.contact_block.phone_country_codes ? cfg.contact_block.phone_country_codes : [];
			var contact = state.frontendStore.form.contact || {};
			var meta = findPhoneCountryMeta(codes, contact.phone_country_iso);
			var maxLen = meta.national_digits || 10;
			var raw = String($inp.val() || '').replace(/\D/g, '').slice(0, maxLen);
			contact.billing_phone_national = raw;
			contact.billing_phone = buildFullPhoneE164(contact);
			state.frontendStore.form.contact = contact;
			var display = formatNationalPhoneDisplay(meta.dial, raw, meta.iso);
			if ($inp.val() !== display) {
				$inp.val(display);
			}
			if (state.frontendStore.form.errors && state.frontendStore.form.errors.contact) {
				delete state.frontendStore.form.errors.contact.billing_phone_national;
			}
			invalidateV2DownstreamFrom(state, 1);
			scheduleCurrentStepDraftSave(state, function () {
				notify(getStepFourAjaxMessage('draft_save_failed', 'step_4.contact_ajax_draft_save_failed', 'Не удалось сохранить данные.'), 'error');
			});
		}).on('blur', function () {
			ensureContactDefaults(state);
			saveCurrentStepDraft(state).fail(function () {
				notify(getStepFourAjaxMessage('draft_save_failed', 'step_4.contact_ajax_draft_save_failed', 'Не удалось сохранить данные.'), 'error');
			});
		});

		$app.find('.mp-cc-payment-card--surface').off('click.mpccPayConfirm').on('click.mpccPayConfirm', function () {
			var $radio = $(this).find('input[data-payment-gateway]');
			var gateway = trimNonEmpty($radio.val());
			if (!gateway) {
				return;
			}
			var brandClass = String($(this).attr('class') || '');
			var glowBrand = brandClass.indexOf('mp-cc-payment-card--brand-bank') !== -1 || brandClass.indexOf('mp-cc-payment-card--brand-robokassa') !== -1 || brandClass.indexOf('mp-cc-payment-card--brand-yookassa') !== -1;
			var visualCfg = glowBrand ? getBankCardVisualConfig() : null;
			state.frontendStore.payment = state.frontendStore.payment || { gateway: '', state: 'idle' };
			var isSameGateway = String(state.frontendStore.payment.gateway || '') === gateway;
			if (isSameGateway && state.frontendStore.payment.user_confirmed === true && visualCfg && visualCfg.allowDeselect) {
				// UX: allow deselect by clicking the selected card again.
				state.frontendStore.payment.user_confirmed = false;
				state.frontendStore.payment.gateway = '';
				state.frontendStore.payment.state = 'idle';
				state.frontendStore.payment.fieldsHtml = '';
				state.frontendStore.payment.fieldsGatewayId = '';
				state.frontendStore.payment.fieldsHydration = 'idle';
				state.frontendStore.payment.gatewayCompatIssue = '';
				state.frontendStore.form.contact = state.frontendStore.form.contact || {};
				state.frontendStore.form.contact.payment_gateway = '';
				state.frontendStore.form.contact.gateway = '';
				$radio.prop('checked', false);
				render(state, $app);
				return;
			}
			state.frontendStore.payment.user_confirmed = true;
			if (isSameGateway) {
				render(state, $app);
			}
		});

		$app.find('[data-payment-gateway]').off('change').on('change', function () {
			var gateway = trimNonEmpty($(this).val());
			if (!gateway) {
				return;
			}
			state.frontendStore.payment = state.frontendStore.payment || { gateway: '', state: 'idle' };
			state.frontendStore.payment.gateway = gateway;
			state.frontendStore.payment.user_confirmed = true;
			state.frontendStore.payment.state = 'syncing';
			window.clearTimeout(state.__mpCcPaymentSuccessTimer);
			state.__mpCcPaymentSuccessTimer = 0;
			state.frontendStore.payment.fieldsHtml = '';
			state.frontendStore.payment.fieldsGatewayId = '';
			state.frontendStore.payment.fieldsHydration = 'pending';
			state.frontendStore.payment.gatewayCompatIssue = '';
			window.clearTimeout(state.__mpCcCompatTimer);
			state.__mpCcCompatTimer = 0;
			state.frontendStore.form.contact = state.frontendStore.form.contact || {};
			state.frontendStore.form.contact.payment_gateway = gateway;
			state.frontendStore.form.contact.gateway = gateway;
			if (state.frontendStore.form.errors && state.frontendStore.form.errors.contact) {
				delete state.frontendStore.form.errors.contact.payment_gateway;
			}
			invalidateV2DownstreamFrom(state, 2);
			render(state, $app);
			postCheckout('set_payment_gateway', {
				gateway: gateway,
				context_id: state.flowContextId
			}).then(function (response) {
				var data = response && response.data ? response.data : {};
				state.frontendStore.payment.state = 'success';
				if (data.flow || data.cart) {
					syncFromFlow(state, data.flow || {}, data.cart || {}, paymentFieldPayloadFromAjaxData(data));
				} else if (data && Object.prototype.hasOwnProperty.call(data, 'payment_fields_html')) {
					state.frontendStore.payment.fieldsHtml = String(data.payment_fields_html || '');
					state.frontendStore.payment.fieldsGatewayId = String(
						data.payment_fields_gateway || state.frontendStore.payment.gateway || ''
					);
					state.frontendStore.payment.fieldsHydration = 'settled';
					state.frontendStore.payment.gatewayCompatIssue = '';
				}
				render(state, $app);
				window.clearTimeout(state.__mpCcPaymentSuccessTimer);
				state.__mpCcPaymentSuccessTimer = window.setTimeout(function () {
					if (!state.frontendStore || !state.frontendStore.payment) {
						return;
					}
					if (state.frontendStore.payment.state === 'success') {
						state.frontendStore.payment.state = 'idle';
						render(state, $app);
					}
				}, 2200);
			}).fail(function (xhr) {
				window.clearTimeout(state.__mpCcPaymentSuccessTimer);
				state.frontendStore.payment.state = 'error';
				var payload = xhr && xhr.responseJSON && xhr.responseJSON.data ? xhr.responseJSON.data : {};
				var paymentCfg = getStepFourConfig().payment_block || {};
				var paymentMessages = paymentCfg.messages && typeof paymentCfg.messages === 'object' ? paymentCfg.messages : {};
				notify(trimNonEmpty(payload.message) || trimNonEmpty(paymentMessages.error) || getUiText('step_4.payment_error_switch', 'Не удалось переключить способ оплаты.'), 'error');
				syncStoreWithBackend(state, $app);
			});
		});

		$app.find('.mp-cc-payment__grid').off('keydown.mpccPayGrid').on('keydown.mpccPayGrid', '.mp-cc-payment-card__radio', function (ev) {
			var $radio = $(this);
			var $grid = $radio.closest('.mp-cc-payment__grid');
			var $radios = $grid.find('.mp-cc-payment-card__radio');
			if ($radios.length < 2) {
				return;
			}
			var key = ev.key;
			if (key !== 'ArrowRight' && key !== 'ArrowLeft' && key !== 'ArrowDown' && key !== 'ArrowUp' && key !== 'Home' && key !== 'End') {
				return;
			}
			var idx = $radios.index($radio);
			var next = idx;
			if (key === 'ArrowRight' || key === 'ArrowDown') {
				next = Math.min($radios.length - 1, idx + 1);
			} else if (key === 'ArrowLeft' || key === 'ArrowUp') {
				next = Math.max(0, idx - 1);
			} else if (key === 'Home') {
				next = 0;
			} else if (key === 'End') {
				next = $radios.length - 1;
			}
			if (next !== idx) {
				ev.preventDefault();
				var $t = $radios.eq(next);
				$t.prop('checked', true).trigger('change');
				$t.trigger('focus');
			}
		});

		$app.find('[data-coupon-code]').off('input').on('input', function () {
			ensureDiscountDefaults(state);
			var discounts = state.frontendStore.discounts || {};
			var rt = discounts.coupon_runtime || { code: '', state: 'empty', message: '' };
			rt.code = String($(this).val() || '');
			if (trimNonEmpty(rt.code)) {
				rt.state = 'empty';
				rt.message = '';
			}
			discounts.coupon_runtime = rt;
			state.frontendStore.discounts = discounts;
		});

		$app.find('[data-coupon-apply]').off('click').on('click', function () {
			ensureDiscountDefaults(state);
			var discounts = state.frontendStore.discounts || {};
			var rt = discounts.coupon_runtime || { code: '', state: 'empty', message: '' };
			var copy = getCouponCopy();
			var code = trimNonEmpty(rt.code);
			if (!code) {
				rt.state = 'error';
				rt.message = copy.emptyMessage;
				discounts.coupon_runtime = rt;
				state.frontendStore.discounts = discounts;
				render(state, $app);
				return;
			}
			postCheckout('apply_coupon', {
				coupon_code: code,
				context_id: state.flowContextId
			}).then(function (response) {
				var data = response && response.data ? response.data : {};
				rt.state = 'success';
				rt.message = trimNonEmpty(data.message) || copy.successMessage;
				discounts.coupons = Array.isArray(data.applied_coupons) ? data.applied_coupons : discounts.coupons;
				discounts.coupon_runtime = rt;
				state.frontendStore.discounts = discounts;
				if (data.flow || data.cart) {
					syncFromFlow(state, data.flow || {}, data.cart || {});
				}
				render(state, $app);
			}).fail(function (xhr) {
				var payload = xhr && xhr.responseJSON && xhr.responseJSON.data ? xhr.responseJSON.data : {};
				rt.state = 'error';
				rt.message = trimNonEmpty(payload.message) || copy.errorMessage;
				if (Array.isArray(payload.applied_coupons)) {
					discounts.coupons = payload.applied_coupons;
				}
				discounts.coupon_runtime = rt;
				state.frontendStore.discounts = discounts;
				if (payload.flow || payload.cart) {
					syncFromFlow(state, payload.flow || {}, payload.cart || {});
				}
				render(state, $app);
				notify(rt.message, 'error');
			});
		});

		$app.off('input.mpCcGiftPeer', '[data-gift-card-peer-code]').on('input.mpCcGiftPeer', '[data-gift-card-peer-code]', function () {
			ensureDiscountDefaults(state);
			var discounts = state.frontendStore.discounts || {};
			var rt = discounts.gift_card_runtime || { code: '', state: 'empty', message: '' };
			rt.code = String($(this).val() || '');
			if (rt.state === 'error' || rt.state === 'success') {
				rt.state = 'empty';
				rt.message = '';
			}
			discounts.gift_card_runtime = rt;
			state.frontendStore.discounts = discounts;
		});

		$app.off('keydown.mpCcGiftPeer', '[data-gift-card-peer-code]').on('keydown.mpCcGiftPeer', '[data-gift-card-peer-code]', function (ev) {
			if (ev.key === 'Enter') {
				ev.preventDefault();
				$(this).closest('[data-gift-peer-card]').find('[data-gift-card-peer-apply]').trigger('click');
			}
		});

		$app.off('click.mpCcGiftPeerApply', '[data-gift-card-peer-apply]').on('click.mpCcGiftPeerApply', '[data-gift-card-peer-apply]', function () {
			if (!isGiftCardPwRuntimeAvailable()) {
				return;
			}
			ensureDiscountDefaults(state);
			var discounts = state.frontendStore.discounts || {};
			var rt = discounts.gift_card_runtime || { code: '', state: 'empty', message: '' };
			var copy = getGiftCardPeerCopy();
			var code = trimNonEmpty(rt.code);
			if (!code) {
				rt.state = 'error';
				rt.message = copy.emptyMessage;
				discounts.gift_card_runtime = rt;
				state.frontendStore.discounts = discounts;
				render(state, $app);
				return;
			}
			rt.state = 'loading';
			rt.message = '';
			discounts.gift_card_runtime = rt;
			state.frontendStore.discounts = discounts;
			render(state, $app);
			postCheckout('apply_gift_card', {
				gift_card_code: code,
				context_id: state.flowContextId
			}).then(function (response) {
				var data = response && response.data ? response.data : {};
				rt.state = 'success';
				rt.message = trimNonEmpty(data.message) || copy.successMessage;
				rt.code = '';
				discounts.gift_card_runtime = rt;
				state.frontendStore.discounts = discounts;
				if (data.flow || data.cart) {
					syncFromFlow(state, data.flow || {}, data.cart || {});
				}
				render(state, $app);
			}).fail(function (xhr) {
				var payload = xhr && xhr.responseJSON && xhr.responseJSON.data ? xhr.responseJSON.data : {};
				rt.state = 'error';
				rt.message = trimNonEmpty(payload.message) || copy.errorMessage;
				discounts.gift_card_runtime = rt;
				state.frontendStore.discounts = discounts;
				if (payload.flow || payload.cart) {
					syncFromFlow(state, payload.flow || {}, payload.cart || {});
				}
				render(state, $app);
				notify(rt.message, 'error');
			});
		});

		$app.find('[data-contact-phone-country]').off('change').on('change', function () {
			var iso = String($(this).val() || '');
			var contact = state.frontendStore.form.contact || {};
			contact.phone_country_iso = iso;
			var cfg = getStepFourConfig();
			var codes = cfg.contact_block && cfg.contact_block.phone_country_codes ? cfg.contact_block.phone_country_codes : [];
			var meta = findPhoneCountryMeta(codes, iso);
			contact.phone_dial_code = meta.dial;
			var raw = String(contact.billing_phone_national || '').replace(/\D/g, '');
			raw = raw.slice(0, meta.national_digits || 10);
			contact.billing_phone_national = raw;
			contact.billing_phone = buildFullPhoneE164(contact);
			state.frontendStore.form.contact = contact;
			if (state.frontendStore.form.errors && state.frontendStore.form.errors.contact) {
				delete state.frontendStore.form.errors.contact.billing_phone_national;
			}
			invalidateV2DownstreamFrom(state, 1);
			render(state, $app);
			saveCurrentStepDraft(state).fail(function () {
				notify(getStepFourAjaxMessage('draft_save_failed', 'step_4.contact_ajax_draft_save_failed', 'Не удалось сохранить данные.'), 'error');
			});
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
		if (!itemKey) {
			return;
		}

		var localItem = getLocalCartItem(state, itemKey);
		if (!localItem) {
			return;
		}
		var minQty = Number(localItem.min_quantity || ($input.length ? $input.attr('min') : 1) || 1);
		var maxQty = Number(localItem.max_quantity || ($input.length ? $input.attr('max') : 9999) || 9999);
		var prevQty = Number(localItem && localItem.quantity ? localItem.quantity : minQty);
		var nextQty = clampQuantity(requestedQty, minQty, maxQty);
		if (nextQty === prevQty || state.isTransitioning) {
			if ($input.length) {
				$input.val(nextQty);
			}
			return;
		}
		$item.addClass('is-updating');
		if ($input.length) {
			$input.val(nextQty);
		}
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
		var scenario = state.frontendStore.fulfillment ? String(state.frontendStore.fulfillment.scenario || '') : '';
		var appliedCouponCodesSig = Array.isArray(cartSummary.applied_coupons) ? cartSummary.applied_coupons.join(',') : '';
		var couponLinesSig = Array.isArray(cartSummary.coupon_lines) ? cartSummary.coupon_lines.map(function (l) { return String(l && l.code ? l.code : ''); }).join(',') : '';
		var giftCodesSig = Array.isArray(cartSummary.applied_gift_cards) ? cartSummary.applied_gift_cards.join(',') : '';
		var giftLinesSig = Array.isArray(cartSummary.gift_card_lines) ? cartSummary.gift_card_lines.map(function (l) {
			var row = l || {};
			return String(row.label || '') + ':' + String(row.amount || '');
		}).join(';') : '';
		var pay = state.frontendStore && state.frontendStore.payment ? state.frontendStore.payment : {};
		var paySig = String(pay.gateway || '') + ':' + String(pay.state || '');
		var signature = [
			String(cartSummary.items_count || 0),
			String(cartSummary.subtotal || ''),
			String(cartSummary.discount || ''),
			String(cartSummary.shipping || ''),
			String(cartSummary.tax || ''),
			String(cartSummary.gift_card_total || ''),
			String(snapshot.total || cartSummary.total || ''),
			scenario,
			appliedCouponCodesSig,
			couponLinesSig,
			giftCodesSig,
			giftLinesSig,
			paySig
		].join('|');
		var prevSignature = String(state.frontendStore.meta.lastSummarySignature || '');
		state.frontendStore.meta.lastSummarySignature = signature;
		if (!prevSignature || prevSignature === signature) {
			return;
		}
		var m = getMotionConfig();
		if (!m.toggles || m.toggles.summary_numbers === false) {
			return;
		}
		if (useCheckoutReducedMotion()) {
			return;
		}
		if (shouldThrottleMotion('summary_numbers')) {
			return;
		}
		var d = getEffectiveMotionDurations().summary_numbers;
		var ms = typeof d === 'number' && !Number.isNaN(d) ? Math.max(120, Math.round(Number(d))) : 320;
		$summary.find('[data-summary-amount]').addClass('is-updated');
		window.setTimeout(function () {
			$summary.find('[data-summary-amount]').removeClass('is-updated');
			logMotionInstrumentation('summary_numbers_pulse', ms);
		}, ms);
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

	/** HTML сумм из WooCommerce (wc_price / get_cart_subtotal); не экранировать — иначе в сайдбаре видны «сырые» теги. */
	function wcPriceHtmlFragment(value) {
		if (value == null || value === '') {
			return '';
		}
		return String(value);
	}

	$(function () {
		var $app = $(selectors.app);
		if (!$app.length) {
			return;
		}

		var context = parseContext();
		bindClientErrorLogging();
		applyThemeVariant(context);
		bindVisualViewportKeyboardInset();
		syncMotionRuntimeVars();
		bindReducedMotionMediaListener();
		bindMotionViewportMediaListener();
		var state = buildState(context);
		patchPaymentFieldsFromContext(state, context);
		// Сервер уже передал снимок корзины в data-mp-cc-context; createFrontendStore иначе оставляет items пустыми до AJAX.
		var initialCart = normalizeCartPayload(context.cart || {});
		state.frontendStore.cart.items = initialCart.items;
		state.frontendStore.cart.summary = initialCart.summary;
		state.frontendStore.runtime.summaryHydrated = initialCart.items.length > 0;
		if (!state.frontendStore.fulfillment.scenario) {
			state.frontendStore.fulfillment.scenario = 'pickup';
		}
		applyScenarioFieldAvailability(state);
		ensurePickupScenarioData(state);
		ensureDateSelection(state);
		ensureCartSnapshotConsistency(state, $app);
		render(state, $app);

		syncStoreWithBackend(state, $app).fail(function () {
			notify(getStepFourAjaxMessage('step_sync_failed', 'step_4.contact_ajax_step_sync_failed', 'Не удалось синхронизировать шаг. Обновите страницу.'), 'error');
		});

		document.addEventListener('mp_cc_scenario_changed', function (event) {
			var nextScenario = event && event.detail ? String(event.detail.scenario || '') : '';
			if (!nextScenario) {
				return;
			}
			nextScenario = normalizeScenarioId(nextScenario);

			withTransitionLock(state, $app, function () {
				return postCheckout('session_set_scenario', { scenario: nextScenario, context_id: state.flowContextId })
					.then(function () {
						return syncStoreWithBackend(state, $app);
					}).fail(function (xhr) {
						var payload = xhr && xhr.responseJSON && xhr.responseJSON.data ? xhr.responseJSON.data : {};
						notify(payload.message || getStepFourAjaxMessage('scenario_sync_failed', 'step_4.contact_ajax_scenario_sync_failed', 'Не удалось сохранить выбор сценария.'), 'error');
						document.dispatchEvent(
							new CustomEvent('mp_cc_scenario_error', {
								detail: { scenario: nextScenario, payload: payload }
							})
						);
						syncStoreWithBackend(state, $app);
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
				var cleanContact = {};
				var pk;
				for (pk in payload) {
					if (!Object.prototype.hasOwnProperty.call(payload, pk)) {
						continue;
					}
					if (pk.indexOf('__') === 0) {
						continue;
					}
					cleanContact[pk] = payload[pk];
				}
				state.frontendStore.form.contact = cleanContact;
				applyScenarioFieldAvailability(state);
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

		bindAbandonCheckoutOnPageLeave();

		document.addEventListener('mp_cc_checkout_success', function () {
			setRuntimeFlag(state, 'success', true);
		});
	});
})(jQuery);
