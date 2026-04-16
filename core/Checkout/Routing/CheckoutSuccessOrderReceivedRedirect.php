<?php
/**
 * Success redirect facade in checkout domain.
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout\Checkout\Routing;

defined( 'ABSPATH' ) || exit;

final class CheckoutSuccessOrderReceivedRedirect {

	public static function register(): void {
		\MP\CustomCheckout\Routing\CheckoutSuccessOrderReceivedRedirect::register();
	}
}
