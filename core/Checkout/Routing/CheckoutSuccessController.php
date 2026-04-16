<?php
/**
 * Success controller facade in checkout domain.
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout\Checkout\Routing;

defined( 'ABSPATH' ) || exit;

final class CheckoutSuccessController {

	public static function register(): void {
		\MP\CustomCheckout\Routing\CheckoutSuccessController::register();
	}
}
