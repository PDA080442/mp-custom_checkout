<?php
/**
 * Legacy compatibility wrapper for checkout entry AJAX hooks.
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout\Hooks;

defined( 'ABSPATH' ) || exit;

final class CheckoutEntryAjaxHooks {

	public const ACTION = \MP\CustomCheckout\Checkout\Hooks\CheckoutEntryAjaxHooks::ACTION;

	public static function register(): void {
		\MP\CustomCheckout\Checkout\Hooks\CheckoutEntryAjaxHooks::register();
	}

	public static function __callStatic( string $name, array $arguments ) {
		return \MP\CustomCheckout\Checkout\Hooks\CheckoutEntryAjaxHooks::{$name}( ...$arguments );
	}
}
