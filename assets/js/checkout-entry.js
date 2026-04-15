/**
 * Вход на кастомный checkout из sticky-корзины: AJAX → флаг сессии → переход на URL.
 */
(function ($) {
	'use strict';

	var cfg = window.mpCcCheckoutEntry || {};

	function postGrant(done) {
		$.post(cfg.ajaxUrl, {
			action: cfg.action,
			nonce: cfg.nonce
		})
			.done(function (res) {
				if (res && res.success && res.data && res.data.checkout_url) {
					if (typeof done === 'function') {
						done(null, res.data.checkout_url);
					} else {
						window.location.href = res.data.checkout_url;
					}
					return;
				}
				var msg = (res && res.data && res.data.message) ? res.data.message : (cfg.i18n && cfg.i18n.error) ? cfg.i18n.error : '';
				if (typeof done === 'function') {
					done(new Error(msg || 'error'));
				}
			})
			.fail(function (xhr) {
				var msg = (cfg.i18n && cfg.i18n.error) ? cfg.i18n.error : '';
				try {
					var json = xhr.responseJSON;
					if (json && json.data && json.data.message) {
						msg = json.data.message;
					}
				} catch (e) {}
				if (typeof done === 'function') {
					done(new Error(msg));
				}
			});
	}

	window.mpCcCheckoutEntry = window.mpCcCheckoutEntry || {};

	/**
	 * Установить флаг сессии и перейти на checkout (или вызвать callback(url)).
	 */
	window.mpCcCheckoutEntry.prepareEntry = function (done) {
		postGrant(done);
	};

	/**
	 * Только установить флаг и получить URL без редиректа.
	 */
	window.mpCcCheckoutEntry.prepareEntryOnly = function (done) {
		postGrant(done);
	};
})(jQuery);
