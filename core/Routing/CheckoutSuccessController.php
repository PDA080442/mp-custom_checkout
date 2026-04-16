<?php
/**
 * Legacy compatibility wrapper for success controller.
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout\Routing;

defined( 'ABSPATH' ) || exit;

final class CheckoutSuccessController {

	public static function register(): void {
		\MP\CustomCheckout\Checkout\Routing\CheckoutSuccessController::register();
	}

	public static function __callStatic( string $name, array $arguments ) {
		return \MP\CustomCheckout\Checkout\Routing\CheckoutSuccessController::{$name}( ...$arguments );
	}
}
