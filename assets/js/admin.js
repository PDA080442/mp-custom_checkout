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
			summaryEmphasis: styleControls.summary_emphasis || 'default'
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
		enhanceStepOneFields();

		var rerender = function () {
			var nextConfig = readLiveConfig(config);
			$('#mp-cc-admin-step1-preview').replaceWith(renderPreview(nextConfig));
		};
		$(document).on('input change', '[name^="mp_custom_checkout_settings[step_1]"]', function () {
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
