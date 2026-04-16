<?php
/**
 * Legacy compatibility wrapper for checkout ajax hooks.
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout\Hooks;

defined( 'ABSPATH' ) || exit;

final class CheckoutAjaxHooks {

	public const ACTION = \MP\CustomCheckout\Checkout\Hooks\CheckoutAjaxHooks::ACTION;

	public static function register(): void {
		\MP\CustomCheckout\Checkout\Hooks\CheckoutAjaxHooks::register();
	}

	public static function __callStatic( string $name, array $arguments ) {
		return \MP\CustomCheckout\Checkout\Hooks\CheckoutAjaxHooks::{$name}( ...$arguments );
	}
}
