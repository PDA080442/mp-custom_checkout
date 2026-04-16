<?php
/**
 * Legacy compatibility wrapper for permalink compatibility.
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout\Routing;

defined( 'ABSPATH' ) || exit;

final class CheckoutPermalinkCompatibility {

	public static function register(): void {
		\MP\CustomCheckout\Checkout\Routing\CheckoutPermalinkCompatibility::register();
	}

	public static function __callStatic( string $name, array $arguments ) {
		return \MP\CustomCheckout\Checkout\Routing\CheckoutPermalinkCompatibility::{$name}( ...$arguments );
	}
}
