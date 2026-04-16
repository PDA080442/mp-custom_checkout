<?php
/**
 * Success route hooks facade in checkout domain.
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout\Checkout\Routing;

defined( 'ABSPATH' ) || exit;

final class CheckoutSuccessRouteHooks {

	public static function register(): void {
		\MP\CustomCheckout\Routing\CheckoutSuccessRouteHooks::register();
	}
}
