<?php
/**
 * Legacy compatibility wrapper for entry service.
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout\Routing;

defined( 'ABSPATH' ) || exit;

final class CheckoutEntryService {

	public const SESSION_KEY = \MP\CustomCheckout\Checkout\Routing\CheckoutEntryService::SESSION_KEY;

	public static function register(): void {
		\MP\CustomCheckout\Checkout\Routing\CheckoutEntryService::register();
	}

	public static function __callStatic( string $name, array $arguments ) {
		return \MP\CustomCheckout\Checkout\Routing\CheckoutEntryService::{$name}( ...$arguments );
	}
}
