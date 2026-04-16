<?php
/**
 * Frontend entry hook facade.
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout\Frontend\Hooks;

defined( 'ABSPATH' ) || exit;

final class CheckoutEntryFrontendHooks {

	public static function register(): void {
		\MP\CustomCheckout\Hooks\CheckoutEntryFrontendHooks::register();
	}
}
