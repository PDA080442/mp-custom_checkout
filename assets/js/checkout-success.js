/**
 * Снижает риск возврата к шагу оплаты через history (не гарантирует отмену POST).
 */
(function () {
	'use strict';
	if (!window.history || typeof window.history.replaceState !== 'function') {
		return;
	}
	try {
		window.history.replaceState(null, '', window.location.href);
	} catch (e) {
		// ignore
	}
})();
