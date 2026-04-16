<?php
/**
 * CSS/JS экрана успеха (маршрут после оплаты).
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout\Hooks;

defined( 'ABSPATH' ) || exit;

/**
 * Class CheckoutSuccessFrontendHooks
 */
final class CheckoutSuccessFrontendHooks {
	public static function register(): void {
		\MP\CustomCheckout\Frontend\Hooks\CheckoutSuccessFrontendHooks::register();
	}
}
