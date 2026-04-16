<?php
/**
 * Success page frontend assets hook facade.
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout\Frontend\Hooks;

defined( 'ABSPATH' ) || exit;

final class CheckoutSuccessFrontendHooks {

	public static function register(): void {
		\MP\CustomCheckout\Hooks\CheckoutSuccessFrontendHooks::register();
	}
}
