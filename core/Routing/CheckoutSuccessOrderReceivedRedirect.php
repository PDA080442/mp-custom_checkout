<?php
/**
 * Legacy compatibility wrapper for success redirect.
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout\Routing;

defined( 'ABSPATH' ) || exit;

final class CheckoutSuccessOrderReceivedRedirect {

	public static function register(): void {
		\MP\CustomCheckout\Checkout\Routing\CheckoutSuccessOrderReceivedRedirect::register();
	}

	public static function __callStatic( string $name, array $arguments ) {
		return \MP\CustomCheckout\Checkout\Routing\CheckoutSuccessOrderReceivedRedirect::{$name}( ...$arguments );
	}
}
