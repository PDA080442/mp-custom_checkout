<?php
/**
 * Route controller facade in checkout domain.
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout\Checkout\Routing;

defined( 'ABSPATH' ) || exit;

final class CheckoutRouteController {

	public static function register(): void {
		\MP\CustomCheckout\Routing\CheckoutRouteController::register();
	}
}
