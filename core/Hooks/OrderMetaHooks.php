<?php
/**
 * Legacy compatibility wrapper for order meta hooks.
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout\Hooks;

defined( 'ABSPATH' ) || exit;

final class OrderMetaHooks {

	public static function register(): void {
		\MP\CustomCheckout\Checkout\Hooks\OrderMetaHooks::register();
	}

	public static function __callStatic( string $name, array $arguments ) {
		return \MP\CustomCheckout\Checkout\Hooks\OrderMetaHooks::{$name}( ...$arguments );
	}
}
