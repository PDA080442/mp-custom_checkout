/**
 * MP Custom Checkout — admin preview for step one.
 */
(function ($) {
	'use strict';

	function getConfigFromRuntime() {
		var source = window.mpCcAdmin && window.mpCcAdmin.stepOneConfig ? window.mpCcAdmin.stepOneConfig : {};
		var labels = source.labels || {};
		var emptyState = source.empty_state || {};
		var styleControls = source.style_controls || {};
		var pickupConfig = window.mpCcAdmin && window.mpCcAdmin.pickupConfig ? window.mpCcAdmin.pickupConfig : {};
		var pickupPoints = pickupConfig.points && Array.isArray(pickupConfig.points) ? pickupConfig.points : [];
		var scenarioUiConfig = window.mpCcAdmin && window.mpCcAdmin.scenarioUiConfig ? window.mpCcAdmin.scenarioUiConfig : {};
		var isPickupPreview = String(scenarioUiConfig.default_scenario || '') === 'pickup';
		return {
			previewEnabled: Boolean(source.admin_preview && source.admin_preview.enabled !== false),
			title: labels.title || 'Корзина',
			summaryTitle: labels.summary_title || 'Сводка заказа',
			subtotalLabel: labels.subtotal_label || 'Подытог',
			shippingLabel: labels.shipping_label || 'Доставка',
			discountLabel: labels.discount_label || 'Скидка',
			giftCardLabel: labels.gift_card_label || 'Подарочная карта',
			taxLabel: labels.tax_label || 'Налоги',
			totalLabel: labels.total_label || 'Итого',
			itemsLabel: labels.items_label || 'Позиций',
			continueLabel: labels.continue_label || 'Продолжить оформление',
			returnLabel: labels.return_label || 'Вернуться в магазин',
			emptyTitle: labels.empty_title || 'Корзина пуста',
			isPickupPreview: isPickupPreview,
			emptyMessage: emptyState.message || '',
			emptyCta: emptyState.cta_label || labels.return_label || 'Вернуться в магазин',
			cardCompact: Boolean(styleControls.card_compact),
			cardEmphasis: styleControls.card_emphasis || 'default',
			summaryEmphasis: styleControls.summary_emphasis || 'default',
			pickupPoint: pickupPoints.length ? pickupPoints[0] : null
		};
	}

	function getScenarioConfigFromRuntime() {
		var source = window.mpCcAdmin && window.mpCcAdmin.scenarioUiConfig ? window.mpCcAdmin.scenarioUiConfig : {};
		var cards = source.cards || {};
		var responsive = source.responsive || {};
		return {
			previewEnabled: Boolean(source.admin_preview && source.admin_preview.enabled !== false),
			defaultScenario: String(source.default_scenario || 'pickup'),
			cardOrder: Array.isArray(source.card_order) ? source.card_order : ['pickup', 'delivery'],
			cards: {
				pickup: {
					title: String(cards.pickup && cards.pickup.title ? cards.pickup.title : 'Самовывоз'),
					description: String(cards.pickup && cards.pickup.description ? cards.pickup.description : ''),
					helper: String(cards.pickup && cards.pickup.helper ? cards.pickup.helper : ''),
					iconVariant: String(cards.pickup && cards.pickup.icon_variant ? cards.pickup.icon_variant : 'pickup'),
					iconStyle: String(cards.pickup && cards.pickup.icon_style ? cards.pickup.icon_style : 'soft')
				},
				delivery: {
					title: String(cards.delivery && cards.delivery.title ? cards.delivery.title : 'Доставка'),
					description: String(cards.delivery && cards.delivery.description ? cards.delivery.description : ''),
					helper: String(cards.delivery && cards.delivery.helper ? cards.delivery.helper : ''),
					iconVariant: String(cards.delivery && cards.delivery.icon_variant ? cards.delivery.icon_variant : 'delivery'),
					iconStyle: String(cards.delivery && cards.delivery.icon_style ? cards.delivery.icon_style : 'soft')
				}
			},
			responsive: {
				desktopColumns: Number(responsive.desktop_columns || 2),
				tabletColumns: Number(responsive.tablet_columns || 1),
				mobileColumns: Number(responsive.mobile_columns || 1),
				cardDensity: String(responsive.card_density || 'comfortable')
			}
		};
	}

	function getDateStepConfigFromRuntime() {
		var source = window.mpCcAdmin && window.mpCcAdmin.stepThreeConfig ? window.mpCcAdmin.stepThreeConfig : {};
		var copy = source.copy || {};
		var helperMap = copy.helper_by_scenario || {};
		var errors = copy.errors || {};
		var minLead = source.min_lead_time_days || {};
		var weekdayRules = source.weekday_rules || {};
		var style = source.calendar_style || {};
		var holidayDates = Array.isArray(source.holiday_dates) ? source.holiday_dates : [];
		var closedDates = Array.isArray(source.closed_dates) ? source.closed_dates : [];
		return {
			previewEnabled: Boolean(copy.admin_preview && copy.admin_preview.enabled !== false),
			title: String(copy.title || 'Выберите дату получения'),
			helperByScenario: {
				pickup: String(helperMap.pickup || ''),
				krasnoyarsk_delivery: String(helperMap.krasnoyarsk_delivery || ''),
				other_city_delivery: String(helperMap.other_city_delivery || '')
			},
			errors: {
				invalidDate: String(errors.invalid_date || ''),
				emptyDate: String(errors.empty_date || '')
			},
			minLeadTime: {
				pickup: Number(minLead.pickup || 1),
				krasnoyarsk_delivery: Number(minLead.krasnoyarsk_delivery || 1),
				other_city_delivery: Number(minLead.other_city_delivery || 2)
			},
			weekdayRules: {
				pickup: Array.isArray(weekdayRules.pickup) ? weekdayRules.pickup : [],
				krasnoyarsk_delivery: Array.isArray(weekdayRules.krasnoyarsk_delivery) ? weekdayRules.krasnoyarsk_delivery : [],
				other_city_delivery: Array.isArray(weekdayRules.other_city_delivery) ? weekdayRules.other_city_delivery : []
			},
			holidayDates: holidayDates,
			closedDates: closedDates,
			calendarStyle: {
				density: String(style.density || 'comfortable'),
				dayShape: String(style.day_shape || 'rounded'),
				highlightStyle: String(style.highlight_style || 'accent'),
				showWeekendTint: Boolean(style.show_weekend_tint !== false)
			}
		};
	}

	function getOfficeHoursPreviewConfigFromRuntime() {
		var source = window.mpCcAdmin && window.mpCcAdmin.stepThreeConfig ? window.mpCcAdmin.stepThreeConfig : {};
		var copy = source.copy || {};
		var pickup = source.conditions_copy && source.conditions_copy.pickup && typeof source.conditions_copy.pickup === 'object'
			? source.conditions_copy.pickup
			: {};
		var pc = window.mpCcAdmin && window.mpCcAdmin.pickupConfig ? window.mpCcAdmin.pickupConfig : {};
		var points = pc.points && Array.isArray(pc.points) ? pc.points : [];
		var point = points.length ? points[0] : null;
		var mapWidget = pc.map_widget && typeof pc.map_widget === 'object' ? pc.map_widget : {};
		return {
			previewEnabled: Boolean(copy.admin_preview && copy.admin_preview.enabled !== false),
			officeBlockTitle: String(pickup.office_block_title || ''),
			officeAddress: String(pickup.office_address || ''),
			officeDescription: String(pickup.office_description || ''),
			officeHoursPlain: String(pickup.office_hours_plain || ''),
			officeHours: Array.isArray(pickup.office_hours) ? pickup.office_hours : [],
			convenienceHelper: String(pickup.convenience_helper || ''),
			criticalNotice: String(pickup.critical_notice || ''),
			showMultiOfficeSlot: pickup.show_multi_office_slot !== false,
			pointTitle: point && point.title ? String(point.title) : '',
			pointAddress: point && point.address ? String(point.address) : '',
			pointDescription: point && point.description ? String(point.description) : '',
			mapEnabled: mapWidget.enabled !== false,
			mapLat: Number(mapWidget.center_lat || 56.010563),
			mapLng: Number(mapWidget.center_lng || 92.852572),
			mapZoom: Number(mapWidget.zoom || 14),
			mapMarkerLabel: String(mapWidget.marker_label || 'Пункт самовывоза'),
			mapMarkerHint: String(mapWidget.marker_hint || 'Заберите заказ в рабочие часы.'),
			mapFallbackTitle: String(mapWidget.fallback_title || 'Карта временно недоступна'),
			mapFallbackMessage: String(mapWidget.fallback_message || 'Посмотрите адрес пункта самовывоза выше и постройте маршрут в приложении карт.'),
			mapDesktopHeight: Number(mapWidget.desktop_height || 250),
			mapMobileHeight: Number(mapWidget.mobile_height || 190)
		};
	}

	function getStepFourConfigFromRuntime() {
		var source = window.mpCcAdmin && window.mpCcAdmin.stepFourConfig ? window.mpCcAdmin.stepFourConfig : {};
		var contact = source.contact_block || {};
		var address = source.address_block || {};
		var payment = source.payment_block && typeof source.payment_block === 'object' ? source.payment_block : {};
		return {
			previewEnabled: true,
			contact: {
				title: String(contact.title || 'Контактные данные'),
				intro: String(contact.intro || ''),
				fieldOrder: Array.isArray(contact.field_order) ? contact.field_order : ['last_name', 'first_name', 'patronymic', 'gender', 'birthdate', 'email', 'phone', 'order_notes'],
				fieldVisibility: contact.field_visibility && typeof contact.field_visibility === 'object' ? contact.field_visibility : {},
				fieldRequired: contact.field_required && typeof contact.field_required === 'object' ? contact.field_required : {},
				labels: contact.labels && typeof contact.labels === 'object' ? contact.labels : {},
				placeholders: contact.placeholders && typeof contact.placeholders === 'object' ? contact.placeholders : {},
				hints: contact.hints && typeof contact.hints === 'object' ? contact.hints : {},
				validationMessages: contact.validation_messages && typeof contact.validation_messages === 'object' ? contact.validation_messages : {},
				constraints: contact.validation_constraints && typeof contact.validation_constraints === 'object' ? contact.validation_constraints : {},
				ajaxMessages: contact.ajax_messages && typeof contact.ajax_messages === 'object' ? contact.ajax_messages : {},
				layout: contact.layout && typeof contact.layout === 'object' ? contact.layout : {},
				states: contact.field_state_styles && typeof contact.field_state_styles === 'object' ? contact.field_state_styles : {}
			},
			address: {
				title: String(address.title || 'Адрес доставки'),
				order: Array.isArray(address.subfields_order) ? address.subfields_order : ['country', 'state', 'city', 'address_1', 'address_2', 'postcode'],
				visible: address.subfields_visible && typeof address.subfields_visible === 'object' ? address.subfields_visible : {}
			},
			discountLayout: source.discount_layout && typeof source.discount_layout === 'object' ? source.discount_layout : { placement: 'step_4', separate_step_enabled: false, order: ['coupon'] },
			discountStyles: source.discount_block_styles && typeof source.discount_block_styles === 'object' ? source.discount_block_styles : { state_empty: 'default', state_success: 'success', state_error: 'error', focus_style: 'default' },
			payment: $.extend(true, {
				title: 'Способ оплаты',
				intro: 'Выберите удобный способ оплаты.',
				gateway_order: [],
				card_surface: 'visual',
				auto_classic_on_empty_gateway_fields: true,
				decorative_card_fields: true,
				card_style: 'default',
				card_active_style: 'accent',
				radio_style: 'default',
				description_style: 'muted',
				show_description: true,
				required: true,
				error_message: 'Выберите способ оплаты.',
				messages: { loading: '', success: '', error: '' },
				layout: { desktop_columns: 2, tablet_columns: 2, mobile_columns: 1, grid_gap: '0.6rem 0.75rem' },
				diagnostics: { enabled: true },
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
				}
			}, payment),
			coupon: $.extend(true, {
				title: 'Промокод',
				intro: '',
				input_label: 'Код купона',
				placeholder: '',
				apply_label: 'Применить',
				empty_message: '',
				success_message: '',
				error_message: '',
				allow_remove_applied: true,
				summary_section_title: ''
			}, source.coupon_block && typeof source.coupon_block === 'object' ? source.coupon_block : {}),
			giftCard: source.gift_card_block && typeof source.gift_card_block === 'object' ? source.gift_card_block : {},
			geoPreview: source.geo_preview && source.geo_preview.enabled !== false,
			geo: source.address_geo && typeof source.address_geo === 'object' ? source.address_geo : {}
		};
	}

	function getDeliveryConfigFromRuntime() {
		var source = window.mpCcAdmin && window.mpCcAdmin.deliveryConfig ? window.mpCcAdmin.deliveryConfig : {};
		var catalog = source.shipping_catalog && typeof source.shipping_catalog === 'object' ? source.shipping_catalog : {};
		return {
			shippingCatalog: {
				sortOrder: Array.isArray(catalog.sort_order) ? catalog.sort_order : [],
				methods: catalog.methods && typeof catalog.methods === 'object' ? catalog.methods : {},
				errorCopy: catalog.error_copy && typeof catalog.error_copy === 'object' ? catalog.error_copy : {},
				bulkUpdate: catalog.bulk_update && typeof catalog.bulk_update === 'object' ? catalog.bulk_update : { enabled: true, seasonal_delta_pct: 0, seasonal_delta_abs: 0, eta_suffix: '' },
				preview: catalog.preview && typeof catalog.preview === 'object' ? catalog.preview : { enabled: true, mock_subtotal: 3670, mock_discount: 200, mock_tax: 160 }
			}
		};
	}

	function trimNonEmptyAdmin(value) {
		var s = String(value || '').trim();
		return s ? s : '';
	}

	function readLiveOfficeHoursPreviewConfig(base) {
		var cfg = $.extend(true, {}, base);
		var p = 'mp_custom_checkout_settings[step_3][conditions_copy][pickup]';
		cfg.officeBlockTitle = readFormValue(p + '[office_block_title]', cfg.officeBlockTitle);
		cfg.officeAddress = readFormValue(p + '[office_address]', cfg.officeAddress);
		cfg.officeDescription = readFormValue(p + '[office_description]', cfg.officeDescription);
		cfg.officeHoursPlain = readFormValue(p + '[office_hours_plain]', cfg.officeHoursPlain);
		cfg.convenienceHelper = readFormValue(p + '[convenience_helper]', cfg.convenienceHelper);
		cfg.criticalNotice = readFormValue(p + '[critical_notice]', cfg.criticalNotice);
		cfg.showMultiOfficeSlot = Boolean(readFormValue(p + '[show_multi_office_slot]', cfg.showMultiOfficeSlot));
		var pm = 'mp_custom_checkout_settings[pickup][map_widget]';
		cfg.mapEnabled = Boolean(readFormValue(pm + '[enabled]', cfg.mapEnabled));
		cfg.mapLat = Number(readFormValue(pm + '[center_lat]', cfg.mapLat));
		cfg.mapLng = Number(readFormValue(pm + '[center_lng]', cfg.mapLng));
		cfg.mapZoom = Number(readFormValue(pm + '[zoom]', cfg.mapZoom));
		cfg.mapMarkerLabel = readFormValue(pm + '[marker_label]', cfg.mapMarkerLabel);
		cfg.mapMarkerHint = readFormValue(pm + '[marker_hint]', cfg.mapMarkerHint);
		cfg.mapFallbackTitle = readFormValue(pm + '[fallback_title]', cfg.mapFallbackTitle);
		cfg.mapFallbackMessage = readFormValue(pm + '[fallback_message]', cfg.mapFallbackMessage);
		cfg.mapDesktopHeight = Number(readFormValue(pm + '[desktop_height]', cfg.mapDesktopHeight));
		cfg.mapMobileHeight = Number(readFormValue(pm + '[mobile_height]', cfg.mapMobileHeight));
		return cfg;
	}

	function renderOfficeHoursPreview(cfg) {
		var officeTitle = trimNonEmptyAdmin(cfg.officeBlockTitle) || 'Офис и график работы';
		var address = trimNonEmptyAdmin(cfg.officeAddress);
		if (!address && cfg.pointAddress) {
			address = String(cfg.pointAddress);
		}
		var pointName = trimNonEmptyAdmin(cfg.pointTitle) ? String(cfg.pointTitle) : '';
		var desc = trimNonEmptyAdmin(cfg.officeDescription);
		if (!desc && cfg.pointDescription) {
			desc = String(cfg.pointDescription);
		}
		if (!desc) {
			desc = 'Выдача заказа в офисе самовывоза после уведомления о готовности.';
		}
		var plain = trimNonEmptyAdmin(cfg.officeHoursPlain);
		var hours = Array.isArray(cfg.officeHours) ? cfg.officeHours : [];
		var hoursClean = [];
		var i;
		for (i = 0; i < hours.length; i += 1) {
			var slot = trimNonEmptyAdmin(hours[i]);
			if (slot) {
				hoursClean.push(slot);
			}
		}
		if (!plain && !hoursClean.length) {
			hoursClean = ['10:00–13:00', '13:00–17:00', '17:00–20:00'];
		}
		var helper = trimNonEmptyAdmin(cfg.convenienceHelper) || 'Можно приехать в удобное время в рамках указанного расписания — уточните готовность заказа по уведомлению.';
		var critical = trimNonEmptyAdmin(cfg.criticalNotice);
		var showMulti = cfg.showMultiOfficeSlot !== false;

		var html = '';
		html += '<section class="mp-cc-admin-preview mp-cc-admin-preview--office" id="mp-cc-admin-office-preview">';
		html += '<h2>Офис и график (шаг 3, самовывоз)</h2>';
		html += '<p class="mp-cc-admin-preview--office__lead">Так блок отображается в карточке условий при выборе самовывоза. Адрес подставляется из настроек ниже или из точки самовывоза.</p>';
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
		html += '<div class="mp-cc-pickup-office__hours" aria-label="Часы выдачи">';
		if (plain) {
			html += '<div class="mp-cc-pickup-office__schedule mp-cc-pickup-office__schedule--plain">';
			var lines = plain.split(/\r?\n/);
			var firstLine = true;
			for (i = 0; i < lines.length; i += 1) {
				var line = trimNonEmptyAdmin(lines[i]);
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
		if (cfg.mapEnabled) {
			var mapLabel = trimNonEmptyAdmin(cfg.mapMarkerLabel) || 'Пункт самовывоза';
			var mapHint = trimNonEmptyAdmin(cfg.mapMarkerHint) || 'Заберите заказ в рабочие часы.';
			html += '<section class="mp-cc-pickup-map mp-cc-pickup-map--admin-preview">';
			html += '<div class="mp-cc-pickup-map__canvas mp-cc-pickup-map__canvas--admin">';
			html += '<div class="mp-cc-admin-preview__pickup-map">';
			html += '<strong>Yandex Map preview</strong><br />';
			html += '<span>center: ' + escapeHtml(String(cfg.mapLat) + ', ' + String(cfg.mapLng)) + '</span><br />';
			html += '<span>zoom: ' + escapeHtml(String(cfg.mapZoom)) + '</span><br />';
			html += '<span>marker: ' + escapeHtml(mapLabel) + '</span><br />';
			html += '<span>hint: ' + escapeHtml(mapHint) + '</span><br />';
			html += '<span>height: ' + escapeHtml(String(cfg.mapDesktopHeight)) + 'px / mobile ' + escapeHtml(String(cfg.mapMobileHeight)) + 'px</span>';
			html += '</div>';
			html += '</div>';
			html += '<div class="mp-cc-pickup-map__fallback">';
			html += '<p class="mp-cc-pickup-map__fallback-title">' + escapeHtml(trimNonEmptyAdmin(cfg.mapFallbackTitle) || 'Карта временно недоступна') + '</p>';
			html += '<p class="mp-cc-pickup-map__fallback-message">' + escapeHtml(trimNonEmptyAdmin(cfg.mapFallbackMessage) || 'Посмотрите адрес пункта самовывоза выше и постройте маршрут в приложении карт.') + '</p>';
			html += '</div>';
			html += '</section>';
		}
		if (showMulti) {
			html += '<div class="mp-cc-pickup-office__multi-slot" data-mp-cc-multi-office="1">';
			html += '<span class="mp-cc-pickup-office__multi-slot-label">' + escapeHtml('Дополнительные точки самовывоза будут отображаться здесь при подключении.') + '</span>';
			html += '</div>';
		}
		html += '</div>';
		html += '</section>';
		return html;
	}

	function getDefaults() {
		var source = window.mpCcAdmin && window.mpCcAdmin.stepOneDefaults ? window.mpCcAdmin.stepOneDefaults : {};
		return (source && typeof source === 'object') ? source : {};
	}

	function getSettingsDefaults() {
		var source = window.mpCcAdmin && window.mpCcAdmin.settingsDefaults ? window.mpCcAdmin.settingsDefaults : {};
		return (source && typeof source === 'object') ? source : {};
	}

	function createPreviewRuntimeMock() {
		return {
			cart: {
				items: [
					{ title: 'Rose Perfume', qty: 1, amount: '2 490 ₽' },
					{ title: 'Gift Box', qty: 2, amount: '590 ₽' }
				]
			},
			summary: {
				items: 3,
				subtotal: '3 670 ₽',
				shipping: '490 ₽',
				discount: '-200 ₽',
				giftCard: '-100 ₽',
				tax: '200 ₽',
				total: '4 060 ₽'
			},
			contact: {
				fullName: 'Иван Иванов',
				emailMasked: 'iv***@example.com',
				phoneMasked: '+7 *** ***-45-67'
			},
			fulfillment: {
				scenario: 'Самовывоз',
				date: '2026-04-20',
				pickupPoint: 'Офис на Мира, 10'
			}
		};
	}

	function createPreviewStore(initialState) {
		var state = $.extend(true, {}, initialState || {});
		return {
			getState: function () {
				return $.extend(true, {}, state);
			},
			setState: function (nextPatch) {
				state = $.extend(true, {}, state, nextPatch || {});
				return this.getState();
			},
			reset: function () {
				state = $.extend(true, {}, initialState || {});
				return this.getState();
			}
		};
	}

	function debounce(callback, waitMs) {
		var timeoutId = 0;
		return function () {
			var args = arguments;
			clearTimeout(timeoutId);
			timeoutId = window.setTimeout(function () {
				callback.apply(null, args);
			}, waitMs);
		};
	}

	function renderPreviewArea() {
		var html = '';
		html += '<section class="mp-cc-admin-preview-area" id="mp-cc-admin-preview-area">';
		html += '<div class="mp-cc-admin-preview-area__header">';
		html += '<h2>Live Checkout Preview</h2>';
		html += '<div class="mp-cc-admin-preview-area__actions">';
		html += '<label class="mp-cc-admin-preview-area__control">Progress';
		html += '<select data-mp-cc-progress-style-select="1"><option value="digits">1/2/3</option><option value="labels">Названия</option><option value="dots">Точки</option></select>';
		html += '</label>';
		html += '<label class="mp-cc-admin-preview-area__control">Scenario';
		html += '<select data-mp-cc-scenario-select="1"><option value="pickup">Самовывоз</option><option value="krasnoyarsk_delivery">Красноярск</option><option value="other_city_delivery">Другой город</option></select>';
		html += '</label>';
		html += '<label class="mp-cc-admin-preview-area__control">Device';
		html += '<select data-mp-cc-device-select="1"><option value="desktop">Desktop</option><option value="tablet">Tablet</option><option value="mobile">Mobile</option></select>';
		html += '</label>';
		html += '<label class="mp-cc-admin-preview-area__control">Interaction';
		html += '<select data-mp-cc-interaction-select="1"><option value="default">Default</option><option value="hover">Hover</option><option value="focus">Focus</option></select>';
		html += '</label>';
		html += '<label class="mp-cc-admin-preview-area__control">Runtime';
		html += '<select data-mp-cc-runtime-select="1"><option value="default">Default</option><option value="error">Error</option><option value="disabled">Disabled</option><option value="loading">Loading</option><option value="success">Success</option></select>';
		html += '</label>';
		html += '<label class="mp-cc-admin-preview-area__control">Sandbox';
		html += '<select data-mp-cc-sandbox-select="1"><option value="pickup_happy_path">Pickup happy path</option><option value="delivery_with_coupon">Delivery + coupon</option><option value="payment_error_case">Payment error</option></select>';
		html += '</label>';
		html += '<button type="button" class="button button-secondary" data-mp-cc-test-util="fulfillment">Test: fulfillment</button>';
		html += '<button type="button" class="button button-secondary" data-mp-cc-test-util="discounts">Test: discounts</button>';
		html += '<button type="button" class="button button-secondary" data-mp-cc-test-util="validation_payment">Test: validation/payment</button>';
		html += '<button type="button" class="button button-secondary" data-mp-cc-preview-reset="1">Сбросить превью</button>';
		html += '<button type="button" class="button button-secondary" data-mp-cc-tab-reset="1">Сбросить настройки вкладки по умолчанию</button>';
		html += '</div>';
		html += '</div>';
		html += '<p class="mp-cc-admin-preview-area__warning" data-mp-cc-preview-warning="1" hidden>В превью есть несохранённые изменения.</p>';
		html += '<nav class="mp-cc-admin-flow-nav" data-mp-cc-preview-flow-nav="1" aria-label="Preview checkout steps"></nav>';
		html += '<div class="mp-cc-admin-flow-progress" data-mp-cc-preview-progress="1"></div>';
		html += '<div class="mp-cc-admin-preview-area__body" data-mp-cc-preview-body="1"></div>';
		html += '</section>';
		return html;
	}

	function getPreviewStepItems() {
		return [
			{ id: 'step_1', label: 'Шаг 1: Корзина' },
			{ id: 'step_2', label: 'Шаг 2: Дата' },
			{ id: 'step_3', label: 'Шаг 3: Условия' },
			{ id: 'step_4', label: 'Шаг 4: Контакты и оплата' },
			{ id: 'success', label: 'Success' }
		];
	}

	function renderFlowStepSwitcher(activeStepId) {
		var items = getPreviewStepItems();
		var html = '';
		for (var i = 0; i < items.length; i += 1) {
			var step = items[i];
			var activeClass = step.id === activeStepId ? ' is-active' : '';
			html += '<button type="button" class="button mp-cc-admin-flow-nav__button' + activeClass + '" data-mp-cc-preview-step="' + escapeHtml(step.id) + '">' + escapeHtml(step.label) + '</button>';
		}
		return html;
	}

	function renderFlowProgress(activeStepId, styleVariant) {
		var items = getPreviewStepItems();
		var activeIndex = 0;
		var i;
		for (i = 0; i < items.length; i += 1) {
			if (items[i].id === activeStepId) {
				activeIndex = i;
				break;
			}
		}
		var html = '';
		html += '<ol class="mp-cc-admin-flow-progress__list" data-variant="' + escapeHtml(styleVariant) + '">';
		for (i = 0; i < items.length; i += 1) {
			var label = items[i].label;
			var number = String(i + 1);
			var stateClass = i < activeIndex ? ' is-done' : (i === activeIndex ? ' is-active' : '');
			html += '<li class="mp-cc-admin-flow-progress__item' + stateClass + '">';
			if (styleVariant === 'labels') {
				html += '<span class="mp-cc-admin-flow-progress__token">' + escapeHtml(label) + '</span>';
			} else if (styleVariant === 'dots') {
				html += '<span class="mp-cc-admin-flow-progress__dot" aria-hidden="true"></span>';
				html += '<span class="screen-reader-text">' + escapeHtml(label) + '</span>';
			} else {
				html += '<span class="mp-cc-admin-flow-progress__token">' + escapeHtml(number) + '</span>';
			}
			html += '</li>';
		}
		html += '</ol>';
		return html;
	}

	function renderCalendarStatePreview(config, previewState) {
		var scenario = previewState && previewState.scenario ? String(previewState.scenario) : 'pickup';
		var weekdays = (config.weekdayRules && config.weekdayRules[scenario] && config.weekdayRules[scenario].length) ? config.weekdayRules[scenario] : [1, 2, 3, 4, 5];
		var blocked = {};
		var i;
		var date;
		for (i = 0; i < config.holidayDates.length; i += 1) {
			date = String(config.holidayDates[i] || '');
			if (date) {
				blocked[date] = 'holiday';
			}
		}
		for (i = 0; i < config.closedDates.length; i += 1) {
			date = String(config.closedDates[i] || '');
			if (date) {
				blocked[date] = 'closed';
			}
		}
		var base = new Date('2026-04-14T00:00:00Z');
		var html = '<div class="mp-cc-admin-calendar-preview">';
		for (i = 0; i < 14; i += 1) {
			var current = new Date(base.getTime() + i * 86400000);
			var iso = current.toISOString().slice(0, 10);
			var day = current.getUTCDay();
			var dayNum = day === 0 ? 7 : day;
			var allowedWeekday = weekdays.indexOf(dayNum) > -1;
			var state = blocked[iso] ? blocked[iso] : (allowedWeekday ? 'open' : 'blocked');
			html += '<span class="mp-cc-admin-calendar-preview__day is-' + escapeHtml(state) + '">' + escapeHtml(iso.slice(8, 10)) + '</span>';
		}
		html += '</div>';
		return html;
	}

	function renderPaymentGatewayStatePreview(cfg, previewState) {
		var runtimeState = previewState && previewState.runtimeState ? String(previewState.runtimeState) : 'default';
		var gateways = (cfg.payment && Array.isArray(cfg.payment.gateway_order) && cfg.payment.gateway_order.length) ? cfg.payment.gateway_order : ['cod', 'card', 'sbp'];
		var html = '<div class="mp-cc-admin-gateway-preview">';
		for (var i = 0; i < gateways.length; i += 1) {
			var key = String(gateways[i] || '');
			if (!key) {
				continue;
			}
			var active = i === 0 ? ' is-active' : '';
			var runtimeClass = runtimeState !== 'default' ? ' is-' + escapeHtml(runtimeState) : '';
			html += '<div class="mp-cc-admin-gateway-preview__item' + active + runtimeClass + '"><strong>' + escapeHtml(key) + '</strong><small>' + escapeHtml(cfg.payment.card_style || 'default') + '</small></div>';
		}
		html += '</div>';
		return html;
	}

	function renderSummaryReviewPreview(config, previewState) {
		var runtime = previewState && previewState.runtime ? previewState.runtime : {};
		var summary = runtime.summary && typeof runtime.summary === 'object' ? runtime.summary : {};
		var scenario = previewState && previewState.scenario ? String(previewState.scenario) : 'pickup';
		var html = '';
		html += '<div class="mp-cc-admin-summary-review">';
		html += '<div class="mp-cc-admin-summary-review__summary"><strong>' + escapeHtml(config.summaryTitle || 'Сводка') + '</strong>';
		html += '<p>' + escapeHtml(config.subtotalLabel || 'Подытог') + ': ' + escapeHtml(summary.subtotal || '—') + '</p>';
		if (scenario !== 'pickup') {
			html += '<p>' + escapeHtml(config.shippingLabel || 'Доставка') + ': ' + escapeHtml(summary.shipping || '—') + '</p>';
		}
		html += '<p>' + escapeHtml(config.discountLabel || 'Скидка') + ': ' + escapeHtml(summary.discount || '—') + '</p>';
		html += '<p>' + escapeHtml(config.giftCardLabel || 'Подарочная карта') + ': ' + escapeHtml(summary.giftCard || '—') + '</p>';
		html += '<p><strong>' + escapeHtml(config.totalLabel || 'Итого') + ': ' + escapeHtml(summary.total || '—') + '</strong></p>';
		html += '</div>';
		html += '<div class="mp-cc-admin-summary-review__order"><strong>Order review (mock)</strong><p>Сценарий: ' + escapeHtml(scenario) + '</p><p>Runtime: ' + escapeHtml(String((previewState && previewState.runtimeState) || 'default')) + '</p><p>Товары: Rose Perfume x1, Gift Box x2</p></div>';
		html += '</div>';
		return html;
	}

	function applySandboxScenario(previewStore, scenarioKey) {
		var key = String(scenarioKey || '');
		if (key === 'delivery_with_coupon') {
			previewStore.setState({
				scenario: 'krasnoyarsk_delivery',
				runtimeState: 'success',
				runtime: {
					summary: {
						items: 3,
						subtotal: '3 670 ₽',
						shipping: '490 ₽',
						discount: '-500 ₽',
						giftCard: '0 ₽',
						tax: '200 ₽',
						total: '3 860 ₽'
					},
					fulfillment: {
						scenario: 'Доставка по Красноярску',
						date: '2026-04-22',
						pickupPoint: 'Курьер'
					}
				}
			});
			return;
		}
		if (key === 'payment_error_case') {
			previewStore.setState({
				scenario: 'other_city_delivery',
				runtimeState: 'error',
				runtime: {
					summary: {
						items: 3,
						subtotal: '3 670 ₽',
						shipping: '700 ₽',
						discount: '-0 ₽',
						giftCard: '-100 ₽',
						tax: '250 ₽',
						total: '4 520 ₽'
					},
					fulfillment: {
						scenario: 'Доставка в другой город',
						date: '2026-04-25',
						pickupPoint: 'Транспортная компания'
					}
				}
			});
			return;
		}
		previewStore.setState({
			scenario: 'pickup',
			runtimeState: 'default',
			runtime: createPreviewRuntimeMock()
		});
	}

	function renderSuccessPreview(runtime) {
		var rt = runtime && typeof runtime === 'object' ? runtime : {};
		var summary = rt.summary && typeof rt.summary === 'object' ? rt.summary : {};
		var contact = rt.contact && typeof rt.contact === 'object' ? rt.contact : {};
		var fulfillment = rt.fulfillment && typeof rt.fulfillment === 'object' ? rt.fulfillment : {};
		var html = '';
		html += '<section class="mp-cc-admin-preview mp-cc-admin-preview--success" id="mp-cc-admin-success-preview">';
		html += '<h2>Success Screen Preview</h2>';
		html += '<div class="mp-cc-admin-preview__date-grid">';
		html += '<article><strong>Fulfillment</strong><p>Сценарий: ' + escapeHtml(String(fulfillment.scenario || '—')) + '</p><p>Дата: ' + escapeHtml(String(fulfillment.date || '—')) + '</p><p>Точка: ' + escapeHtml(String(fulfillment.pickupPoint || '—')) + '</p></article>';
		html += '<article><strong>Contact Summary</strong><p>' + escapeHtml(String(contact.fullName || '—')) + '</p><p>' + escapeHtml(String(contact.emailMasked || '—')) + '</p><p>' + escapeHtml(String(contact.phoneMasked || '—')) + '</p></article>';
		html += '<article><strong>Financial Summary</strong><p>Subtotal: ' + escapeHtml(String(summary.subtotal || '—')) + '</p><p>Shipping: ' + escapeHtml(String(summary.shipping || '—')) + '</p><p>Total: <strong>' + escapeHtml(String(summary.total || '—')) + '</strong></p></article>';
		html += '</div>';
		html += '</section>';
		return html;
	}

	function renderPreview(config, previewState) {
		var html = '';
		html += '<section class="mp-cc-admin-preview" id="mp-cc-admin-step1-preview"';
		html += ' data-card-compact="' + (config.cardCompact ? '1' : '0') + '"';
		html += ' data-card-emphasis="' + escapeHtml(config.cardEmphasis) + '"';
		html += ' data-summary-emphasis="' + escapeHtml(config.summaryEmphasis) + '"';
		html += '>';
		html += '<h2>Step 1 Preview</h2>';
		html += '<p>Device: <strong>' + escapeHtml(String((previewState && previewState.device) || 'desktop')) + '</strong>, interaction: <strong>' + escapeHtml(String((previewState && previewState.interactionState) || 'default')) + '</strong></p>';
		html += '<div class="mp-cc-admin-preview__grid">';
		html += '<article class="mp-cc-admin-preview__card">';
		html += '<h3>' + escapeHtml(config.title) + '</h3>';
		html += '<div class="mp-cc-admin-preview__item"><strong>Rose Perfume</strong><span>1 x 2 490 ₽</span></div>';
		html += '<div class="mp-cc-admin-preview__item"><strong>Gift Box</strong><span>2 x 590 ₽</span></div>';
		html += '</article>';
		html += '<aside class="mp-cc-admin-preview__summary">';
		html += '<h3>' + escapeHtml(config.summaryTitle) + '</h3>';
		html += '<p>' + escapeHtml(config.itemsLabel) + ': <strong>3</strong></p>';
		html += '<p>' + escapeHtml(config.subtotalLabel) + ': <strong>3 670 ₽</strong></p>';
		if (!config.isPickupPreview) {
			html += '<p>' + escapeHtml(config.shippingLabel) + ': <strong>490 ₽</strong></p>';
		}
		html += '<p>' + escapeHtml(config.discountLabel) + ': <strong>-200 ₽</strong></p>';
		html += '<p>' + escapeHtml(config.giftCardLabel) + ': <strong>-100 ₽</strong></p>';
		html += '<p>' + escapeHtml(config.taxLabel) + ': <strong>200 ₽</strong></p>';
		html += '<p><strong>' + escapeHtml(config.totalLabel) + ': 4 060 ₽</strong></p>';
		html += '<div class="mp-cc-admin-preview__actions">';
		html += '<button type="button">' + escapeHtml(config.continueLabel) + '</button>';
		html += '<a href="#">' + escapeHtml(config.returnLabel) + '</a>';
		html += '</div>';
		html += '</aside>';
		html += '</div>';
		html += '<div class="mp-cc-admin-preview__empty">';
		html += '<p><strong>' + escapeHtml(config.emptyTitle) + '</strong></p>';
		if (config.emptyMessage) {
			html += '<p>' + escapeHtml(config.emptyMessage) + '</p>';
		}
		html += '<a href="#">' + escapeHtml(config.emptyCta) + '</a>';
		html += '</div>';
		if (config.pickupPoint) {
			html += '<div class="mp-cc-admin-preview__pickup">';
			html += '<h3>Pickup block preview</h3>';
			html += '<p><strong>' + escapeHtml(String(config.pickupPoint.title || '')) + '</strong></p>';
			if (config.pickupPoint.address) {
				html += '<p>' + escapeHtml(String(config.pickupPoint.address)) + '</p>';
			}
			if (config.pickupPoint.description) {
				html += '<p>' + escapeHtml(String(config.pickupPoint.description)) + '</p>';
			}
			html += '<div class="mp-cc-admin-preview__pickup-map">Map slot reserved</div>';
			html += '</div>';
		}
		html += renderSummaryReviewPreview(config, previewState);
		html += '</section>';
		return html;
	}

	function renderScenarioPreview(config, previewState) {
		var order = Array.isArray(config.cardOrder) ? config.cardOrder : ['pickup', 'delivery'];
		var selectedScenario = previewState && previewState.scenario ? String(previewState.scenario) : config.defaultScenario;
		var html = '';
		html += '<section class="mp-cc-admin-preview mp-cc-admin-preview--scenario" id="mp-cc-admin-scenario-preview">';
		html += '<h2>Scenario Cards Preview</h2>';
		html += '<p>Default scenario: <strong>' + escapeHtml(config.defaultScenario) + '</strong></p>';
		html += '<div class="mp-cc-admin-preview__scenario-grid"';
		html += ' data-desktop="' + escapeHtml(String(config.responsive.desktopColumns)) + '"';
		html += ' data-tablet="' + escapeHtml(String(config.responsive.tabletColumns)) + '"';
		html += ' data-mobile="' + escapeHtml(String(config.responsive.mobileColumns)) + '"';
		html += ' data-density="' + escapeHtml(config.responsive.cardDensity) + '">';
		for (var i = 0; i < order.length; i += 1) {
			var key = String(order[i] || '');
			if (!config.cards[key]) {
				continue;
			}
			var card = config.cards[key];
			var selectedClass = selectedScenario.indexOf('pickup') > -1 && key === 'pickup' ? ' is-selected' : (selectedScenario.indexOf('delivery') > -1 && key === 'delivery' ? ' is-selected' : '');
			html += '<article class="mp-cc-admin-preview__scenario-card' + selectedClass + '">';
			html += '<div class="mp-cc-admin-preview__scenario-icon mp-cc-admin-preview__scenario-icon--' + escapeHtml(card.iconStyle) + '">' + escapeHtml(card.iconVariant) + '</div>';
			html += '<h3>' + escapeHtml(card.title) + '</h3>';
			if (card.description) {
				html += '<p>' + escapeHtml(card.description) + '</p>';
			}
			if (card.helper) {
				html += '<small>' + escapeHtml(card.helper) + '</small>';
			}
			html += '</article>';
		}
		html += '</div>';
		html += '</section>';
		return html;
	}

	function renderDatePreview(config, previewState) {
		var blockedTotal = config.holidayDates.length + config.closedDates.length;
		var scenario = previewState && previewState.scenario ? String(previewState.scenario) : 'pickup';
		var html = '';
		html += '<section class="mp-cc-admin-preview mp-cc-admin-preview--date" id="mp-cc-admin-date-preview">';
		html += '<h2>Date Step Preview</h2>';
		html += '<p>Scenario: <strong>' + escapeHtml(scenario) + '</strong></p>';
		html += '<h3>' + escapeHtml(config.title) + '</h3>';
		html += '<div class="mp-cc-admin-preview__date-grid">';
		html += '<article><strong>Самовывоз</strong><p>' + escapeHtml(config.helperByScenario.pickup || '—') + '</p></article>';
		html += '<article><strong>Доставка по Красноярску</strong><p>' + escapeHtml(config.helperByScenario.krasnoyarsk_delivery || '—') + '</p></article>';
		html += '<article><strong>Доставка в другой город</strong><p>' + escapeHtml(config.helperByScenario.other_city_delivery || '—') + '</p></article>';
		html += '</div>';
		html += '<div class="mp-cc-admin-preview__date-errors">';
		html += '<p><strong>Invalid date:</strong> ' + escapeHtml(config.errors.invalidDate || '—') + '</p>';
		html += '<p><strong>Empty date:</strong> ' + escapeHtml(config.errors.emptyDate || '—') + '</p>';
		html += '</div>';
		html += '<div class="mp-cc-admin-preview__date-rules">';
		html += '<p><strong>Lead time:</strong> pickup ' + escapeHtml(config.minLeadTime.pickup) + 'd, krasnoyarsk ' + escapeHtml(config.minLeadTime.krasnoyarsk_delivery) + 'd, other city ' + escapeHtml(config.minLeadTime.other_city_delivery) + 'd</p>';
		html += '<p><strong>Weekdays:</strong> pickup [' + escapeHtml(config.weekdayRules.pickup.join(',')) + '] | krasnoyarsk [' + escapeHtml(config.weekdayRules.krasnoyarsk_delivery.join(',')) + '] | other city [' + escapeHtml(config.weekdayRules.other_city_delivery.join(',')) + ']</p>';
		html += '<p><strong>Blocked dates:</strong> holidays ' + escapeHtml(config.holidayDates.length) + ', closed ' + escapeHtml(config.closedDates.length) + ', total ' + escapeHtml(blockedTotal) + '</p>';
		html += '<p><strong>Calendar style:</strong> ' + escapeHtml(config.calendarStyle.density) + ', ' + escapeHtml(config.calendarStyle.dayShape) + ', ' + escapeHtml(config.calendarStyle.highlightStyle) + ', weekend tint: ' + escapeHtml(config.calendarStyle.showWeekendTint ? 'on' : 'off') + '</p>';
		html += '</div>';
		html += renderCalendarStatePreview(config, previewState);
		html += '</section>';
		return html;
	}

	function renderDeliveryConfigPreview(cfg) {
		var cat = cfg.shippingCatalog || {};
		var methods = cat.methods && typeof cat.methods === 'object' ? cat.methods : {};
		var sort = Array.isArray(cat.sortOrder) ? cat.sortOrder : Object.keys(methods);
		var mockSubtotal = Number((cat.preview && cat.preview.mock_subtotal) || 3670);
		var mockDiscount = Number((cat.preview && cat.preview.mock_discount) || 200);
		var mockTax = Number((cat.preview && cat.preview.mock_tax) || 160);
		var html = '';
		html += '<section class="mp-cc-admin-preview mp-cc-admin-preview--delivery" id="mp-cc-admin-delivery-preview">';
		html += '<h2>Shipping Catalog Preview</h2>';
		html += '<p>Sort order: <code>' + escapeHtml(sort.join(', ')) + '</code></p>';
		html += '<div class="mp-cc-admin-preview__date-grid">';
		for (var i = 0; i < sort.length; i += 1) {
			var id = String(sort[i] || '');
			var method = methods[id] && typeof methods[id] === 'object' ? methods[id] : null;
			if (!method || method.active === false) {
				continue;
			}
			var price = Number(method.price || 0);
			var total = Math.max(0, mockSubtotal - mockDiscount + mockTax + price);
			html += '<article>';
			html += '<strong>' + escapeHtml(String(method.title || id)) + '</strong>';
			html += '<p>id: <code>' + escapeHtml(id) + '</code></p>';
			html += '<p>price: ' + escapeHtml(String(Math.round(price))) + ' ₽, eta: ' + escapeHtml(String(method.eta || '—')) + '</p>';
			html += '<p>requires address: ' + escapeHtml(method.requires_address ? 'yes' : 'no') + '</p>';
			var vis = Array.isArray(method.visibility_scenarios) ? method.visibility_scenarios : [];
			html += '<p>visibility_scenarios: <code>' + escapeHtml(vis.length ? vis.join(', ') : '(пусто — метод доступен при любом сценарии)') + '</code></p>';
			if (method.tariffs && typeof method.tariffs === 'object') {
				var tks = Object.keys(method.tariffs);
				var tj;
				for (tj = 0; tj < tks.length; tj += 1) {
					var tk = tks[tj];
					var trw = method.tariffs[tk] && typeof method.tariffs[tk] === 'object' ? method.tariffs[tk] : {};
					if (trw.active === false) {
						continue;
					}
					html += '<p class="mp-cc-admin-preview__tariff-line"><code>' + escapeHtml(tk) + '</code> — ' + escapeHtml(String(trw.title || '')) + ', ' + escapeHtml(String(Math.round(Number(trw.price || 0)))) + ' ₽, eta ' + escapeHtml(String(trw.eta || '—')) + '</p>';
				}
			}
			html += '<p><strong>Preview total:</strong> ' + escapeHtml(String(Math.round(total))) + ' ₽</p>';
			html += '</article>';
		}
		html += '</div>';
		html += '<div class="mp-cc-admin-preview__date-errors">';
		html += '<p><strong>Error copy method:</strong> ' + escapeHtml(String((cat.errorCopy && cat.errorCopy.method_unavailable) || '—')) + '</p>';
		html += '<p><strong>Error copy tariff:</strong> ' + escapeHtml(String((cat.errorCopy && cat.errorCopy.tariff_unavailable) || '—')) + '</p>';
		html += '</div>';
		html += '<p><button type="button" class="button button-secondary" data-mp-cc-delivery-bulk-apply="1">Применить bulk update к ценам/ETA в форме</button></p>';
		html += '</section>';
		return html;
	}

	function applyDeliveryBulkUpdate(liveCfg) {
		var cat = liveCfg.shippingCatalog || {};
		var methods = cat.methods && typeof cat.methods === 'object' ? cat.methods : {};
		var bulk = cat.bulkUpdate || {};
		var pct = Number(bulk.seasonal_delta_pct || 0);
		var abs = Number(bulk.seasonal_delta_abs || 0);
		var suffix = String(bulk.eta_suffix || '').trim();
		var p = 'mp_custom_checkout_settings[delivery][shipping_catalog][methods]';
		var methodIds = Object.keys(methods);
		for (var i = 0; i < methodIds.length; i += 1) {
			var id = methodIds[i];
			var m = methods[id] || {};
			if (m.active === false) {
				continue;
			}
			var nextPrice = Number(m.price || 0);
			if (pct) {
				nextPrice = nextPrice + (nextPrice * pct / 100);
			}
			if (abs) {
				nextPrice += abs;
			}
			nextPrice = Math.max(0, Math.round(nextPrice));
			writeFormValue(p + '[' + id + '][price]', String(nextPrice));
			if (suffix) {
				var eta = String(m.eta || '');
				var nextEta = eta.indexOf(suffix) > -1 ? eta : (eta ? eta + ' ' + suffix : suffix);
				writeFormValue(p + '[' + id + '][eta]', nextEta);
			}
		}
	}

	function validateDeliveryConfigConsistency(cfg) {
		var errors = [];
		var allowedScenarios = { pickup: true, krasnoyarsk_delivery: true, other_city_delivery: true };
		var cat = cfg.shippingCatalog || {};
		var methods = cat.methods && typeof cat.methods === 'object' ? cat.methods : {};
		var sort = Array.isArray(cat.sortOrder) ? cat.sortOrder : [];
		for (var i = 0; i < sort.length; i += 1) {
			if (!methods[sort[i]]) {
				errors.push('sort_order содержит неизвестный метод: ' + sort[i]);
			}
		}
		var methodIds = Object.keys(methods);
		for (var m = 0; m < methodIds.length; m += 1) {
			var id = methodIds[m];
			var method = methods[id] || {};
			if (method.active !== false && !String(method.title || '').trim()) {
				errors.push('Пустое название у активного метода: ' + id);
			}
			if (Number(method.price || 0) < 0) {
				errors.push('Отрицательная цена у метода: ' + id);
			}
			if (Array.isArray(method.visibility_scenarios)) {
				for (var si = 0; si < method.visibility_scenarios.length; si += 1) {
					var sc = String(method.visibility_scenarios[si] || '').trim();
					if (!sc) {
						continue;
					}
					if (!allowedScenarios[sc]) {
						errors.push('Неизвестный сценарий в visibility_scenarios у метода «' + id + '»: ' + sc + ' (допустимо: pickup, krasnoyarsk_delivery, other_city_delivery)');
					}
				}
			}
			if (method.tariffs && typeof method.tariffs === 'object') {
				var tariffKeys = Object.keys(method.tariffs);
				var ti;
				for (ti = 0; ti < tariffKeys.length; ti += 1) {
					var tid = tariffKeys[ti];
					var tr = method.tariffs[tid] && typeof method.tariffs[tid] === 'object' ? method.tariffs[tid] : {};
					if (tr.active !== false && !String(tr.title || '').trim()) {
						errors.push('Пустое название у активного тарифа «' + tid + '» (метод ' + id + ')');
					}
					if (Number(tr.price || 0) < 0) {
						errors.push('Отрицательная цена у тарифа «' + tid + '» (метод ' + id + ')');
					}
				}
			}
		}
		return errors;
	}

	var DELIVERY_STUDIO_CATALOG = 'mp_custom_checkout_settings[delivery][shipping_catalog]';

	function deliveryStudioNotifyForm(name) {
		var $f = $('[name="' + name + '"]').first();
		if ($f.length) {
			$f.trigger('input').trigger('change');
		}
	}

	function readDeliverySortOrderArray() {
		var raw = String(readFormValue(DELIVERY_STUDIO_CATALOG + '[sort_order]', '') || '');
		var arr = raw.split(',').map(function (s) {
			return String(s || '').trim();
		}).filter(Boolean);
		var base = getDeliveryConfigFromRuntime();
		var live = readLiveDeliveryConfig(base);
		var methods = live.shippingCatalog && live.shippingCatalog.methods ? live.shippingCatalog.methods : {};
		var out = [];
		var seen = {};
		var i;
		var id;
		for (i = 0; i < arr.length; i += 1) {
			id = arr[i];
			if (methods[id] && !seen[id]) {
				seen[id] = true;
				out.push(id);
			}
		}
		var keys = Object.keys(methods);
		for (i = 0; i < keys.length; i += 1) {
			id = keys[i];
			if (!seen[id]) {
				seen[id] = true;
				out.push(id);
			}
		}
		return out;
	}

	function writeDeliverySortOrderArray(order) {
		writeFormValue(DELIVERY_STUDIO_CATALOG + '[sort_order]', order.join(', '));
		deliveryStudioNotifyForm(DELIVERY_STUDIO_CATALOG + '[sort_order]');
	}

	function mountDeliveryStudioPanel() {
		var section = detectActiveSettingsSection();
		var $fields = $('.mp-cc-admin-shell__fields').first();
		if (section !== 'delivery' || !$fields.length) {
			$('#mp-cc-delivery-studio').remove();
			return;
		}
		if (!$('[name^="mp_custom_checkout_settings[delivery]"]').length) {
			return;
		}
		var base = getDeliveryConfigFromRuntime();
		var live = readLiveDeliveryConfig(base);
		var cat = live.shippingCatalog || {};
		var methods = cat.methods && typeof cat.methods === 'object' ? cat.methods : {};
		var displaySort = readDeliverySortOrderArray();
		var html = '';
		html += '<div id="mp-cc-delivery-studio" class="mp-cc-delivery-studio">';
		html += '<header class="mp-cc-delivery-studio__head">';
		html += '<h3 class="mp-cc-delivery-studio__title">Каталог доставки</h3>';
		html += '<p class="mp-cc-delivery-studio__lead">Порядок способов синхронизируется с полем <code>sort_order</code>. Тарифы курьера и ПВЗ — с соответствующими полями формы. Дополнительные позиции используют резервные слоты <code>slot_1</code> / <code>slot_2</code> (так сохранение совместимо с деревом настроек).</p>';
		html += '</header>';
		html += '<section class="mp-cc-delivery-studio__block">';
		html += '<h4>Порядок на витрине</h4>';
		html += '<ol class="mp-cc-delivery-studio__sort-list">';
		var i;
		for (i = 0; i < displaySort.length; i += 1) {
			var mid = String(displaySort[i] || '');
			if (!mid || !methods[mid]) {
				continue;
			}
			var mm = methods[mid];
			var label = String(mm.title || mid);
			var inactive = mm.active === false;
			html += '<li class="mp-cc-delivery-studio__sort-item"' + (inactive ? ' data-inactive="1"' : '') + '>';
			html += '<span class="mp-cc-delivery-studio__sort-label">' + escapeHtml(label) + ' <code>' + escapeHtml(mid) + '</code></span>';
			html += '<span class="mp-cc-delivery-studio__sort-actions">';
			html += '<button type="button" class="button button-small" data-mp-cc-delivery-sort-up="' + escapeHtml(mid) + '"' + (i === 0 ? ' disabled' : '') + '>↑</button>';
			html += '<button type="button" class="button button-small" data-mp-cc-delivery-sort-down="' + escapeHtml(mid) + '"' + (i >= displaySort.length - 1 ? ' disabled' : '') + '>↓</button>';
			html += '</span></li>';
		}
		html += '</ol></section>';
		var tariffMethods = ['courier', 'pvz'];
		for (var tm = 0; tm < tariffMethods.length; tm += 1) {
			var methodId = tariffMethods[tm];
			var method = methods[methodId];
			if (!method || !method.tariffs || typeof method.tariffs !== 'object') {
				continue;
			}
			html += '<section class="mp-cc-delivery-studio__block">';
			html += '<h4>Тарифы: ' + escapeHtml(String(method.title || methodId)) + ' <code>' + escapeHtml(methodId) + '</code></h4>';
			html += '<table class="mp-cc-delivery-studio__tariff-table"><thead><tr><th>ID</th><th>Название</th><th>Цена</th><th>ETA</th><th>Активен</th><th></th></tr></thead><tbody>';
			var tariffIds = Object.keys(method.tariffs);
			var ti2;
			for (ti2 = 0; ti2 < tariffIds.length; ti2 += 1) {
				var tariffId = tariffIds[ti2];
				var tr = method.tariffs[tariffId] && typeof method.tariffs[tariffId] === 'object' ? method.tariffs[tariffId] : {};
				var isSlot = tariffId === 'slot_1' || tariffId === 'slot_2';
				var active = tr.active !== false;
				html += '<tr>';
				html += '<td><code>' + escapeHtml(tariffId) + '</code></td>';
				html += '<td><input type="text" class="regular-text mp-cc-delivery-studio__input" data-mp-cc-tariff-field="title" data-method="' + escapeHtml(methodId) + '" data-tariff="' + escapeHtml(tariffId) + '" value="' + escapeHtml(String(tr.title || '')) + '" /></td>';
				html += '<td><input type="number" min="0" step="1" class="small-text mp-cc-delivery-studio__input" data-mp-cc-tariff-field="price" data-method="' + escapeHtml(methodId) + '" data-tariff="' + escapeHtml(tariffId) + '" value="' + escapeHtml(String(Math.max(0, Math.round(Number(tr.price || 0))))) + '" /></td>';
				html += '<td><input type="text" class="regular-text mp-cc-delivery-studio__input" data-mp-cc-tariff-field="eta" data-method="' + escapeHtml(methodId) + '" data-tariff="' + escapeHtml(tariffId) + '" value="' + escapeHtml(String(tr.eta || '')) + '" /></td>';
				html += '<td><label class="mp-cc-delivery-studio__check"><input type="checkbox" data-mp-cc-tariff-field="active" data-method="' + escapeHtml(methodId) + '" data-tariff="' + escapeHtml(tariffId) + '"' + (active ? ' checked' : '') + ' /> да</label></td>';
				html += '<td>';
				if (isSlot) {
					if (active) {
						html += '<button type="button" class="button button-small" data-mp-cc-tariff-slot-off="' + escapeHtml(methodId) + '" data-tariff="' + escapeHtml(tariffId) + '">Очистить слот</button>';
					} else {
						html += '<button type="button" class="button button-small button-primary" data-mp-cc-tariff-slot-on="' + escapeHtml(methodId) + '" data-tariff="' + escapeHtml(tariffId) + '">Включить слот</button>';
					}
				} else {
					html += '<span class="description">базовый</span>';
				}
				html += '</td></tr>';
			}
			html += '</tbody></table></section>';
		}
		html += '</div>';
		var $existing = $('#mp-cc-delivery-studio');
		if ($existing.length) {
			$existing.replaceWith(html);
		} else {
			$fields.prepend(html);
		}
	}

	var deliveryStudioInteractionsBound = false;

	function bindDeliveryStudioInteractions() {
		if (deliveryStudioInteractionsBound) {
			return;
		}
		deliveryStudioInteractionsBound = true;
		$(document).on('click', '[data-mp-cc-delivery-sort-up]', function () {
			var mid = String($(this).attr('data-mp-cc-delivery-sort-up') || '');
			var order = readDeliverySortOrderArray();
			var idx = order.indexOf(mid);
			if (idx <= 0) {
				return;
			}
			var prev = order[idx - 1];
			order[idx - 1] = mid;
			order[idx] = prev;
			writeDeliverySortOrderArray(order);
			mountDeliveryStudioPanel();
		});
		$(document).on('click', '[data-mp-cc-delivery-sort-down]', function () {
			var mid = String($(this).attr('data-mp-cc-delivery-sort-down') || '');
			var order = readDeliverySortOrderArray();
			var idx = order.indexOf(mid);
			if (idx < 0 || idx >= order.length - 1) {
				return;
			}
			var nxt = order[idx + 1];
			order[idx + 1] = mid;
			order[idx] = nxt;
			writeDeliverySortOrderArray(order);
			mountDeliveryStudioPanel();
		});
		$(document).on('input change', '#mp-cc-delivery-studio [data-mp-cc-tariff-field]', function () {
			var $el = $(this);
			var field = String($el.attr('data-mp-cc-tariff-field') || '');
			var methodId = String($el.attr('data-method') || '');
			var tariffId = String($el.attr('data-tariff') || '');
			if (!field || !methodId || !tariffId) {
				return;
			}
			var name = DELIVERY_STUDIO_CATALOG + '[methods][' + methodId + '][tariffs][' + tariffId + '][' + field + ']';
			if (field === 'active') {
				writeFormValue(name, $el.is(':checked'));
			} else {
				writeFormValue(name, $el.val());
			}
			deliveryStudioNotifyForm(name);
		});
		$(document).on('click', '[data-mp-cc-tariff-slot-on]', function () {
			var methodId = String($(this).attr('data-mp-cc-tariff-slot-on') || '');
			var tariffId = String($(this).attr('data-tariff') || '');
			if (!methodId || !tariffId) {
				return;
			}
			var basePath = DELIVERY_STUDIO_CATALOG + '[methods][' + methodId + '][tariffs][' + tariffId + ']';
			writeFormValue(basePath + '[active]', true);
			writeFormValue(basePath + '[title]', 'Доп. тариф');
			writeFormValue(basePath + '[price]', '0');
			writeFormValue(basePath + '[eta]', '');
			deliveryStudioNotifyForm(basePath + '[active]');
			mountDeliveryStudioPanel();
		});
		$(document).on('click', '[data-mp-cc-tariff-slot-off]', function () {
			var methodId = String($(this).attr('data-mp-cc-tariff-slot-off') || '');
			var tariffId = String($(this).attr('data-tariff') || '');
			if (!methodId || !tariffId) {
				return;
			}
			var basePath = DELIVERY_STUDIO_CATALOG + '[methods][' + methodId + '][tariffs][' + tariffId + ']';
			writeFormValue(basePath + '[active]', false);
			writeFormValue(basePath + '[title]', '');
			writeFormValue(basePath + '[price]', '0');
			writeFormValue(basePath + '[eta]', '');
			deliveryStudioNotifyForm(basePath + '[active]');
			mountDeliveryStudioPanel();
		});
	}

	function refreshDeliveryAdminUi() {
		enhanceDeliveryFields();
		bindDeliveryStudioInteractions();
		mountDeliveryStudioPanel();
	}

	function renderStepFourPreview(cfg, previewState) {
		var order = Array.isArray(cfg.contact.fieldOrder) ? cfg.contact.fieldOrder : [];
		var runtimeState = previewState && previewState.runtimeState ? String(previewState.runtimeState) : 'default';
		var interaction = previewState && previewState.interactionState ? String(previewState.interactionState) : 'default';
		var html = '';
		html += '<section class="mp-cc-admin-preview mp-cc-admin-preview--step4" id="mp-cc-admin-step4-preview">';
		html += '<h2>Step 4 Contact Preview</h2>';
		html += '<p>Runtime: <strong>' + escapeHtml(runtimeState) + '</strong>, interaction: <strong>' + escapeHtml(interaction) + '</strong></p>';
		html += '<p><strong>' + escapeHtml(cfg.contact.title) + '</strong></p>';
		if (cfg.contact.intro) {
			html += '<p>' + escapeHtml(cfg.contact.intro) + '</p>';
		}
		html += '<div class="mp-cc-admin-preview__date-rules">';
		html += '<p><strong>Layout:</strong> desktop ' + escapeHtml(String(cfg.contact.layout.desktop_columns || 3)) + ', tablet ' + escapeHtml(String(cfg.contact.layout.tablet_columns || 2)) + ', mobile ' + escapeHtml(String(cfg.contact.layout.mobile_columns || 1)) + '</p>';
		html += '<p><strong>Field states:</strong> invalid=' + escapeHtml(String(cfg.contact.states.invalid_style || 'default')) + ', hint=' + escapeHtml(String(cfg.contact.states.hint_style || 'default')) + ', focus=' + escapeHtml(String(cfg.contact.states.focus_style || 'default')) + ', disabled=' + escapeHtml(String(cfg.contact.states.disabled_style || 'default')) + '</p>';
		html += '<p><strong>Constraints:</strong> age ' + escapeHtml(String(cfg.contact.constraints.birthdate_min_age || 0)) + '…' + escapeHtml(String(cfg.contact.constraints.birthdate_max_age || 120)) + ', phone digits override=' + escapeHtml(String(cfg.contact.constraints.phone_digits_override || 0)) + '</p>';
		html += '</div>';
		html += '<div class="mp-cc-admin-preview__date-grid">';
		for (var i = 0; i < order.length; i += 1) {
			var key = String(order[i] || '');
			if (!key) {
				continue;
			}
			var visible = cfg.contact.fieldVisibility[key] !== false;
			var required = Boolean(cfg.contact.fieldRequired[key]);
			var label = cfg.contact.labels[key] || key;
			var ph = cfg.contact.placeholders[key] || '';
			var hint = cfg.contact.hints[key] || '';
			html += '<article>';
			html += '<strong>' + escapeHtml(label) + '</strong>';
			html += '<p>key: ' + escapeHtml(key) + '</p>';
			html += '<p>visible: <strong>' + escapeHtml(visible ? 'yes' : 'no') + '</strong>, required: <strong>' + escapeHtml(required ? 'yes' : 'no') + '</strong></p>';
			if (ph) {
				html += '<p>placeholder: ' + escapeHtml(ph) + '</p>';
			}
			if (hint) {
				html += '<p>hint: ' + escapeHtml(hint) + '</p>';
			}
			html += '</article>';
		}
		html += '</div>';
		html += '<div class="mp-cc-admin-preview__date-errors">';
		html += '<p><strong>Validation texts:</strong></p>';
		html += '<p>required: ' + escapeHtml(String(cfg.contact.validationMessages.required || '—')) + '</p>';
		html += '<p>email_invalid: ' + escapeHtml(String(cfg.contact.validationMessages.email_invalid || '—')) + '</p>';
		html += '<p>phone_required: ' + escapeHtml(String(cfg.contact.validationMessages.phone_required || '—')) + '</p>';
		html += '<p>phone_format: ' + escapeHtml(String(cfg.contact.validationMessages.phone_format || '—')) + '</p>';
		html += '<p>step_blocked: ' + escapeHtml(String(cfg.contact.validationMessages.step_blocked || '—')) + '</p>';
		html += '<p>conditions_required: ' + escapeHtml(String(cfg.contact.validationMessages.conditions_required || '—')) + '</p>';
		html += '<p><strong>AJAX fallback:</strong> draft=' + escapeHtml(String(cfg.contact.ajaxMessages.draft_save_failed || '—')) + ', sync=' + escapeHtml(String(cfg.contact.ajaxMessages.step_sync_failed || '—')) + ', scenario=' + escapeHtml(String(cfg.contact.ajaxMessages.scenario_sync_failed || '—')) + '</p>';
		html += '</div>';
		html += '<div class="mp-cc-admin-preview__date-grid">';
		html += '<article>';
		html += '<strong>Preview: field-level error</strong>';
		html += '<p>Email</p>';
		html += '<p class="mp-cc-admin-step4-error-sample">' + escapeHtml(String(cfg.contact.validationMessages.email_invalid || 'Введите корректный email.')) + '</p>';
		html += '</article>';
		html += '<article>';
		html += '<strong>Preview: invalid step</strong>';
		html += '<p>Progress step marked invalid + banner text:</p>';
		html += '<p class="mp-cc-admin-step4-error-sample">' + escapeHtml(String(cfg.contact.validationMessages.step_blocked || 'Заполните обязательные поля текущего шага.')) + '</p>';
		html += '</article>';
		html += '</div>';
		html += '<div class="mp-cc-admin-preview__date-rules">';
		html += '<p><strong>Coupon placement:</strong> ' + escapeHtml(String(cfg.discountLayout.placement || 'step_4')) + ', separate-step ready=' + escapeHtml(cfg.discountLayout.separate_step_enabled ? 'yes' : 'no') + '</p>';
		html += '<p><strong>Discount styles:</strong> empty=' + escapeHtml(String(cfg.discountStyles.state_empty || 'default')) + ', success=' + escapeHtml(String(cfg.discountStyles.state_success || 'success')) + ', error=' + escapeHtml(String(cfg.discountStyles.state_error || 'error')) + '</p>';
		html += '<p><strong>Payment block:</strong> title="' + escapeHtml(String(cfg.payment.title || 'Способ оплаты')) + '", surface=' + escapeHtml(String(cfg.payment.card_surface || 'visual')) + ', auto_classic_if_empty=' + escapeHtml(cfg.payment.auto_classic_on_empty_gateway_fields === false ? 'off' : 'on') + ', decorative=' + escapeHtml(cfg.payment.decorative_card_fields === false ? 'off' : 'on') + ', style=' + escapeHtml(String(cfg.payment.card_style || 'default')) + ', description=' + escapeHtml(cfg.payment.show_description === false ? 'off' : 'on') + '</p>';
		html += '<p><strong>Payment states:</strong> loading="' + escapeHtml(String((cfg.payment.messages && cfg.payment.messages.loading) || '—')) + '", success="' + escapeHtml(String((cfg.payment.messages && cfg.payment.messages.success) || '—')) + '", error="' + escapeHtml(String((cfg.payment.messages && cfg.payment.messages.error) || '—')) + '"</p>';
		html += '<p><strong>Payment diagnostics:</strong> ' + escapeHtml(cfg.payment.diagnostics && cfg.payment.diagnostics.enabled === false ? 'off' : 'on') + '</p>';
		var smrCfg = cfg.payment.summary_mini_review && typeof cfg.payment.summary_mini_review === 'object' ? cfg.payment.summary_mini_review : {};
		html += '<p><strong>Summary mini-review:</strong> enabled=' + escapeHtml(smrCfg.enabled === false ? 'off' : 'on') + ', show_gateway_id=' + escapeHtml(smrCfg.show_gateway_id ? 'on' : 'off') + ', show_description=' + escapeHtml(smrCfg.show_gateway_description === false ? 'off' : 'on') + '</p>';
		html += '</div>';
		html += '<div class="mp-cc-admin-preview__date-grid mp-cc-admin-step4-sidebar-mock">';
		html += '<article class="mp-cc-admin-sidebar-mock">';
		html += '<strong>Превью: правая колонка (промо + итоги + оплата)</strong>';
		html += '<p>coupon <code>allow_remove_applied</code>=' + escapeHtml(cfg.coupon.allow_remove_applied === false ? 'off' : 'on') + ', <code>summary_section_title</code>=' + escapeHtml(trimNonEmptyAdmin(cfg.coupon.summary_section_title) || '—') + '</p>';
		html += '<div class="mp-cc-admin-sidebar-mock__card">';
		html += '<p class="mp-cc-admin-sidebar-mock__pret">' + escapeHtml(trimNonEmptyAdmin(cfg.coupon.summary_section_title) || 'Промокоды и скидки') + '</p>';
		html += '<div class="mp-cc-admin-sidebar-mock__chiprow"><span class="mp-cc-admin-sidebar-mock__chip">PROMO10: −500 ₽</span><span class="mp-cc-admin-sidebar-mock__x">×</span></div>';
		html += '<div class="mp-cc-admin-sidebar-mock__chiprow"><span class="mp-cc-admin-sidebar-mock__chip">Подарочная карта: −200 ₽</span></div>';
		html += '<p class="mp-cc-admin-sidebar-mock__pret">Итоги</p>';
		html += '<p class="mp-cc-admin-sidebar-mock__row">Подытог: <strong>3 670 ₽</strong></p>';
		html += '<p class="mp-cc-admin-sidebar-mock__row">Итого: <strong>2 970 ₽</strong></p>';
		html += '<div class="mp-cc-admin-sidebar-mock__mini">';
		html += '<p class="mp-cc-admin-sidebar-mock__mint">' + escapeHtml(trimNonEmptyAdmin(smrCfg.title) || 'Способ оплаты') + '</p>';
		html += '<p class="mp-cc-admin-sidebar-mock__minm">' + escapeHtml(trimNonEmptyAdmin(smrCfg.intro) || 'Выбранный метод проведения платежа.') + '</p>';
		html += '<p class="mp-cc-admin-sidebar-mock__minl"><span>Метод</span> <strong>Банковская карта</strong></p>';
		if (smrCfg.show_gateway_id) {
			html += '<p class="mp-cc-admin-sidebar-mock__minl mp-cc-admin-sidebar-mock__minl--muted"><span>Код</span> <code>card_gateway</code></p>';
		}
		html += '<p class="mp-cc-admin-sidebar-mock__minst">' + escapeHtml(trimNonEmptyAdmin(smrCfg.state_loading) || 'Сохраняем выбор…') + '</p>';
		html += '</div></div></article></div>';
		html += '<div class="mp-cc-admin-preview__date-grid">';
		html += '<article>';
		html += '<strong>' + escapeHtml(String(cfg.coupon.title || cfg.giftCard.title || 'Подарочная карта')) + '</strong>';
		html += '<p>' + escapeHtml(String(cfg.coupon.intro || cfg.giftCard.intro || '')) + '</p>';
		html += '<p>Label: ' + escapeHtml(String(cfg.coupon.input_label || cfg.giftCard.input_label || 'Код подарочной карты')) + '</p>';
		html += '<p>Placeholder: ' + escapeHtml(String(cfg.coupon.placeholder || cfg.giftCard.placeholder || '')) + '</p>';
		html += '<p><em>States:</em> empty="' + escapeHtml(String(cfg.coupon.empty_message || cfg.giftCard.empty_message || '')) + '", success="' + escapeHtml(String(cfg.coupon.success_message || cfg.giftCard.success_message || '')) + '", error="' + escapeHtml(String(cfg.coupon.error_message || cfg.giftCard.error_message || '')) + '"</p>';
		html += '<p><em>Одно поле на шаге 4:</em> тексты из <code>coupon_block</code>, при пустых полях подставляются из <code>gift_card_block</code>.</p>';
		html += '</article>';
		html += '</div>';
		html += '<div class="mp-cc-admin-preview__date-grid">';
		html += '<article class="mp-cc-admin-gift-peer-preview">';
		html += '<strong>Превью: подарочная карта рядом с оплатой</strong>';
		html += '<p>Карточный UI: <code>gift_card_block.peer_next_to_payment</code>=' + escapeHtml(cfg.giftCard.peer_next_to_payment === false ? 'off' : 'on') + ', <code>allow_remove_applied</code>=' + escapeHtml(cfg.giftCard.allow_remove_applied === false ? 'off' : 'on') + '</p>';
		var gTitle = trimNonEmptyAdmin(cfg.giftCard.card_title) || trimNonEmptyAdmin(cfg.giftCard.title) || 'Подарочная карта';
		var gSub = trimNonEmptyAdmin(cfg.giftCard.card_subtitle) || trimNonEmptyAdmin(cfg.giftCard.intro) || 'Введите код подарочной карты.';
		html += '<div class="mp-cc-admin-gift-peer-preview__mock" aria-hidden="true">';
		html += '<div class="mp-cc-admin-gift-peer-preview__shell"><span class="mp-cc-admin-gift-peer-preview__badge">Подарок</span>';
		html += '<p class="mp-cc-admin-gift-peer-preview__t">' + escapeHtml(gTitle) + '</p>';
		html += '<p class="mp-cc-admin-gift-peer-preview__s">' + escapeHtml(gSub) + '</p></div>';
		html += '<div class="mp-cc-admin-gift-peer-preview__inlay">';
		html += '<span class="mp-cc-admin-gift-peer-preview__ph"></span>';
		html += '<span class="mp-cc-admin-gift-peer-preview__btn">' + escapeHtml(trimNonEmptyAdmin(cfg.giftCard.apply_label) || 'Применить') + '</span>';
		html += '</div></div>';
		html += '<p class="mp-cc-admin-preview__muted"><code>card_title</code> / <code>card_subtitle</code> / <code>unavailable_message</code> — опционально; пустые значения берутся из основных полей блока.</p>';
		html += '</article></div>';
		html += '<div class="mp-cc-admin-preview__date-grid">';
		html += '<article>';
		html += '<strong>Payment preview: loading</strong>';
		html += '<p class="mp-cc-admin-step4-error-sample">' + escapeHtml(String((cfg.payment.messages && cfg.payment.messages.loading) || 'Сохраняем выбранный способ оплаты...')) + '</p>';
		html += '</article>';
		html += '<article>';
		html += '<strong>Payment preview: success</strong>';
		html += '<p class="mp-cc-admin-step4-error-sample">' + escapeHtml(String((cfg.payment.messages && cfg.payment.messages.success) || 'Способ оплаты обновлён.')) + '</p>';
		html += '</article>';
		html += '<article>';
		html += '<strong>Payment preview: error</strong>';
		html += '<p class="mp-cc-admin-step4-error-sample">' + escapeHtml(String((cfg.payment.messages && cfg.payment.messages.error) || 'Не удалось переключить способ оплаты.')) + '</p>';
		html += '</article>';
		html += '</div>';
		html += renderPaymentGatewayStatePreview(cfg, previewState);
		html += '<p><strong>' + escapeHtml(cfg.address.title) + '</strong></p>';
		html += '<p>Address order: ' + escapeHtml(cfg.address.order.join(', ')) + '</p>';
		if (cfg.geoPreview) {
			var cCodes = Object.keys(cfg.geo || {});
			html += '<div class="mp-cc-admin-preview__date-rules">';
			html += '<p><strong>Geo dependency mode:</strong> enabled</p>';
			html += '<p><strong>Countries:</strong> ' + escapeHtml(String(cCodes.length)) + ' (' + escapeHtml(cCodes.join(', ')) + ')</p>';
			if (cCodes.length) {
				var firstCountry = cfg.geo[cCodes[0]] || {};
				var regions = firstCountry.regions && typeof firstCountry.regions === 'object' ? Object.keys(firstCountry.regions) : [];
				html += '<p><strong>Sample country regions:</strong> ' + escapeHtml(String(regions.length)) + '</p>';
			}
			html += '</div>';
		}
		html += '</section>';
		return html;
	}

	function readFormValue(name, fallback) {
		var $checkbox = $('[name="' + name + '"]').filter('[type="checkbox"]');
		if ($checkbox.length) {
			return $checkbox.is(':checked');
		}
		var $field = $('[name="' + name + '"]').first();
		if (!$field.length) {
			return fallback;
		}
		return String($field.val() || '');
	}

	function writeFormValue(name, value) {
		var $checkbox = $('[name="' + name + '"]').filter('[type="checkbox"]');
		if ($checkbox.length) {
			$checkbox.prop('checked', Boolean(value));
			return;
		}
		var $field = $('[name="' + name + '"]').first();
		if (!$field.length) {
			return;
		}
		$field.val(String(value == null ? '' : value));
	}

	function readLiveConfig(baseConfig) {
		var cfg = $.extend({}, baseConfig);
		cfg.title = readFormValue('mp_custom_checkout_settings[step_1][labels][title]', cfg.title);
		cfg.summaryTitle = readFormValue('mp_custom_checkout_settings[step_1][labels][summary_title]', cfg.summaryTitle);
		cfg.subtotalLabel = readFormValue('mp_custom_checkout_settings[step_1][labels][subtotal_label]', cfg.subtotalLabel);
		cfg.shippingLabel = readFormValue('mp_custom_checkout_settings[step_1][labels][shipping_label]', cfg.shippingLabel);
		cfg.discountLabel = readFormValue('mp_custom_checkout_settings[step_1][labels][discount_label]', cfg.discountLabel);
		cfg.giftCardLabel = readFormValue('mp_custom_checkout_settings[step_1][labels][gift_card_label]', cfg.giftCardLabel);
		cfg.taxLabel = readFormValue('mp_custom_checkout_settings[step_1][labels][tax_label]', cfg.taxLabel);
		cfg.totalLabel = readFormValue('mp_custom_checkout_settings[step_1][labels][total_label]', cfg.totalLabel);
		cfg.itemsLabel = readFormValue('mp_custom_checkout_settings[step_1][labels][items_label]', cfg.itemsLabel);
		cfg.continueLabel = readFormValue('mp_custom_checkout_settings[step_1][labels][continue_label]', cfg.continueLabel);
		cfg.returnLabel = readFormValue('mp_custom_checkout_settings[step_1][labels][return_label]', cfg.returnLabel);
		cfg.emptyTitle = readFormValue('mp_custom_checkout_settings[step_1][labels][empty_title]', cfg.emptyTitle);
		cfg.emptyMessage = readFormValue('mp_custom_checkout_settings[step_1][empty_state][message]', cfg.emptyMessage);
		cfg.emptyCta = readFormValue('mp_custom_checkout_settings[step_1][empty_state][cta_label]', cfg.emptyCta);
		cfg.cardCompact = Boolean(readFormValue('mp_custom_checkout_settings[step_1][style_controls][card_compact]', cfg.cardCompact));
		cfg.cardEmphasis = readFormValue('mp_custom_checkout_settings[step_1][style_controls][card_emphasis]', cfg.cardEmphasis);
		cfg.summaryEmphasis = readFormValue('mp_custom_checkout_settings[step_1][style_controls][summary_emphasis]', cfg.summaryEmphasis);
		return cfg;
	}

	function readLiveScenarioConfig(baseConfig) {
		var cfg = $.extend(true, {}, baseConfig);
		cfg.defaultScenario = readFormValue('mp_custom_checkout_settings[step_2][default_scenario]', cfg.defaultScenario);
		cfg.cardOrder = String(readFormValue('mp_custom_checkout_settings[step_2][card_order]', cfg.cardOrder.join(','))).split(',').map(function (value) {
			return $.trim(String(value || ''));
		}).filter(Boolean);
		cfg.cards.pickup.title = readFormValue('mp_custom_checkout_settings[step_2][cards][pickup][title]', cfg.cards.pickup.title);
		cfg.cards.pickup.description = readFormValue('mp_custom_checkout_settings[step_2][cards][pickup][description]', cfg.cards.pickup.description);
		cfg.cards.pickup.helper = readFormValue('mp_custom_checkout_settings[step_2][cards][pickup][helper]', cfg.cards.pickup.helper);
		cfg.cards.pickup.iconVariant = readFormValue('mp_custom_checkout_settings[step_2][cards][pickup][icon_variant]', cfg.cards.pickup.iconVariant);
		cfg.cards.pickup.iconStyle = readFormValue('mp_custom_checkout_settings[step_2][cards][pickup][icon_style]', cfg.cards.pickup.iconStyle);
		cfg.cards.delivery.title = readFormValue('mp_custom_checkout_settings[step_2][cards][delivery][title]', cfg.cards.delivery.title);
		cfg.cards.delivery.description = readFormValue('mp_custom_checkout_settings[step_2][cards][delivery][description]', cfg.cards.delivery.description);
		cfg.cards.delivery.helper = readFormValue('mp_custom_checkout_settings[step_2][cards][delivery][helper]', cfg.cards.delivery.helper);
		cfg.cards.delivery.iconVariant = readFormValue('mp_custom_checkout_settings[step_2][cards][delivery][icon_variant]', cfg.cards.delivery.iconVariant);
		cfg.cards.delivery.iconStyle = readFormValue('mp_custom_checkout_settings[step_2][cards][delivery][icon_style]', cfg.cards.delivery.iconStyle);
		cfg.responsive.desktopColumns = Number(readFormValue('mp_custom_checkout_settings[step_2][responsive][desktop_columns]', cfg.responsive.desktopColumns));
		cfg.responsive.tabletColumns = Number(readFormValue('mp_custom_checkout_settings[step_2][responsive][tablet_columns]', cfg.responsive.tabletColumns));
		cfg.responsive.mobileColumns = Number(readFormValue('mp_custom_checkout_settings[step_2][responsive][mobile_columns]', cfg.responsive.mobileColumns));
		cfg.responsive.cardDensity = readFormValue('mp_custom_checkout_settings[step_2][responsive][card_density]', cfg.responsive.cardDensity);
		return cfg;
	}

	function readLiveDateStepConfig(baseConfig) {
		var cfg = $.extend(true, {}, baseConfig);
		cfg.title = readFormValue('mp_custom_checkout_settings[step_3][copy][title]', cfg.title);
		cfg.helperByScenario.pickup = readFormValue('mp_custom_checkout_settings[step_3][copy][helper_by_scenario][pickup]', cfg.helperByScenario.pickup);
		cfg.helperByScenario.krasnoyarsk_delivery = readFormValue('mp_custom_checkout_settings[step_3][copy][helper_by_scenario][krasnoyarsk_delivery]', cfg.helperByScenario.krasnoyarsk_delivery);
		cfg.helperByScenario.other_city_delivery = readFormValue('mp_custom_checkout_settings[step_3][copy][helper_by_scenario][other_city_delivery]', cfg.helperByScenario.other_city_delivery);
		cfg.errors.invalidDate = readFormValue('mp_custom_checkout_settings[step_3][copy][errors][invalid_date]', cfg.errors.invalidDate);
		cfg.errors.emptyDate = readFormValue('mp_custom_checkout_settings[step_3][copy][errors][empty_date]', cfg.errors.emptyDate);
		cfg.minLeadTime.pickup = Number(readFormValue('mp_custom_checkout_settings[step_3][min_lead_time_days][pickup]', cfg.minLeadTime.pickup));
		cfg.minLeadTime.krasnoyarsk_delivery = Number(readFormValue('mp_custom_checkout_settings[step_3][min_lead_time_days][krasnoyarsk_delivery]', cfg.minLeadTime.krasnoyarsk_delivery));
		cfg.minLeadTime.other_city_delivery = Number(readFormValue('mp_custom_checkout_settings[step_3][min_lead_time_days][other_city_delivery]', cfg.minLeadTime.other_city_delivery));
		cfg.calendarStyle.density = readFormValue('mp_custom_checkout_settings[step_3][calendar_style][density]', cfg.calendarStyle.density);
		cfg.calendarStyle.dayShape = readFormValue('mp_custom_checkout_settings[step_3][calendar_style][day_shape]', cfg.calendarStyle.dayShape);
		cfg.calendarStyle.highlightStyle = readFormValue('mp_custom_checkout_settings[step_3][calendar_style][highlight_style]', cfg.calendarStyle.highlightStyle);
		cfg.calendarStyle.showWeekendTint = Boolean(readFormValue('mp_custom_checkout_settings[step_3][calendar_style][show_weekend_tint]', cfg.calendarStyle.showWeekendTint));
		return cfg;
	}

	function readLiveStepFourConfig(base) {
		var cfg = $.extend(true, {}, base);
		var p = 'mp_custom_checkout_settings[step_4][contact_block]';
		cfg.contact.title = readFormValue(p + '[title]', cfg.contact.title);
		cfg.contact.intro = readFormValue(p + '[intro]', cfg.contact.intro);
		cfg.contact.labels.gender = readFormValue(p + '[labels][gender]', cfg.contact.labels.gender || 'Пол');
		cfg.contact.hints.gender = readFormValue(p + '[hints][gender]', cfg.contact.hints.gender || '');
		cfg.contact.fieldVisibility.gender = Boolean(readFormValue(p + '[field_visibility][gender]', cfg.contact.fieldVisibility.gender !== false));
		cfg.contact.fieldRequired.gender = Boolean(readFormValue(p + '[field_required][gender]', cfg.contact.fieldRequired.gender !== false));
		cfg.contact.layout.desktop_columns = Number(readFormValue(p + '[layout][desktop_columns]', cfg.contact.layout.desktop_columns || 3));
		cfg.contact.layout.tablet_columns = Number(readFormValue(p + '[layout][tablet_columns]', cfg.contact.layout.tablet_columns || 2));
		cfg.contact.layout.mobile_columns = Number(readFormValue(p + '[layout][mobile_columns]', cfg.contact.layout.mobile_columns || 1));
		cfg.contact.labels.birthdate = readFormValue(p + '[labels][birthdate]', cfg.contact.labels.birthdate || 'Дата рождения');
		cfg.contact.placeholders.birthdate = readFormValue(p + '[placeholders][birthdate]', cfg.contact.placeholders.birthdate || '');
		cfg.contact.hints.birthdate = readFormValue(p + '[hints][birthdate]', cfg.contact.hints.birthdate || '');
		cfg.contact.fieldVisibility.birthdate = Boolean(readFormValue(p + '[field_visibility][birthdate]', cfg.contact.fieldVisibility.birthdate !== false));
		cfg.contact.fieldRequired.birthdate = Boolean(readFormValue(p + '[field_required][birthdate]', cfg.contact.fieldRequired.birthdate !== false));
		cfg.contact.labels.order_notes = readFormValue(p + '[labels][order_notes]', cfg.contact.labels.order_notes || 'Примечания к заказу');
		cfg.contact.placeholders.order_notes = readFormValue(p + '[placeholders][order_notes]', cfg.contact.placeholders.order_notes || '');
		cfg.contact.hints.order_notes = readFormValue(p + '[hints][order_notes]', cfg.contact.hints.order_notes || '');
		cfg.contact.fieldVisibility.order_notes = Boolean(readFormValue(p + '[field_visibility][order_notes]', cfg.contact.fieldVisibility.order_notes !== false));
		cfg.contact.fieldRequired.order_notes = Boolean(readFormValue(p + '[field_required][order_notes]', cfg.contact.fieldRequired.order_notes !== false));
		cfg.contact.validationMessages.required = readFormValue(p + '[validation_messages][required]', cfg.contact.validationMessages.required || 'Заполните это поле.');
		cfg.contact.validationMessages.email_invalid = readFormValue(p + '[validation_messages][email_invalid]', cfg.contact.validationMessages.email_invalid || 'Введите корректный email.');
		cfg.contact.validationMessages.phone_required = readFormValue(p + '[validation_messages][phone_required]', cfg.contact.validationMessages.phone_required || 'Укажите номер телефона.');
		cfg.contact.validationMessages.phone_format = readFormValue(p + '[validation_messages][phone_format]', cfg.contact.validationMessages.phone_format || 'Введите номер полностью.');
		cfg.contact.validationMessages.address_required = readFormValue(p + '[validation_messages][address_required]', cfg.contact.validationMessages.address_required || 'Заполните это поле.');
		cfg.contact.validationMessages.address_region = readFormValue(p + '[validation_messages][address_region]', cfg.contact.validationMessages.address_region || 'Выберите корректный регион.');
		cfg.contact.validationMessages.address_city = readFormValue(p + '[validation_messages][address_city]', cfg.contact.validationMessages.address_city || 'Выберите населённый пункт из списка.');
		cfg.contact.validationMessages.address_postcode = readFormValue(p + '[validation_messages][address_postcode]', cfg.contact.validationMessages.address_postcode || 'Слишком длинный индекс.');
		cfg.contact.validationMessages.step_blocked = readFormValue(p + '[validation_messages][step_blocked]', cfg.contact.validationMessages.step_blocked || 'Заполните обязательные поля текущего шага.');
		cfg.contact.validationMessages.conditions_required = readFormValue(p + '[validation_messages][conditions_required]', cfg.contact.validationMessages.conditions_required || 'Подтвердите ознакомление с условиями, чтобы продолжить.');
		cfg.contact.constraints.birthdate_min_age = Number(readFormValue(p + '[validation_constraints][birthdate_min_age]', cfg.contact.constraints.birthdate_min_age || 0));
		cfg.contact.constraints.birthdate_max_age = Number(readFormValue(p + '[validation_constraints][birthdate_max_age]', cfg.contact.constraints.birthdate_max_age || 120));
		cfg.contact.constraints.phone_digits_override = Number(readFormValue(p + '[validation_constraints][phone_digits_override]', cfg.contact.constraints.phone_digits_override || 0));
		cfg.contact.ajaxMessages.draft_save_failed = readFormValue(p + '[ajax_messages][draft_save_failed]', cfg.contact.ajaxMessages.draft_save_failed || 'Не удалось сохранить данные.');
		cfg.contact.ajaxMessages.step_sync_failed = readFormValue(p + '[ajax_messages][step_sync_failed]', cfg.contact.ajaxMessages.step_sync_failed || 'Не удалось синхронизировать шаг. Обновите страницу.');
		cfg.contact.ajaxMessages.scenario_sync_failed = readFormValue(p + '[ajax_messages][scenario_sync_failed]', cfg.contact.ajaxMessages.scenario_sync_failed || 'Не удалось сохранить выбор сценария.');
		cfg.contact.states.invalid_style = readFormValue(p + '[field_state_styles][invalid_style]', cfg.contact.states.invalid_style || 'default');
		cfg.contact.states.hint_style = readFormValue(p + '[field_state_styles][hint_style]', cfg.contact.states.hint_style || 'default');
		cfg.contact.states.focus_style = readFormValue(p + '[field_state_styles][focus_style]', cfg.contact.states.focus_style || 'default');
		cfg.contact.states.disabled_style = readFormValue(p + '[field_state_styles][disabled_style]', cfg.contact.states.disabled_style || 'default');
		var d = 'mp_custom_checkout_settings[step_4][discount_layout]';
		cfg.discountLayout.placement = readFormValue(d + '[placement]', cfg.discountLayout.placement || 'step_4');
		cfg.discountLayout.separate_step_enabled = Boolean(readFormValue(d + '[separate_step_enabled]', cfg.discountLayout.separate_step_enabled));
		var ds = 'mp_custom_checkout_settings[step_4][discount_block_styles]';
		cfg.discountStyles.state_empty = readFormValue(ds + '[state_empty]', cfg.discountStyles.state_empty || 'default');
		cfg.discountStyles.state_success = readFormValue(ds + '[state_success]', cfg.discountStyles.state_success || 'success');
		cfg.discountStyles.state_error = readFormValue(ds + '[state_error]', cfg.discountStyles.state_error || 'error');
		cfg.discountStyles.focus_style = readFormValue(ds + '[focus_style]', cfg.discountStyles.focus_style || 'default');
		var py = 'mp_custom_checkout_settings[step_4][payment_block]';
		cfg.payment.title = readFormValue(py + '[title]', cfg.payment.title || 'Способ оплаты');
		cfg.payment.intro = readFormValue(py + '[intro]', cfg.payment.intro || 'Выберите удобный способ оплаты.');
		cfg.payment.card_surface = readFormValue(py + '[card_surface]', cfg.payment.card_surface || 'visual');
		cfg.payment.auto_classic_on_empty_gateway_fields = Boolean(readFormValue(py + '[auto_classic_on_empty_gateway_fields]', cfg.payment.auto_classic_on_empty_gateway_fields !== false));
		cfg.payment.decorative_card_fields = Boolean(readFormValue(py + '[decorative_card_fields]', cfg.payment.decorative_card_fields !== false));
		cfg.payment.card_style = readFormValue(py + '[card_style]', cfg.payment.card_style || 'default');
		cfg.payment.show_description = Boolean(readFormValue(py + '[show_description]', cfg.payment.show_description !== false));
		cfg.payment.required = Boolean(readFormValue(py + '[required]', cfg.payment.required !== false));
		cfg.payment.error_message = readFormValue(py + '[error_message]', cfg.payment.error_message || 'Выберите способ оплаты.');
		cfg.payment.layout = cfg.payment.layout && typeof cfg.payment.layout === 'object' ? cfg.payment.layout : {};
		cfg.payment.layout.desktop_columns = Number(readFormValue(py + '[layout][desktop_columns]', cfg.payment.layout.desktop_columns || 2));
		cfg.payment.layout.tablet_columns = Number(readFormValue(py + '[layout][tablet_columns]', cfg.payment.layout.tablet_columns || 2));
		cfg.payment.layout.mobile_columns = Number(readFormValue(py + '[layout][mobile_columns]', cfg.payment.layout.mobile_columns || 1));
		cfg.payment.layout.grid_gap = readFormValue(py + '[layout][grid_gap]', cfg.payment.layout.grid_gap || '0.6rem 0.75rem');
		cfg.payment.gateway_order = String(readFormValue(py + '[gateway_order]', (cfg.payment.gateway_order || []).join(','))).split(',').map(function (value) {
			return $.trim(String(value || ''));
		}).filter(Boolean);
		cfg.payment.card_active_style = readFormValue(py + '[card_active_style]', cfg.payment.card_active_style || 'accent');
		cfg.payment.radio_style = readFormValue(py + '[radio_style]', cfg.payment.radio_style || 'default');
		cfg.payment.description_style = readFormValue(py + '[description_style]', cfg.payment.description_style || 'muted');
		cfg.payment.messages = cfg.payment.messages && typeof cfg.payment.messages === 'object' ? cfg.payment.messages : {};
		cfg.payment.messages.loading = readFormValue(py + '[messages][loading]', cfg.payment.messages.loading || 'Сохраняем выбранный способ оплаты...');
		cfg.payment.messages.success = readFormValue(py + '[messages][success]', cfg.payment.messages.success || 'Способ оплаты обновлён.');
		cfg.payment.messages.error = readFormValue(py + '[messages][error]', cfg.payment.messages.error || 'Не удалось переключить способ оплаты.');
		cfg.payment.diagnostics = cfg.payment.diagnostics && typeof cfg.payment.diagnostics === 'object' ? cfg.payment.diagnostics : {};
		cfg.payment.diagnostics.enabled = Boolean(readFormValue(py + '[diagnostics][enabled]', cfg.payment.diagnostics.enabled !== false));
		var mrPath = py + '[summary_mini_review]';
		cfg.payment.summary_mini_review = cfg.payment.summary_mini_review && typeof cfg.payment.summary_mini_review === 'object' ? cfg.payment.summary_mini_review : {};
		cfg.payment.summary_mini_review.enabled = Boolean(readFormValue(mrPath + '[enabled]', cfg.payment.summary_mini_review.enabled !== false));
		cfg.payment.summary_mini_review.title = readFormValue(mrPath + '[title]', cfg.payment.summary_mini_review.title || '');
		cfg.payment.summary_mini_review.intro = readFormValue(mrPath + '[intro]', cfg.payment.summary_mini_review.intro || '');
		cfg.payment.summary_mini_review.method_label = readFormValue(mrPath + '[method_label]', cfg.payment.summary_mini_review.method_label || '');
		cfg.payment.summary_mini_review.id_label = readFormValue(mrPath + '[id_label]', cfg.payment.summary_mini_review.id_label || '');
		cfg.payment.summary_mini_review.state_loading = readFormValue(mrPath + '[state_loading]', cfg.payment.summary_mini_review.state_loading || '');
		cfg.payment.summary_mini_review.state_success = readFormValue(mrPath + '[state_success]', cfg.payment.summary_mini_review.state_success || '');
		cfg.payment.summary_mini_review.state_error = readFormValue(mrPath + '[state_error]', cfg.payment.summary_mini_review.state_error || '');
		cfg.payment.summary_mini_review.show_gateway_id = Boolean(readFormValue(mrPath + '[show_gateway_id]', cfg.payment.summary_mini_review.show_gateway_id === true));
		cfg.payment.summary_mini_review.show_gateway_description = Boolean(readFormValue(mrPath + '[show_gateway_description]', cfg.payment.summary_mini_review.show_gateway_description !== false));
		var cp = 'mp_custom_checkout_settings[step_4][coupon_block]';
		cfg.coupon.title = readFormValue(cp + '[title]', cfg.coupon.title || 'Подарочная карта');
		cfg.coupon.intro = readFormValue(cp + '[intro]', cfg.coupon.intro || '');
		cfg.coupon.input_label = readFormValue(cp + '[input_label]', cfg.coupon.input_label || 'Код купона');
		cfg.coupon.placeholder = readFormValue(cp + '[placeholder]', cfg.coupon.placeholder || '');
		cfg.coupon.apply_label = readFormValue(cp + '[apply_label]', cfg.coupon.apply_label || 'Применить');
		cfg.coupon.empty_message = readFormValue(cp + '[empty_message]', cfg.coupon.empty_message || '');
		cfg.coupon.success_message = readFormValue(cp + '[success_message]', cfg.coupon.success_message || '');
		cfg.coupon.error_message = readFormValue(cp + '[error_message]', cfg.coupon.error_message || '');
		cfg.coupon.allow_remove_applied = Boolean(readFormValue(cp + '[allow_remove_applied]', cfg.coupon.allow_remove_applied !== false));
		cfg.coupon.summary_section_title = readFormValue(cp + '[summary_section_title]', cfg.coupon.summary_section_title || '');
		var gc = 'mp_custom_checkout_settings[step_4][gift_card_block]';
		cfg.giftCard.title = readFormValue(gc + '[title]', cfg.giftCard.title || 'Подарочная карта');
		cfg.giftCard.intro = readFormValue(gc + '[intro]', cfg.giftCard.intro || '');
		cfg.giftCard.input_label = readFormValue(gc + '[input_label]', cfg.giftCard.input_label || 'Код подарочной карты');
		cfg.giftCard.placeholder = readFormValue(gc + '[placeholder]', cfg.giftCard.placeholder || '');
		cfg.giftCard.apply_label = readFormValue(gc + '[apply_label]', cfg.giftCard.apply_label || 'Применить');
		cfg.giftCard.empty_message = readFormValue(gc + '[empty_message]', cfg.giftCard.empty_message || '');
		cfg.giftCard.success_message = readFormValue(gc + '[success_message]', cfg.giftCard.success_message || '');
		cfg.giftCard.error_message = readFormValue(gc + '[error_message]', cfg.giftCard.error_message || '');
		cfg.giftCard.peer_next_to_payment = Boolean(readFormValue(gc + '[peer_next_to_payment]', cfg.giftCard.peer_next_to_payment !== false));
		cfg.giftCard.allow_remove_applied = Boolean(readFormValue(gc + '[allow_remove_applied]', cfg.giftCard.allow_remove_applied !== false));
		cfg.giftCard.card_title = readFormValue(gc + '[card_title]', cfg.giftCard.card_title || '');
		cfg.giftCard.card_subtitle = readFormValue(gc + '[card_subtitle]', cfg.giftCard.card_subtitle || '');
		cfg.giftCard.unavailable_message = readFormValue(gc + '[unavailable_message]', cfg.giftCard.unavailable_message || '');
		return cfg;
	}

	function readLiveDeliveryConfig(base) {
		var cfg = $.extend(true, {}, base);
		var p = 'mp_custom_checkout_settings[delivery][shipping_catalog]';
		var methods = cfg.shippingCatalog.methods && typeof cfg.shippingCatalog.methods === 'object' ? cfg.shippingCatalog.methods : {};
		var methodIds = Object.keys(methods);
		for (var i = 0; i < methodIds.length; i += 1) {
			var id = methodIds[i];
			var mPath = p + '[methods][' + id + ']';
			methods[id].title = readFormValue(mPath + '[title]', methods[id].title);
			methods[id].price = Number(readFormValue(mPath + '[price]', methods[id].price));
			methods[id].eta = readFormValue(mPath + '[eta]', methods[id].eta);
			methods[id].description = readFormValue(mPath + '[description]', methods[id].description);
			methods[id].active = Boolean(readFormValue(mPath + '[active]', methods[id].active));
			methods[id].requires_address = Boolean(readFormValue(mPath + '[requires_address]', methods[id].requires_address));
			var visFallback = Array.isArray(methods[id].visibility_scenarios) ? methods[id].visibility_scenarios.join(',') : '';
			methods[id].visibility_scenarios = String(readFormValue(mPath + '[visibility_scenarios]', visFallback)).split(',').map(function (s) {
				return String(s || '').trim();
			}).filter(Boolean);
			if (methods[id].tariffs && typeof methods[id].tariffs === 'object') {
				var tIds = Object.keys(methods[id].tariffs);
				var ti;
				for (ti = 0; ti < tIds.length; ti += 1) {
					var tid = tIds[ti];
					var tPath = mPath + '[tariffs][' + tid + ']';
					var tBase = methods[id].tariffs[tid] && typeof methods[id].tariffs[tid] === 'object' ? methods[id].tariffs[tid] : {};
					methods[id].tariffs[tid] = {
						title: readFormValue(tPath + '[title]', tBase.title),
						price: Number(readFormValue(tPath + '[price]', tBase.price)),
						eta: readFormValue(tPath + '[eta]', tBase.eta),
						active: Boolean(readFormValue(tPath + '[active]', tBase.active))
					};
				}
			}
		}
		cfg.shippingCatalog.methods = methods;
		cfg.shippingCatalog.sortOrder = String(readFormValue(p + '[sort_order]', cfg.shippingCatalog.sortOrder.join(','))).split(',').map(function (s) {
			return String(s || '').trim();
		}).filter(Boolean);
		cfg.shippingCatalog.bulkUpdate.seasonal_delta_pct = Number(readFormValue(p + '[bulk_update][seasonal_delta_pct]', cfg.shippingCatalog.bulkUpdate.seasonal_delta_pct));
		cfg.shippingCatalog.bulkUpdate.seasonal_delta_abs = Number(readFormValue(p + '[bulk_update][seasonal_delta_abs]', cfg.shippingCatalog.bulkUpdate.seasonal_delta_abs));
		cfg.shippingCatalog.bulkUpdate.eta_suffix = readFormValue(p + '[bulk_update][eta_suffix]', cfg.shippingCatalog.bulkUpdate.eta_suffix);
		cfg.shippingCatalog.preview.mock_subtotal = Number(readFormValue(p + '[preview][mock_subtotal]', cfg.shippingCatalog.preview.mock_subtotal));
		cfg.shippingCatalog.preview.mock_discount = Number(readFormValue(p + '[preview][mock_discount]', cfg.shippingCatalog.preview.mock_discount));
		cfg.shippingCatalog.preview.mock_tax = Number(readFormValue(p + '[preview][mock_tax]', cfg.shippingCatalog.preview.mock_tax));
		cfg.shippingCatalog.errorCopy.method_unavailable = readFormValue(p + '[error_copy][method_unavailable]', cfg.shippingCatalog.errorCopy.method_unavailable || '');
		cfg.shippingCatalog.errorCopy.tariff_unavailable = readFormValue(p + '[error_copy][tariff_unavailable]', cfg.shippingCatalog.errorCopy.tariff_unavailable || '');
		return cfg;
	}

	function flattenStepOneDefaults(defaults, node, trail) {
		var current = node && typeof node === 'object' ? node : {};
		var path = Array.isArray(trail) ? trail : [];
		var key;
		for (key in current) {
			if (!Object.prototype.hasOwnProperty.call(current, key)) {
				continue;
			}
			var nextPath = path.concat([key]);
			var value = current[key];
			if (value && typeof value === 'object' && !Array.isArray(value)) {
				flattenStepOneDefaults(defaults, value, nextPath);
				continue;
			}
			var name = 'mp_custom_checkout_settings[step_1]';
			for (var i = 0; i < nextPath.length; i += 1) {
				name += '[' + nextPath[i] + ']';
			}
			defaults[name] = value;
		}
	}

	function applyDefaultsToForm(defaultsMap) {
		var name;
		for (name in defaultsMap) {
			if (!Object.prototype.hasOwnProperty.call(defaultsMap, name)) {
				continue;
			}
			var value = defaultsMap[name];
			var $field = $('[name="' + name + '"]');
			if (!$field.length) {
				continue;
			}
			if ($field.is(':checkbox')) {
				$field.prop('checked', Boolean(value)).trigger('change');
				continue;
			}
			$field.val(String(value)).trigger('input').trigger('change');
		}
	}

	function flattenDefaultsForSection(defaults, node, section, trail) {
		var current = node && typeof node === 'object' ? node : {};
		var path = Array.isArray(trail) ? trail : [];
		var key;
		for (key in current) {
			if (!Object.prototype.hasOwnProperty.call(current, key)) {
				continue;
			}
			var nextPath = path.concat([key]);
			var value = current[key];
			if (value && typeof value === 'object' && !Array.isArray(value)) {
				flattenDefaultsForSection(defaults, value, section, nextPath);
				continue;
			}
			var name = 'mp_custom_checkout_settings[' + section + ']';
			for (var i = 0; i < nextPath.length; i += 1) {
				name += '[' + nextPath[i] + ']';
			}
			defaults[name] = value;
		}
	}

	function detectActiveSettingsSection() {
		var $active = $('.mp-cc-admin-shell__tab.is-active').first();
		if ($active.length) {
			var href = String($active.attr('href') || '');
			var match = href.match(/[?&]tab=([^&]+)/);
			if (match && match[1]) {
				return String(match[1]);
			}
		}
		var search = window.location && window.location.search ? String(window.location.search) : '';
		var fromSearch = search.match(/[?&]tab=([^&]+)/);
		if (fromSearch && fromSearch[1]) {
			return String(fromSearch[1]);
		}
		var $firstField = $('[name^="mp_custom_checkout_settings["]').first();
		if (!$firstField.length) {
			return '';
		}
		var name = String($firstField.attr('name') || '');
		var fallback = name.match(/^mp_custom_checkout_settings\[([^\]]+)\]/);
		return fallback && fallback[1] ? String(fallback[1]) : '';
	}

	function enhanceStepOneFields() {
		var $rows = $('input[name^="mp_custom_checkout_settings[step_1]"], select[name^="mp_custom_checkout_settings[step_1]"], textarea[name^="mp_custom_checkout_settings[step_1]"]')
			.closest('tr');
		if (!$rows.length) {
			return;
		}
		$rows.addClass('mp-cc-admin-step1-row');
		var hints = {
			'labels][title': 'Main heading of Step 1.',
			'labels][summary_title': 'Summary card title in sidebar.',
			'labels][subtotal_label': 'Label before subtotal amount.',
			'labels][continue_label': 'Primary CTA in summary.',
			'empty_state][message': 'Extra message shown when cart is empty.',
			'layout_order][secondary_order': 'Controls order of secondary item blocks.',
			'responsive][mobile_mode': 'Step 1 density on small screens.'
		};
		$rows.each(function () {
			var $row = $(this);
			var $input = $row.find('input[name^="mp_custom_checkout_settings[step_1]"], select[name^="mp_custom_checkout_settings[step_1]"], textarea[name^="mp_custom_checkout_settings[step_1]"]').first();
			if (!$input.length) {
				return;
			}
			var name = String($input.attr('name') || '');
			var hintText = '';
			var key;
			for (key in hints) {
				if (Object.prototype.hasOwnProperty.call(hints, key) && name.indexOf(key) > -1) {
					hintText = hints[key];
					break;
				}
			}
			if (hintText && !$row.find('.mp-cc-admin-step1-hint').length) {
				$row.find('td').append('<p class="mp-cc-admin-step1-hint">' + escapeHtml(hintText) + '</p>');
			}
		});
	}

	function enhanceStepThreeFields() {
		var $rows = $('input[name^="mp_custom_checkout_settings[step_3]"], select[name^="mp_custom_checkout_settings[step_3]"], textarea[name^="mp_custom_checkout_settings[step_3]"]')
			.closest('tr');
		if (!$rows.length) {
			return;
		}
		$rows.addClass('mp-cc-admin-step3-row');
		var hints = {
			'copy][title': 'Общий заголовок блока выбора даты.',
			'copy][helper_by_scenario][pickup': 'Подсказка для сценария самовывоза.',
			'copy][helper_by_scenario][krasnoyarsk_delivery': 'Подсказка для доставки по Красноярску.',
			'copy][helper_by_scenario][other_city_delivery': 'Подсказка для доставки в другой город.',
			'copy][errors][invalid_date': 'Текст ошибки для недоступной даты.',
			'copy][errors][empty_date': 'Текст ошибки, если дата не выбрана.',
			'holiday_dates': 'Список праздничных дат YYYY-MM-DD (массив).',
			'closed_dates': 'Список вручную закрытых дат YYYY-MM-DD (массив).',
			'conditions_copy][pickup][office_block_title': 'Заголовок блока «Офис и график» на шаге условий (самовывоз).',
			'conditions_copy][pickup][office_address': 'Адрес офиса (если пусто — подставляется из точки самовывоза).',
			'conditions_copy][pickup][office_description': 'Описание выдачи в офисе.',
			'conditions_copy][pickup][office_hours_plain': 'Текстовое расписание (строки); если заполнено, чипы по слотам скрываются.',
			'conditions_copy][pickup][convenience_helper': 'Подсказка о визите в удобное время в рабочие часы.',
			'conditions_copy][pickup][critical_notice': 'Важное предупреждение (выделяется в блоке офиса).',
			'conditions_copy][pickup][show_multi_office_slot': 'Показывать слот для будущих дополнительных точек самовывоза.'
		};
		$rows.each(function () {
			var $row = $(this);
			var $input = $row.find('input[name^="mp_custom_checkout_settings[step_3]"], select[name^="mp_custom_checkout_settings[step_3]"], textarea[name^="mp_custom_checkout_settings[step_3]"]').first();
			if (!$input.length) {
				return;
			}
			var name = String($input.attr('name') || '');
			var hintText = '';
			var key;
			for (key in hints) {
				if (Object.prototype.hasOwnProperty.call(hints, key) && name.indexOf(key) > -1) {
					hintText = hints[key];
					break;
				}
			}
			if (hintText && !$row.find('.mp-cc-admin-step3-hint').length) {
				$row.find('td').append('<p class="mp-cc-admin-step3-hint">' + escapeHtml(hintText) + '</p>');
			}
		});
	}

	function enhanceStepFourFields() {
		var $rows = $('input[name^="mp_custom_checkout_settings[step_4]"], select[name^="mp_custom_checkout_settings[step_4]"], textarea[name^="mp_custom_checkout_settings[step_4]"]').closest('tr');
		if (!$rows.length) {
			return;
		}
		$rows.addClass('mp-cc-admin-step4-row');
	}

	function enhanceDeliveryFields() {
		var $rows = $('input[name^="mp_custom_checkout_settings[delivery]"], select[name^="mp_custom_checkout_settings[delivery]"], textarea[name^="mp_custom_checkout_settings[delivery]"]').closest('tr');
		if (!$rows.length) {
			return;
		}
		$rows.addClass('mp-cc-admin-delivery-row');
	}

	function refreshStepThreeEmptyIndicators() {
		var selectors = [
			'input[name="mp_custom_checkout_settings[step_3][copy][title]"]',
			'input[name="mp_custom_checkout_settings[step_3][copy][helper_by_scenario][pickup]"]',
			'input[name="mp_custom_checkout_settings[step_3][copy][helper_by_scenario][krasnoyarsk_delivery]"]',
			'input[name="mp_custom_checkout_settings[step_3][copy][helper_by_scenario][other_city_delivery]"]',
			'input[name="mp_custom_checkout_settings[step_3][copy][errors][invalid_date]"]',
			'input[name="mp_custom_checkout_settings[step_3][copy][errors][empty_date]"]'
		];
		for (var i = 0; i < selectors.length; i += 1) {
			var $field = $(selectors[i]).first();
			if (!$field.length) {
				continue;
			}
			var value = String($field.val() || '').trim();
			var $row = $field.closest('tr');
			var isEmpty = value.length === 0;
			$row.toggleClass('mp-cc-admin-step3-row--empty', isEmpty);
			if (isEmpty) {
				if (!$row.find('.mp-cc-admin-step3-warning').length) {
					$row.find('td').append('<p class="mp-cc-admin-step3-warning">Поле пустое — будет использован fallback.</p>');
				}
			} else {
				$row.find('.mp-cc-admin-step3-warning').remove();
			}
		}
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
		var config = getConfigFromRuntime();
		var scenarioConfig = getScenarioConfigFromRuntime();
		var dateStepConfig = getDateStepConfigFromRuntime();
		var officeHoursPreviewConfig = getOfficeHoursPreviewConfigFromRuntime();
		var stepFourConfig = getStepFourConfigFromRuntime();
		var deliveryConfig = getDeliveryConfigFromRuntime();
		var defaults = getDefaults();
		var settingsDefaults = getSettingsDefaults();
		var previewStore = createPreviewStore({
			runtime: createPreviewRuntimeMock(),
			activeStep: 'step_1',
			progressStyle: 'digits',
			scenario: 'pickup',
			device: 'desktop',
			interactionState: 'default',
			runtimeState: 'default',
			dirty: false
		});
		var flags = window.mpCcAdmin && window.mpCcAdmin.featureFlags ? window.mpCcAdmin.featureFlags : {};
		if (!config.previewEnabled || flags.admin_live_preview === false) {
			return;
		}
		var $wrap = $('.wrap').first();
		if (!$wrap.length) {
			return;
		}
		if ($('#mp-cc-admin-preview-area').length) {
			return;
		}
		$wrap.append(renderPreviewArea());
		enhanceStepOneFields();
		enhanceStepThreeFields();
		enhanceStepFourFields();
		refreshStepThreeEmptyIndicators();

		var mountPreviews = function (nextConfig, nextScenarioConfig, nextDateConfig, nextOfficeConfig, nextStepFourConfig, nextDeliveryConfig) {
			var previewState = previewStore.getState();
			var activeStep = previewState.activeStep || 'step_1';
			var progressStyle = previewState.progressStyle || 'digits';
			var html = '';
			if (activeStep === 'step_1') {
				html += renderPreview(nextConfig, previewState);
			} else if (activeStep === 'step_2' && nextDateConfig.previewEnabled) {
				html += renderDatePreview(nextDateConfig, previewState);
				html += renderDeliveryConfigPreview(nextDeliveryConfig);
			} else if (activeStep === 'step_3' && nextOfficeConfig.previewEnabled) {
				html += renderOfficeHoursPreview(nextOfficeConfig);
				if (nextScenarioConfig.previewEnabled) {
					html += renderScenarioPreview(nextScenarioConfig, previewState);
				}
			} else if (activeStep === 'step_4' && nextStepFourConfig.previewEnabled) {
				html += renderStepFourPreview(nextStepFourConfig, previewState);
			} else if (activeStep === 'success') {
				html += renderSuccessPreview(previewState.runtime);
			} else {
				html += renderPreview(nextConfig, previewState);
			}
			var flowNavHtml = renderFlowStepSwitcher(activeStep);
			var progressHtml = renderFlowProgress(activeStep, progressStyle);
			var signature = [
				activeStep,
				progressStyle,
				String(previewState.device || 'desktop'),
				String(previewState.interactionState || 'default'),
				String(previewState.runtimeState || 'default'),
				html
			].join('|');
			var lastSignature = String(previewStore.getState().lastRenderSignature || '');
			if (signature === lastSignature) {
				return;
			}
			previewStore.setState({ lastRenderSignature: signature });
			$('[data-mp-cc-preview-body="1"]').html(html);
			$('[data-mp-cc-preview-body="1"]').attr('data-device', String(previewState.device || 'desktop'));
			$('[data-mp-cc-preview-body="1"]').attr('data-interaction', String(previewState.interactionState || 'default'));
			$('[data-mp-cc-preview-body="1"]').attr('data-runtime', String(previewState.runtimeState || 'default'));
			$('[data-mp-cc-preview-flow-nav="1"]').html(flowNavHtml);
			$('[data-mp-cc-preview-progress="1"]').html(progressHtml);
		};

		var updatePreviewWarning = function () {
			var isDirty = Boolean(previewStore.getState().dirty);
			$('[data-mp-cc-preview-warning="1"]').prop('hidden', !isDirty);
		};

		var rerender = function () {
			var nextConfig = readLiveConfig(config);
			var nextScenarioConfig = readLiveScenarioConfig(scenarioConfig);
			var nextDateConfig = readLiveDateStepConfig(dateStepConfig);
			var nextOfficeCfg = readLiveOfficeHoursPreviewConfig(officeHoursPreviewConfig);
			var nextStepFourCfg = readLiveStepFourConfig(stepFourConfig);
			var nextDeliveryCfg = readLiveDeliveryConfig(deliveryConfig);
			mountPreviews(nextConfig, nextScenarioConfig, nextDateConfig, nextOfficeCfg, nextStepFourCfg, nextDeliveryCfg);
			previewStore.setState({ dirty: true });
			updatePreviewWarning();
		};
		var rerenderDebounced = debounce(rerender, 120);
		mountPreviews(config, scenarioConfig, dateStepConfig, officeHoursPreviewConfig, stepFourConfig, deliveryConfig);
		$('[data-mp-cc-progress-style-select="1"]').val('digits');
		$('[data-mp-cc-scenario-select="1"]').val('pickup');
		$('[data-mp-cc-device-select="1"]').val('desktop');
		$('[data-mp-cc-interaction-select="1"]').val('default');
		$('[data-mp-cc-runtime-select="1"]').val('default');
		$('[data-mp-cc-sandbox-select="1"]').val('pickup_happy_path');
		updatePreviewWarning();

		$(document).on('input change', '[name^="mp_custom_checkout_settings[step_1]"]', function () {
			rerenderDebounced();
		});
		$(document).on('input change', '[name^="mp_custom_checkout_settings[step_2]"]', function () {
			rerenderDebounced();
		});
		$(document).on('input change', '[name^="mp_custom_checkout_settings[step_3]"]', function () {
			refreshStepThreeEmptyIndicators();
			rerenderDebounced();
		});
		$(document).on('input change', '[name^="mp_custom_checkout_settings[step_4]"]', function () {
			rerenderDebounced();
		});
		$(document).on('input change', '[name^="mp_custom_checkout_settings[delivery]"]', function () {
			rerenderDebounced();
		});
		$(document).on('click', '[data-mp-cc-preview-reset]', function () {
			previewStore.reset();
			mountPreviews(config, scenarioConfig, dateStepConfig, officeHoursPreviewConfig, stepFourConfig, deliveryConfig);
			updatePreviewWarning();
		});
		$(document).on('click', '[data-mp-cc-preview-step]', function () {
			var stepId = String($(this).data('mpCcPreviewStep') || '');
			if (!stepId) {
				return;
			}
			previewStore.setState({ activeStep: stepId, dirty: true });
			mountPreviews(readLiveConfig(config), readLiveScenarioConfig(scenarioConfig), readLiveDateStepConfig(dateStepConfig), readLiveOfficeHoursPreviewConfig(officeHoursPreviewConfig), readLiveStepFourConfig(stepFourConfig), readLiveDeliveryConfig(deliveryConfig));
			updatePreviewWarning();
		});
		$(document).on('click', '[data-mp-cc-progress-style]', function () {
			var style = String($(this).data('mpCcProgressStyle') || '');
			if (!style) {
				return;
			}
			previewStore.setState({ progressStyle: style, dirty: true });
			mountPreviews(readLiveConfig(config), readLiveScenarioConfig(scenarioConfig), readLiveDateStepConfig(dateStepConfig), readLiveOfficeHoursPreviewConfig(officeHoursPreviewConfig), readLiveStepFourConfig(stepFourConfig), readLiveDeliveryConfig(deliveryConfig));
			updatePreviewWarning();
		});
		$(document).on('change', '[data-mp-cc-progress-style-select]', function () {
			var styleSelect = String($(this).val() || '');
			if (!styleSelect) {
				return;
			}
			previewStore.setState({ progressStyle: styleSelect, dirty: true });
			mountPreviews(readLiveConfig(config), readLiveScenarioConfig(scenarioConfig), readLiveDateStepConfig(dateStepConfig), readLiveOfficeHoursPreviewConfig(officeHoursPreviewConfig), readLiveStepFourConfig(stepFourConfig), readLiveDeliveryConfig(deliveryConfig));
			updatePreviewWarning();
		});
		$(document).on('click', '[data-mp-cc-scenario]', function () {
			var scenario = String($(this).data('mpCcScenario') || '');
			if (!scenario) {
				return;
			}
			previewStore.setState({ scenario: scenario, dirty: true });
			mountPreviews(readLiveConfig(config), readLiveScenarioConfig(scenarioConfig), readLiveDateStepConfig(dateStepConfig), readLiveOfficeHoursPreviewConfig(officeHoursPreviewConfig), readLiveStepFourConfig(stepFourConfig), readLiveDeliveryConfig(deliveryConfig));
			updatePreviewWarning();
		});
		$(document).on('change', '[data-mp-cc-scenario-select]', function () {
			var scenarioSelect = String($(this).val() || '');
			if (!scenarioSelect) {
				return;
			}
			previewStore.setState({ scenario: scenarioSelect, dirty: true });
			mountPreviews(readLiveConfig(config), readLiveScenarioConfig(scenarioConfig), readLiveDateStepConfig(dateStepConfig), readLiveOfficeHoursPreviewConfig(officeHoursPreviewConfig), readLiveStepFourConfig(stepFourConfig), readLiveDeliveryConfig(deliveryConfig));
			updatePreviewWarning();
		});
		$(document).on('click', '[data-mp-cc-device]', function () {
			var device = String($(this).data('mpCcDevice') || '');
			if (!device) {
				return;
			}
			previewStore.setState({ device: device, dirty: true });
			mountPreviews(readLiveConfig(config), readLiveScenarioConfig(scenarioConfig), readLiveDateStepConfig(dateStepConfig), readLiveOfficeHoursPreviewConfig(officeHoursPreviewConfig), readLiveStepFourConfig(stepFourConfig), readLiveDeliveryConfig(deliveryConfig));
			updatePreviewWarning();
		});
		$(document).on('change', '[data-mp-cc-device-select]', function () {
			var deviceSelect = String($(this).val() || '');
			if (!deviceSelect) {
				return;
			}
			previewStore.setState({ device: deviceSelect, dirty: true });
			mountPreviews(readLiveConfig(config), readLiveScenarioConfig(scenarioConfig), readLiveDateStepConfig(dateStepConfig), readLiveOfficeHoursPreviewConfig(officeHoursPreviewConfig), readLiveStepFourConfig(stepFourConfig), readLiveDeliveryConfig(deliveryConfig));
			updatePreviewWarning();
		});
		$(document).on('click', '[data-mp-cc-interaction]', function () {
			var interaction = String($(this).data('mpCcInteraction') || '');
			if (!interaction) {
				return;
			}
			previewStore.setState({ interactionState: interaction, dirty: true });
			mountPreviews(readLiveConfig(config), readLiveScenarioConfig(scenarioConfig), readLiveDateStepConfig(dateStepConfig), readLiveOfficeHoursPreviewConfig(officeHoursPreviewConfig), readLiveStepFourConfig(stepFourConfig), readLiveDeliveryConfig(deliveryConfig));
			updatePreviewWarning();
		});
		$(document).on('change', '[data-mp-cc-interaction-select]', function () {
			var interactionSelect = String($(this).val() || '');
			if (!interactionSelect) {
				return;
			}
			previewStore.setState({ interactionState: interactionSelect, dirty: true });
			mountPreviews(readLiveConfig(config), readLiveScenarioConfig(scenarioConfig), readLiveDateStepConfig(dateStepConfig), readLiveOfficeHoursPreviewConfig(officeHoursPreviewConfig), readLiveStepFourConfig(stepFourConfig), readLiveDeliveryConfig(deliveryConfig));
			updatePreviewWarning();
		});
		$(document).on('click', '[data-mp-cc-runtime]', function () {
			var runtimeState = String($(this).data('mpCcRuntime') || '');
			if (!runtimeState) {
				return;
			}
			previewStore.setState({ runtimeState: runtimeState, dirty: true });
			mountPreviews(readLiveConfig(config), readLiveScenarioConfig(scenarioConfig), readLiveDateStepConfig(dateStepConfig), readLiveOfficeHoursPreviewConfig(officeHoursPreviewConfig), readLiveStepFourConfig(stepFourConfig), readLiveDeliveryConfig(deliveryConfig));
			updatePreviewWarning();
		});
		$(document).on('change', '[data-mp-cc-runtime-select]', function () {
			var runtimeSelect = String($(this).val() || '');
			if (!runtimeSelect) {
				return;
			}
			previewStore.setState({ runtimeState: runtimeSelect, dirty: true });
			mountPreviews(readLiveConfig(config), readLiveScenarioConfig(scenarioConfig), readLiveDateStepConfig(dateStepConfig), readLiveOfficeHoursPreviewConfig(officeHoursPreviewConfig), readLiveStepFourConfig(stepFourConfig), readLiveDeliveryConfig(deliveryConfig));
			updatePreviewWarning();
		});
		$(document).on('change', '[data-mp-cc-sandbox-select]', function () {
			var sandbox = String($(this).val() || '');
			applySandboxScenario(previewStore, sandbox);
			previewStore.setState({ dirty: true });
			mountPreviews(readLiveConfig(config), readLiveScenarioConfig(scenarioConfig), readLiveDateStepConfig(dateStepConfig), readLiveOfficeHoursPreviewConfig(officeHoursPreviewConfig), readLiveStepFourConfig(stepFourConfig), readLiveDeliveryConfig(deliveryConfig));
			updatePreviewWarning();
		});
		$(document).on('click', '[data-mp-cc-test-util]', function () {
			var util = String($(this).data('mpCcTestUtil') || '');
			if (util === 'fulfillment') {
				previewStore.setState({ scenario: 'other_city_delivery', dirty: true });
			} else if (util === 'discounts') {
				previewStore.setState({
					runtime: {
						summary: {
							items: 3,
							subtotal: '3 670 ₽',
							shipping: '490 ₽',
							discount: '-700 ₽',
							giftCard: '-300 ₽',
							tax: '160 ₽',
							total: '3 320 ₽'
						}
					},
					runtimeState: 'success',
					dirty: true
				});
			} else if (util === 'validation_payment') {
				previewStore.setState({ runtimeState: 'error', interactionState: 'focus', dirty: true });
			}
			mountPreviews(readLiveConfig(config), readLiveScenarioConfig(scenarioConfig), readLiveDateStepConfig(dateStepConfig), readLiveOfficeHoursPreviewConfig(officeHoursPreviewConfig), readLiveStepFourConfig(stepFourConfig), readLiveDeliveryConfig(deliveryConfig));
			updatePreviewWarning();
		});
		$(document).on('click', '[data-mp-cc-delivery-bulk-apply]', function () {
			var liveDelivery = readLiveDeliveryConfig(deliveryConfig);
			applyDeliveryBulkUpdate(liveDelivery);
			rerenderDebounced();
		});
		$(document).on('click', '[data-mp-cc-tab-reset]', function () {
			var activeSection = detectActiveSettingsSection();
			if (!activeSection || !settingsDefaults[activeSection] || typeof settingsDefaults[activeSection] !== 'object') {
				return;
			}
			var tabDefaultsMap = {};
			flattenDefaultsForSection(tabDefaultsMap, settingsDefaults[activeSection], activeSection, []);
			applyDefaultsToForm(tabDefaultsMap);
			rerenderDebounced();
		});
		$(document).on('click', '[data-mp-cc-step1-reset]', function () {
			var defaultsMap = {};
			flattenStepOneDefaults(defaultsMap, defaults, []);
			applyDefaultsToForm(defaultsMap);
			rerender();
		});
		function validateMotionFormBeforeSave() {
			var errs = [];
			var keys = ['step_transition', 'rail', 'step_screen', 'field_state', 'summary_numbers', 'skeleton_shimmer'];
			keys.forEach(function (k) {
				var el = document.getElementsByName('mp_custom_checkout_settings[motion][durations_ms][' + k + ']')[0];
				if (!el) {
					return;
				}
				var n = parseInt(String(el.value || ''), 10);
				if (!Number.isFinite(n) || n < 0 || n > 4000) {
					errs.push('durations_ms.' + k + ': 0–4000 мс');
				}
			});
			var useDesk = document.getElementsByName('mp_custom_checkout_settings[motion][mobile][use_desktop_durations]')[0];
			if (!useDesk || !useDesk.checked) {
				keys.forEach(function (k) {
					var el = document.getElementsByName('mp_custom_checkout_settings[motion][mobile][durations_ms][' + k + ']')[0];
					if (!el) {
						return;
					}
					var n = parseInt(String(el.value || ''), 10);
					if (!Number.isFinite(n) || n < 0 || n > 4000) {
						errs.push('mobile.durations_ms.' + k + ': 0–4000 мс');
					}
				});
			}
			var profEl = document.getElementsByName('mp_custom_checkout_settings[motion][ease_profile]')[0];
			if (profEl) {
				var p = String(profEl.value || '').trim().toLowerCase();
				var allowed = (window.mpCcAdmin && window.mpCcAdmin.motionEasePresetIds) ? window.mpCcAdmin.motionEasePresetIds.concat(['custom']) : ['snappy', 'balanced', 'smooth', 'custom'];
				if (allowed.indexOf(p) < 0) {
					errs.push('ease_profile: допустимо ' + allowed.join(', '));
				}
				if (p === 'custom') {
					var s = document.getElementsByName('mp_custom_checkout_settings[motion][ease][standard]')[0];
					var e = document.getElementsByName('mp_custom_checkout_settings[motion][ease][emphasized]')[0];
					if (!s || !String(s.value || '').trim()) {
						errs.push('ease.standard обязателен для custom');
					}
					if (!e || !String(e.value || '').trim()) {
						errs.push('ease.emphasized обязателен для custom');
					}
				}
			}
			var thEl = document.getElementsByName('mp_custom_checkout_settings[motion][throttle][min_interval_ms]')[0];
			if (thEl) {
				var t = parseInt(String(thEl.value || ''), 10);
				if (!Number.isFinite(t) || t < 0 || t > 2000) {
					errs.push('throttle.min_interval_ms: 0–2000');
				}
			}
			return errs;
		}

		$(document).on('submit', 'form', function (event) {
			var activeSection = detectActiveSettingsSection();
			if (activeSection === 'delivery') {
				var deliveryErrors = validateDeliveryConfigConsistency(readLiveDeliveryConfig(deliveryConfig));
				if (deliveryErrors.length) {
					event.preventDefault();
					window.alert('Проверьте конфигурацию доставки:\n- ' + deliveryErrors.join('\n- '));
					return;
				}
			}
			if ($('[name^="mp_custom_checkout_settings[motion]"]').length) {
				var motionErrors = validateMotionFormBeforeSave();
				if (motionErrors.length) {
					event.preventDefault();
					window.alert('Проверьте раздел «Анимации»:\n- ' + motionErrors.join('\n- '));
					return;
				}
			}
			previewStore.setState({ dirty: false });
			updatePreviewWarning();
		});
	});

	$(function () {
		if (!$('.mp-cc-admin-shell').length) {
			return;
		}
		refreshDeliveryAdminUi();
		$(document).on('click', '.mp-cc-admin-shell__tab', function () {
			window.setTimeout(refreshDeliveryAdminUi, 0);
		});
	});

	$(function () {
		var $preview = $('[data-mp-cc-motion-preview="1"]');
		if (!$preview.length) {
			return;
		}
		var $stage = $preview.find('[data-mp-cc-motion-stage="1"]');
		var $rail = $preview.find('[data-mp-cc-motion-rail-fill="1"]');
		var $panel = $preview.find('[data-mp-cc-motion-panel="1"]');
		var $amount = $preview.find('[data-mp-cc-motion-amount="1"]');
		var durationKeys = ['step_transition', 'rail', 'step_screen', 'field_state', 'summary_numbers', 'skeleton_shimmer'];

		function motionInputName(branch, key) {
			return 'mp_custom_checkout_settings[motion][' + branch + '][' + key + ']';
		}

		function readIntByName(name, fallback) {
			var el = document.getElementsByName(name)[0];
			var n = parseInt(String(el && el.value != null ? el.value : ''), 10);
			if (!Number.isFinite(n)) {
				return fallback;
			}
			return Math.max(0, Math.min(4000, n));
		}

		function resolveEaseFromForm() {
			var profileEl = document.getElementsByName('mp_custom_checkout_settings[motion][ease_profile]')[0];
			var profile = profileEl ? String(profileEl.value || '').trim().toLowerCase() : 'balanced';
			var map = (window.mpCcAdmin && window.mpCcAdmin.motionEasePresetsMap) ? window.mpCcAdmin.motionEasePresetsMap : {};
			if (profile !== 'custom' && map[profile] && map[profile].standard) {
				return String(map[profile].standard || 'ease');
			}
			var stdEl = document.getElementsByName('mp_custom_checkout_settings[motion][ease][standard]')[0];
			return String(stdEl && stdEl.value ? stdEl.value : 'ease');
		}

		function applyPreviewCssFromForm() {
			if (!$stage.length) {
				return;
			}
			var railMs = readIntByName(motionInputName('durations_ms', 'rail'), 420);
			var panelMs = readIntByName(motionInputName('durations_ms', 'step_screen'), 200);
			var fieldMs = readIntByName(motionInputName('durations_ms', 'field_state'), 220);
			var sumMs = readIntByName(motionInputName('durations_ms', 'summary_numbers'), 340);
			var ease = resolveEaseFromForm();
			var railS = Math.max(0, railMs / 1000).toFixed(4) + 's';
			var panelS = Math.max(0, panelMs / 1000).toFixed(4) + 's';
			var sumS = Math.max(0, sumMs / 1000).toFixed(4) + 's';
			$stage[0].style.setProperty('--mp-cc-ap-rail', railS);
			$stage[0].style.setProperty('--mp-cc-ap-panel', panelS);
			$stage[0].style.setProperty('--mp-cc-ap-field', Math.max(0, fieldMs / 1000).toFixed(4) + 's');
			$stage[0].style.setProperty('--mp-cc-ap-sum', sumS);
			$stage[0].style.setProperty('--mp-cc-ap-ease', ease);
		}

		function applyDesktopPreset(presetId) {
			var raw = $preview.attr('data-motion-duration-presets') || '{}';
			var presets = {};
			try {
				presets = JSON.parse(raw) || {};
			} catch (e0) {
				presets = {};
			}
			var pack = presets[presetId];
			if (!pack || typeof pack !== 'object') {
				return;
			}
			durationKeys.forEach(function (key) {
				if (!Object.prototype.hasOwnProperty.call(pack, key)) {
					return;
				}
				var el = document.getElementsByName(motionInputName('durations_ms', key))[0];
				if (el) {
					el.value = String(pack[key]);
				}
			});
			applyPreviewCssFromForm();
		}

		function playPreview() {
			applyPreviewCssFromForm();
			$rail.removeClass('is-mp-cc-ap-play');
			$panel.removeClass('is-mp-cc-ap-play');
			$amount.removeClass('is-mp-cc-ap-play');
			window.requestAnimationFrame(function () {
				$rail.addClass('is-mp-cc-ap-play');
				$panel.addClass('is-mp-cc-ap-play');
				$amount.addClass('is-mp-cc-ap-play');
			});
			var railMs = readIntByName(motionInputName('durations_ms', 'rail'), 420);
			var panelMs = readIntByName(motionInputName('durations_ms', 'step_screen'), 200);
			var sumMs = readIntByName(motionInputName('durations_ms', 'summary_numbers'), 340);
			var resetMs = Math.max(railMs, panelMs, sumMs) + 80;
			window.setTimeout(function () {
				$rail.removeClass('is-mp-cc-ap-play');
				$panel.removeClass('is-mp-cc-ap-play');
				$amount.removeClass('is-mp-cc-ap-play');
			}, resetMs);
		}

		$preview.on('click', '[data-mp-cc-motion-preset]', function () {
			var id = String($(this).attr('data-mp-cc-motion-preset') || '');
			if (!id) {
				return;
			}
			applyDesktopPreset(id);
		});
		$preview.on('click', '[data-mp-cc-motion-play]', function () {
			playPreview();
		});

		$(document).on('input change', '.mp-cc-admin-shell input, .mp-cc-admin-shell textarea', function () {
			var n = String(this.name || '');
			if (n.indexOf('[motion]') < 0) {
				return;
			}
			applyPreviewCssFromForm();
		});

		applyPreviewCssFromForm();
	});
})(jQuery);
