/**
 * Модал выбора ПВЗ: CDEK Widget 3.x (UMD) или список точек магазина (§29.2).
 */
(function ($) {
	'use strict';

	var activeModal = null;
	var lastFocus = null;

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
		if (!confirmed && !hadChoose && ctx && typeof ctx.logValidationFailure === 'function') {
			ctx.logValidationFailure({ event_type: 'pvz_picker_closed_without_selection' });
		}
	}

	function showListModal(opts, labels, pickupPoints) {
		pickupPoints = Array.isArray(pickupPoints) ? pickupPoints : [];
		var ctx = {
			chosenThisOpen: false,
			widgetInstance: null,
			logValidationFailure: opts.logValidationFailure,
			onKey: null
		};
		var $overlay = $('<div class="mp-cc-cdek-modal" role="dialog" aria-modal="true" aria-labelledby="mp-cc-cdek-modal-title"></div>');
		var $panel = $('<div class="mp-cc-cdek-modal__panel"></div>');
		var idTitle = 'mp-cc-cdek-modal-title';
		$panel.append('<h2 class="mp-cc-cdek-modal__title" id="' + idTitle + '">' + escHtml(labels.list_title || labels.modal_list_title || 'Выбор пункта') + '</h2>');
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

	function showMapModal(opts, labels) {
		var Widget = getWidgetCtor();
		var cfg = getCfg();
		var inline = getCdekInline();
		if (!Widget) {
			showListModal(
				opts,
				$.extend({}, labels, { list_title: labels.map_unavailable_title || labels.list_title }),
				opts.pickupPoints || []
			);
			return;
		}
		var ctx = {
			chosenThisOpen: false,
			chosenInFlight: false,
			widgetInstance: null,
			logValidationFailure: opts.logValidationFailure,
			onKey: null
		};
		var $overlay = $('<div class="mp-cc-cdek-modal" role="dialog" aria-modal="true" aria-labelledby="mp-cc-cdek-map-title"></div>');
		var $panel = $('<div class="mp-cc-cdek-modal__panel mp-cc-cdek-modal__panel--map"></div>');
		$panel.append(
			'<div class="mp-cc-cdek-modal__head"><h2 class="mp-cc-cdek-modal__title" id="mp-cc-cdek-map-title">' +
				escHtml(labels.map_title || 'Пункт СДЭК на карте') +
				'</h2>' +
				'<button type="button" class="mp-cc-cdek-modal__icon-close" aria-label="' +
				escHtml(labels.close || 'Закрыть') +
				'">&times;</button></div>'
		);
		var $err = $('<div class="mp-cc-cdek-modal__live" aria-live="polite"></div>');
		$panel.append($err);
		$panel.append('<div id="mp-cc-cdek-modal__map" class="mp-cc-cdek-modal__map"></div>');
		$overlay.append($panel);
		$('body').append($overlay).addClass('mp-cc-cdek-modal-open');

		var $btnClose = $panel.find('.mp-cc-cdek-modal__icon-close');

		function onClose() {
			closeModal(ctx, false);
		}
		$btnClose.on('click', onClose);
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

		var apiKey = cfg.apiKey || inline.key || '';
		var lang = cfg.lang || inline.lang || 'rus';
		var servicePath = cfg.service_path || '';
		if (!apiKey || !servicePath) {
			$err.text(labels.map_config_error || 'Карта недоступна: проверьте настройки СДЭК.');
			activeModal = { $overlay: $overlay };
			$btnClose.trigger('focus');
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

		try {
			ctx.widgetInstance = new Widget({
				root: '#mp-cc-cdek-modal__map',
				apiKey: apiKey,
				lang: lang,
				servicePath: servicePath,
				debug: false,
				defaultLocation: defaultLocation,
				officesRaw: officesRaw,
				goods: goods,
				hideDeliveryOptions: { door: true, office: false },
				popup: false,
				canChoose: true,
				sender: false,
				onChoose: function (_type, _tariff, address) {
					if (ctx.chosenInFlight) {
						return;
					}
					var code = address && address.code ? String(address.code) : '';
					if (!code || typeof window.mpCcSetCdekOfficeCode !== 'function') {
						$err.text(labels.map_pick_failed || 'Не удалось получить код пункта.');
						return;
					}
					ctx.chosenInFlight = true;
					window.mpCcSetCdekOfficeCode(code)
						.always(function () {
							ctx.chosenInFlight = false;
						})
						.then(function () {
							ctx.chosenThisOpen = true;
							closeModal(ctx, true);
						});
				}
			});
		} catch (err) {
			$err.text(labels.map_init_failed || 'Не удалось открыть карту.');
		}
		activeModal = { $overlay: $overlay };
		window.setTimeout(function () {
			$btnClose.trigger('focus');
		}, 0);
	}

	function open(opts) {
		opts = opts || {};
		var labels = mergeLabels(opts);
		var mode = String(opts.mode || 'map');
		var pickupPoints = Array.isArray(opts.pickupPoints) ? opts.pickupPoints : [];
		lastFocus = opts.trigger || document.activeElement;
		if (mode === 'list') {
			showListModal(opts, labels, pickupPoints);
			return;
		}
		if (!getCfg().map_ready) {
			showListModal(opts, labels, pickupPoints);
			return;
		}
		showMapModal(opts, labels);
	}

	window.MPCC_CDEKWidgetBridge = {
		open: open
	};
})(jQuery);
