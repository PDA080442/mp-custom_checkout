<?php
/**
 * Legacy compatibility wrapper for checkout route controller.
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout\Routing;

defined( 'ABSPATH' ) || exit;

final class CheckoutRouteController {

	public static function register(): void {
		\MP\CustomCheckout\Checkout\Routing\CheckoutRouteController::register();
	}

	public static function __callStatic( string $name, array $arguments ) {
		return \MP\CustomCheckout\Checkout\Routing\CheckoutRouteController::{$name}( ...$arguments );
	}
}
