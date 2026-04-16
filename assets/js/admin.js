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
		return {
			previewEnabled: Boolean(source.admin_preview && source.admin_preview.enabled !== false),
			title: labels.title || 'Корзина',
			summaryTitle: labels.summary_title || 'Сводка заказа',
			subtotalLabel: labels.subtotal_label || 'Подытог',
			itemsLabel: labels.items_label || 'Позиций',
			continueLabel: labels.continue_label || 'Продолжить оформление',
			returnLabel: labels.return_label || 'Вернуться в магазин',
			emptyTitle: labels.empty_title || 'Корзина пуста',
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
			pointDescription: point && point.description ? String(point.description) : ''
		};
	}

	function getStepFourConfigFromRuntime() {
		var source = window.mpCcAdmin && window.mpCcAdmin.stepFourConfig ? window.mpCcAdmin.stepFourConfig : {};
		var contact = source.contact_block || {};
		var address = source.address_block || {};
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
			geoPreview: source.geo_preview && source.geo_preview.enabled !== false,
			geo: source.address_geo && typeof source.address_geo === 'object' ? source.address_geo : {}
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

	function renderPreview(config) {
		var html = '';
		html += '<section class="mp-cc-admin-preview" id="mp-cc-admin-step1-preview"';
		html += ' data-card-compact="' + (config.cardCompact ? '1' : '0') + '"';
		html += ' data-card-emphasis="' + escapeHtml(config.cardEmphasis) + '"';
		html += ' data-summary-emphasis="' + escapeHtml(config.summaryEmphasis) + '"';
		html += '>';
		html += '<h2>Step 1 Preview</h2>';
		html += '<div class="mp-cc-admin-preview__toolbar">';
		html += '<button type="button" class="button button-secondary" data-mp-cc-step1-reset="1">Reset Step 1 to defaults</button>';
		html += '</div>';
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
		html += '</section>';
		return html;
	}

	function renderScenarioPreview(config) {
		var order = Array.isArray(config.cardOrder) ? config.cardOrder : ['pickup', 'delivery'];
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
			html += '<article class="mp-cc-admin-preview__scenario-card">';
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

	function renderDatePreview(config) {
		var blockedTotal = config.holidayDates.length + config.closedDates.length;
		var html = '';
		html += '<section class="mp-cc-admin-preview mp-cc-admin-preview--date" id="mp-cc-admin-date-preview">';
		html += '<h2>Date Step Preview</h2>';
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
		html += '</section>';
		return html;
	}

	function renderStepFourPreview(cfg) {
		var order = Array.isArray(cfg.contact.fieldOrder) ? cfg.contact.fieldOrder : [];
		var html = '';
		html += '<section class="mp-cc-admin-preview mp-cc-admin-preview--step4" id="mp-cc-admin-step4-preview">';
		html += '<h2>Step 4 Contact Preview</h2>';
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
		var $field = $('[name="' + name + '"]').first();
		if (!$field.length) {
			return fallback;
		}
		if ($field.is(':checkbox')) {
			return $field.is(':checked');
		}
		return String($field.val() || '');
	}

	function readLiveConfig(baseConfig) {
		var cfg = $.extend({}, baseConfig);
		cfg.title = readFormValue('mp_custom_checkout_settings[step_1][labels][title]', cfg.title);
		cfg.summaryTitle = readFormValue('mp_custom_checkout_settings[step_1][labels][summary_title]', cfg.summaryTitle);
		cfg.subtotalLabel = readFormValue('mp_custom_checkout_settings[step_1][labels][subtotal_label]', cfg.subtotalLabel);
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
		var defaults = getDefaults();
		var flags = window.mpCcAdmin && window.mpCcAdmin.featureFlags ? window.mpCcAdmin.featureFlags : {};
		if (!config.previewEnabled || flags.admin_live_preview === false) {
			return;
		}
		var $wrap = $('.wrap').first();
		if (!$wrap.length) {
			return;
		}
		if ($('#mp-cc-admin-step1-preview').length) {
			return;
		}
		$wrap.append(renderPreview(config));
		if (scenarioConfig.previewEnabled) {
			$wrap.append(renderScenarioPreview(scenarioConfig));
		}
		if (dateStepConfig.previewEnabled) {
			$wrap.append(renderDatePreview(dateStepConfig));
		}
		if (officeHoursPreviewConfig.previewEnabled) {
			$wrap.append(renderOfficeHoursPreview(officeHoursPreviewConfig));
		}
		if (stepFourConfig.previewEnabled) {
			$wrap.append(renderStepFourPreview(stepFourConfig));
		}
		enhanceStepOneFields();
		enhanceStepThreeFields();
		enhanceStepFourFields();
		refreshStepThreeEmptyIndicators();

		var rerender = function () {
			var nextConfig = readLiveConfig(config);
			$('#mp-cc-admin-step1-preview').replaceWith(renderPreview(nextConfig));
			if (scenarioConfig.previewEnabled) {
				var nextScenarioConfig = readLiveScenarioConfig(scenarioConfig);
				$('#mp-cc-admin-scenario-preview').replaceWith(renderScenarioPreview(nextScenarioConfig));
			}
			if (dateStepConfig.previewEnabled) {
				var nextDateConfig = readLiveDateStepConfig(dateStepConfig);
				$('#mp-cc-admin-date-preview').replaceWith(renderDatePreview(nextDateConfig));
			}
			if (officeHoursPreviewConfig.previewEnabled) {
				var nextOfficeCfg = readLiveOfficeHoursPreviewConfig(officeHoursPreviewConfig);
				$('#mp-cc-admin-office-preview').replaceWith(renderOfficeHoursPreview(nextOfficeCfg));
			}
			if (stepFourConfig.previewEnabled) {
				var nextStepFourCfg = readLiveStepFourConfig(stepFourConfig);
				$('#mp-cc-admin-step4-preview').replaceWith(renderStepFourPreview(nextStepFourCfg));
			}
		};
		$(document).on('input change', '[name^="mp_custom_checkout_settings[step_1]"]', function () {
			rerender();
		});
		$(document).on('input change', '[name^="mp_custom_checkout_settings[step_2]"]', function () {
			rerender();
		});
		$(document).on('input change', '[name^="mp_custom_checkout_settings[step_3]"]', function () {
			refreshStepThreeEmptyIndicators();
			rerender();
		});
		$(document).on('input change', '[name^="mp_custom_checkout_settings[step_4]"]', function () {
			rerender();
		});
		$(document).on('click', '[data-mp-cc-step1-reset]', function () {
			var defaultsMap = {};
			flattenStepOneDefaults(defaultsMap, defaults, []);
			applyDefaultsToForm(defaultsMap);
			rerender();
		});
	});
})(jQuery);
