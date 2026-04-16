<?php
/**
 * No-JS fallback hook facade.
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout\Frontend\Hooks;

defined( 'ABSPATH' ) || exit;

final class CheckoutNoJsFallbackHooks {

	public static function register(): void {
		\MP\CustomCheckout\Hooks\CheckoutNoJsFallbackHooks::register();
	}
}
