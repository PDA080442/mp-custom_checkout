<?php
/**
 * Legacy compatibility wrapper for email hooks.
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout\Hooks;

defined( 'ABSPATH' ) || exit;

final class EmailHooks {

	public static function register(): void {
		\MP\CustomCheckout\Checkout\Hooks\EmailHooks::register();
	}

	public static function __callStatic( string $name, array $arguments ) {
		return \MP\CustomCheckout\Checkout\Hooks\EmailHooks::{$name}( ...$arguments );
	}
}
