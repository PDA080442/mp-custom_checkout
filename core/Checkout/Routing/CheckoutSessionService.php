<?php
/**
 * Session service facade in checkout domain.
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout\Checkout\Routing;

defined( 'ABSPATH' ) || exit;

final class CheckoutSessionService {

	public static function register(): void {
		\MP\CustomCheckout\Routing\CheckoutSessionService::register();
	}
}
