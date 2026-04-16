<?php
/**
 * Серверный fallback checkout для сценария без JavaScript.
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout\Hooks;

defined( 'ABSPATH' ) || exit;

/**
 * Class CheckoutNoJsFallbackHooks
 */
final class CheckoutNoJsFallbackHooks {
	public static function register(): void {
		\MP\CustomCheckout\Frontend\Hooks\CheckoutNoJsFallbackHooks::register();
	}
}
