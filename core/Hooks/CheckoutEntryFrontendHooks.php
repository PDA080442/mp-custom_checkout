<?php
/**
 * Фронтенд: скрипт привязки перехода к оформлению из sticky-корзины.
 *
 * Ручная проверка сценариев: главная, каталог, товар, корзина (пустая/непустая),
 * прямой URL checkout без AJAX — редирект с уведомлением.
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout\Hooks;

defined( 'ABSPATH' ) || exit;

/**
 * Class CheckoutEntryFrontendHooks
 */
final class CheckoutEntryFrontendHooks {
	public static function register(): void {
		\MP\CustomCheckout\Frontend\Hooks\CheckoutEntryFrontendHooks::register();
	}
}
