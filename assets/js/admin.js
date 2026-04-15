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
			}
		};
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
			'closed_dates': 'Список вручную закрытых дат YYYY-MM-DD (массив).'
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
		enhanceStepOneFields();
		enhanceStepThreeFields();
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
		$(document).on('click', '[data-mp-cc-step1-reset]', function () {
			var defaultsMap = {};
			flattenStepOneDefaults(defaultsMap, defaults, []);
			applyDefaultsToForm(defaultsMap);
			rerender();
		});
	});
})(jQuery);
