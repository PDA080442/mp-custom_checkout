/**
 * ПВЗ СДЭК: список в нашем модале или нативный popup CDEKWidget (как в эталонном cdek-checkout-map.js).
 * Режимы: pvz_list (список offices_json), pvz_map (popup виджета), pickup_list (точки магазина).
 */
(function ($) {
	'use strict';

	var activeModal = null;
	var lastFocus = null;
	/** Кэш инстанса виджета (паритет с эталоном: updateOfficesRaw / updateLocation / open). */
	var nativeCdekWidgetInstance = null;

	function escHtml(s) {
		return String(s == null ? '' : s)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#039;');
	}

	function getCfg() {
		return window.mpCcCdekWidget && typeof window.mpCcCdekWidget === 'object' ? window.mpCcCdekWidget : {};
	}

	function getCdekInline() {
		return window.cdek && typeof window.cdek === 'object' ? window.cdek : {};
	}

	function getWidgetCtor() {
		return window.CDEKWidget;
	}

	function mergeLabels(opts) {
		var fromPhp = getCfg().labels || {};
		var fromOpts = opts && opts.labels && typeof opts.labels === 'object' ? opts.labels : {};
		return $.extend({}, fromPhp, fromOpts);
	}

	/**
	 * @param {string} mode
	 * @returns {'pvz_map'|'pvz_list'|'pickup_list'}
	 */
	function normalizeBridgeMode(mode) {
		var m = String(mode || 'pvz_map').toLowerCase();
		if (m === 'pvz_list') {
			return 'pvz_list';
		}
		if (m === 'list' || m === 'pickup_list' || m === 'pickup') {
			return 'pickup_list';
		}
		return 'pvz_map';
	}

	function officeCodeFromRaw(o) {
		if (!o || typeof o !== 'object') {
			return '';
		}
		if (o.code != null && String(o.code).trim() !== '') {
			return String(o.code).trim();
		}
		if (o.uuid != null && String(o.uuid).trim() !== '') {
			return String(o.uuid).trim();
		}
		return '';
	}

	function officeAddressFromRaw(o) {
		if (!o || typeof o !== 'object') {
			return '';
		}
		var loc = o.location && typeof o.location === 'object' ? o.location : {};
		var line = loc.address_full || loc.address || o.address || o.address_full || o.name || '';
		return String(line || '').trim();
	}

	/**
	 * @returns {Array<{id:string,address:string}>}
	 */
	function parseOfficesFromCfg(cfg) {
		cfg = cfg && typeof cfg === 'object' ? cfg : {};
		var raw;
		try {
			raw = JSON.parse(cfg.offices_json || '[]');
		} catch (e1) {
			raw = [];
		}
		if (!Array.isArray(raw)) {
			return [];
		}
		var out = [];
		for (var i = 0; i < raw.length; i += 1) {
			var row = raw[i];
			var code = officeCodeFromRaw(row);
			if (!code) {
				continue;
			}
			var addr = officeAddressFromRaw(row);
			out.push({ id: code, address: addr || code });
		}
		return out;
	}

	function removeModal() {
		if (activeModal && activeModal.$overlay) {
			activeModal.$overlay.remove();
		}
		activeModal = null;
		$('body').removeClass('mp-cc-cdek-modal-open');
	}

	function restoreFocus() {
		if (lastFocus && typeof lastFocus.focus === 'function') {
			try {
				lastFocus.focus({ preventScroll: true });
			} catch (e) {
				try {
					lastFocus.focus();
				} catch (e2) {
					// ignore
				}
			}
		}
		lastFocus = null;
	}

	function trapTab(e, $modal) {
		if (e.key !== 'Tab') {
			return;
		}
		var focusable = $modal
			.find(
				'a[href], button:not([disabled]), textarea, input, select, [tabindex]:not([tabindex="-1"])'
			)
			.filter(':visible');
		if (!focusable.length) {
			return;
		}
		var first = focusable.get(0);
		var last = focusable.get(focusable.length - 1);
		if (e.shiftKey && document.activeElement === first) {
			e.preventDefault();
			last.focus();
		} else if (!e.shiftKey && document.activeElement === last) {
			e.preventDefault();
			first.focus();
		}
	}

	function closeModal(ctx, confirmed) {
		var hadChoose = ctx && ctx.chosenThisOpen;
		if (ctx && ctx.widgetInstance && typeof ctx.widgetInstance.destroy === 'function') {
			try {
				ctx.widgetInstance.destroy();
			} catch (err) {
				// ignore
			}
		}
		if (ctx && ctx.onKey) {
			$(document).off('keydown.mpCcCdekModal', ctx.onKey);
		}
		removeModal();
		restoreFocus();
		if (
			!confirmed &&
			!hadChoose &&
			ctx &&
			typeof ctx.logValidationFailure === 'function' &&
			!ctx.skipAbandonLog
		) {
			ctx.logValidationFailure({ event_type: 'pvz_picker_closed_without_selection' });
		}
	}

	/**
	 * @param {'pickup_point'|'cdek_office'} selectionKind
	 */
	function showListModal(opts, labels, pickupPoints, selectionKind, titleOverride) {
		pickupPoints = Array.isArray(pickupPoints) ? pickupPoints : [];
		selectionKind = selectionKind || 'pickup_point';
		var ctx = {
			chosenThisOpen: false,
			widgetInstance: null,
			logValidationFailure: opts.logValidationFailure,
			onKey: null,
			skipAbandonLog: false
		};
		var $overlay = $('<div class="mp-cc-cdek-modal" role="dialog" aria-modal="true" aria-labelledby="mp-cc-cdek-modal-title"></div>');
		var $panel = $('<div class="mp-cc-cdek-modal__panel"></div>');
		var idTitle = 'mp-cc-cdek-modal-title';
		var headTitle =
			titleOverride ||
			labels.list_title ||
			labels.modal_list_title ||
			'Выбор пункта';
		$panel.append('<h2 class="mp-cc-cdek-modal__title" id="' + idTitle + '">' + escHtml(headTitle) + '</h2>');
		var $live = $('<div class="mp-cc-cdek-modal__live" aria-live="polite"></div>');
		$panel.append($live);
		var $list = $('<div class="mp-cc-cdek-modal__list" role="radiogroup" aria-label="' + escHtml(labels.list_group || '') + '"></div>');
		if (!pickupPoints.length) {
			$list.append(
				'<p class="mp-cc-cdek-modal__empty">' +
					escHtml(labels.list_empty || 'Нет доступных точек.') +
					'</p>'
			);
		}
		for (var i = 0; i < pickupPoints.length; i += 1) {
			var p = pickupPoints[i] || {};
			var pid = String(p.id || '');
			var addr = String(p.address || p.title || pid);
			var rowId = 'mp-cc-pvz-modal-' + i;
			$list.append(
				'<label class="mp-cc-cdek-modal__item" for="' +
					rowId +
					'">' +
					'<input type="radio" name="mp-cc-modal-pvz" id="' +
					rowId +
					'" value="' +
					escHtml(pid) +
					'">' +
					'<span>' +
					escHtml(addr) +
					'</span></label>'
			);
		}
		$panel.append($list);
		var $actions = $('<div class="mp-cc-cdek-modal__actions"></div>');
		var $confirm = $(
			'<button type="button" class="mp-cc-cdek-modal__btn mp-cc-cdek-modal__btn--primary">' +
				escHtml(labels.confirm || 'Выбрать') +
				'</button>'
		);
		var $close = $(
			'<button type="button" class="mp-cc-cdek-modal__btn">' + escHtml(labels.close || 'Закрыть') + '</button>'
		);
		$actions.append($confirm, $close);
		$panel.append($actions);
		$overlay.append($panel);
		$('body').append($overlay).addClass('mp-cc-cdek-modal-open');

		function onClose() {
			closeModal(ctx, false);
		}
		function onConfirm() {
			if (!pickupPoints.length) {
				onClose();
				return;
			}
			var val = $list.find('input[name="mp-cc-modal-pvz"]:checked').val();
			if (!val) {
				$live.text(labels.pick_required || 'Выберите пункт из списка.');
				return;
			}
			if (selectionKind === 'cdek_office') {
				if (typeof window.mpCcSetCdekOfficeCode !== 'function') {
					$live.text(labels.map_pick_failed || 'Не удалось получить код пункта.');
					return;
				}
				ctx.chosenThisOpen = true;
				window
					.mpCcSetCdekOfficeCode(String(val))
					.fail(function () {
						ctx.chosenThisOpen = false;
					})
					.then(function () {
						closeModal(ctx, true);
					});
				return;
			}
			ctx.chosenThisOpen = true;
			if (typeof opts.onPickupPointChosen === 'function') {
				opts.onPickupPointChosen(String(val));
			}
			closeModal(ctx, true);
		}
		$close.on('click', onClose);
		$confirm.on('click', onConfirm);
		$overlay.on('click', function (e) {
			if ($(e.target).is('.mp-cc-cdek-modal')) {
				onClose();
			}
		});
		ctx.onKey = function (e) {
			if (e.key === 'Escape') {
				e.preventDefault();
				onClose();
				return;
			}
			trapTab(e, $overlay);
		};
		$(document).on('keydown.mpCcCdekModal', ctx.onKey);
		activeModal = { $overlay: $overlay };
		window.setTimeout(function () {
			$close.trigger('focus');
		}, 0);
	}

	function showPvzEmptyModal(opts, labels) {
		var cfg = getCfg();
		var hint = String(cfg.reason_hint || labels.reason_hint || '').trim();
		var ctx = {
			chosenThisOpen: false,
			widgetInstance: null,
			logValidationFailure: opts.logValidationFailure,
			onKey: null,
			skipAbandonLog: true
		};
		var $overlay = $('<div class="mp-cc-cdek-modal" role="dialog" aria-modal="true" aria-labelledby="mp-cc-cdek-modal-empty-title"></div>');
		var $panel = $('<div class="mp-cc-cdek-modal__panel"></div>');
		var baseMsg =
			labels.pvz_no_offices ||
			labels.map_unavailable ||
			'Карта ПВЗ временно недоступна. Выберите другой способ доставки.';
		var body = hint ? baseMsg + ' ' + hint : baseMsg;
		$panel.append(
			'<h2 class="mp-cc-cdek-modal__title" id="mp-cc-cdek-modal-empty-title">' +
				escHtml(labels.map_unavailable_title || labels.list_title || 'ПВЗ СДЭК') +
				'</h2>'
		);
		$panel.append(
			'<p class="mp-cc-cdek-modal__empty">' + escHtml(body) + '</p>'
		);
		var $actions = $('<div class="mp-cc-cdek-modal__actions"></div>');
		var $close = $(
			'<button type="button" class="mp-cc-cdek-modal__btn mp-cc-cdek-modal__btn--primary">' +
				escHtml(labels.close || 'Закрыть') +
				'</button>'
		);
		$actions.append($close);
		$panel.append($actions);
		$overlay.append($panel);
		$('body').append($overlay).addClass('mp-cc-cdek-modal-open');

		function onClose() {
			closeModal(ctx, false);
		}
		$close.on('click', onClose);
		$overlay.on('click', function (e) {
			if ($(e.target).is('.mp-cc-cdek-modal')) {
				onClose();
			}
		});
		ctx.onKey = function (e) {
			if (e.key === 'Escape') {
				e.preventDefault();
				onClose();
				return;
			}
			trapTab(e, $overlay);
		};
		$(document).on('keydown.mpCcCdekModal', ctx.onKey);
		activeModal = { $overlay: $overlay };
		window.setTimeout(function () {
			$close.trigger('focus');
		}, 0);
	}

	function logPvzFallback(opts, cfg, subtype) {
		if (opts && typeof opts.logValidationFailure === 'function') {
			opts.logValidationFailure({
				event_type: 'pvz_map_fallback_to_list',
				reason: String(cfg.reason || ''),
				subtype: subtype || ''
			});
		}
	}

	function openPvzMapFallback(opts, labels, logSubtype) {
		var cfg = getCfg();
		logPvzFallback(opts, cfg, logSubtype);
		var offices = parseOfficesFromCfg(cfg);
		if (!offices.length) {
			if (typeof opts.logValidationFailure === 'function') {
				opts.logValidationFailure({
					event_type: 'pvz_map_empty_offices',
					reason: String(cfg.reason || ''),
					subtype: logSubtype || ''
				});
			}
			showPvzEmptyModal(opts, labels);
			return;
		}
		var listLabels = $.extend({}, labels, {
			list_title: labels.map_unavailable_title || labels.list_title
		});
		showListModal(opts, listLabels, offices, 'cdek_office', listLabels.list_title);
	}

	function openPvzListModal(opts, labels) {
		var cfg = getCfg();
		var offices = parseOfficesFromCfg(cfg);
		if (!offices.length) {
			if (typeof opts.logValidationFailure === 'function') {
				opts.logValidationFailure({
					event_type: 'pvz_list_empty_offices',
					reason: String(cfg.reason || ''),
					subtype: 'pvz_list'
				});
			}
			showPvzEmptyModal(opts, labels);
			return;
		}
		showListModal(opts, labels, offices, 'cdek_office', labels.list_title || labels.modal_list_title);
	}

	function openNativeCdekPopup(opts, labels) {
		var Widget = getWidgetCtor();
		var cfg = getCfg();
		var inline = getCdekInline();
		var apiKey = cfg.apiKey || inline.key || '';
		var lang = cfg.lang || inline.lang || 'rus';
		var debugFlag = cfg.debug === true || cfg.debug === 1 || cfg.debug === '1';

		if (!apiKey || !Widget) {
			openPvzMapFallback(opts, labels, !apiKey ? 'map_not_ready' : 'cdek_widget_missing');
			return;
		}

		var officesRaw;
		try {
			officesRaw = JSON.parse(cfg.offices_json || '[]');
		} catch (e1) {
			officesRaw = [];
		}
		var defaultLocation = cfg.default_city || 'Москва';
		var goods = Array.isArray(cfg.goods) && cfg.goods.length ? cfg.goods : [{ length: 10, width: 10, height: 10, weight: 1000 }];
		var servicePath = String(cfg.service_path || '').trim();
		var chosenInFlight = false;

		function maybeCloseWidgetAfterSave() {
			var inst = nativeCdekWidgetInstance;
			if (!inst || typeof inst.close !== 'function') {
				return;
			}
			var autoClose = Boolean(inline.close || cfg.map_auto_close);
			if (!autoClose) {
				return;
			}
			try {
				inst.close();
			} catch (eClose) {
				// ignore
			}
		}

		function onChoose(_type, _tariff, address) {
			if (chosenInFlight) {
				return;
			}
			var code = address && address.code ? String(address.code) : '';
			if (!code || typeof window.mpCcSetCdekOfficeCode !== 'function') {
				return;
			}
			chosenInFlight = true;
			window
				.mpCcSetCdekOfficeCode(code)
				.always(function () {
					chosenInFlight = false;
				})
				.then(function () {
					maybeCloseWidgetAfterSave();
				});
		}

		try {
			if (nativeCdekWidgetInstance === null) {
				var wopts = {
					apiKey: apiKey,
					popup: true,
					lang: lang,
					debug: debugFlag,
					defaultLocation: defaultLocation,
					officesRaw: officesRaw,
					goods: goods,
					hideDeliveryOptions: { door: true, office: false },
					onChoose: onChoose
				};
				if (servicePath !== '') {
					wopts.servicePath = servicePath;
				}
				nativeCdekWidgetInstance = new Widget(wopts);
			} else {
				if (typeof nativeCdekWidgetInstance.updateOfficesRaw === 'function') {
					nativeCdekWidgetInstance.updateOfficesRaw(officesRaw);
				}
				if (typeof nativeCdekWidgetInstance.updateLocation === 'function') {
					nativeCdekWidgetInstance.updateLocation(defaultLocation);
				}
			}
			if (nativeCdekWidgetInstance && typeof nativeCdekWidgetInstance.open === 'function') {
				nativeCdekWidgetInstance.open();
			}
		} catch (err) {
			nativeCdekWidgetInstance = null;
			openPvzMapFallback(opts, labels, 'widget_init_failed');
		}
	}

	function open(opts) {
		opts = opts || {};
		var labels = mergeLabels(opts);
		var bridgeMode = normalizeBridgeMode(opts.mode);
		var pickupPoints = Array.isArray(opts.pickupPoints) ? opts.pickupPoints : [];
		lastFocus = opts.trigger || document.activeElement;

		if (bridgeMode === 'pickup_list') {
			showListModal(opts, labels, pickupPoints, 'pickup_point', null);
			return;
		}

		if (bridgeMode === 'pvz_list') {
			openPvzListModal(opts, labels);
			return;
		}

		var cfg = getCfg();
		if (!cfg.map_ready || !getWidgetCtor()) {
			openPvzMapFallback(opts, labels, !cfg.map_ready ? 'map_not_ready' : 'cdek_widget_missing');
			return;
		}

		openNativeCdekPopup(opts, labels);
	}

	window.MPCC_CDEKWidgetBridge = {
		open: open
	};
})(jQuery);
