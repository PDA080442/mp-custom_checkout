<?php
/**
 * Legacy compatibility wrapper for success route hooks.
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout\Routing;

defined( 'ABSPATH' ) || exit;

final class CheckoutSuccessRouteHooks {

	public const QUERY_VAR_SUCCESS = \MP\CustomCheckout\Checkout\Routing\CheckoutSuccessRouteHooks::QUERY_VAR_SUCCESS;
	public const QUERY_VAR_ORDER_ID = \MP\CustomCheckout\Checkout\Routing\CheckoutSuccessRouteHooks::QUERY_VAR_ORDER_ID;
	public const QUERY_VAR_ORDER_KEY = \MP\CustomCheckout\Checkout\Routing\CheckoutSuccessRouteHooks::QUERY_VAR_ORDER_KEY;
	public const DEFAULT_SLUG = \MP\CustomCheckout\Checkout\Routing\CheckoutSuccessRouteHooks::DEFAULT_SLUG;

	public static function register(): void {
		\MP\CustomCheckout\Checkout\Routing\CheckoutSuccessRouteHooks::register();
	}

	public static function __callStatic( string $name, array $arguments ) {
		return \MP\CustomCheckout\Checkout\Routing\CheckoutSuccessRouteHooks::{$name}( ...$arguments );
	}
}
