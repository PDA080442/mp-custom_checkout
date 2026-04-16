<?php
/**
 * Legacy compatibility wrapper for session service.
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout\Routing;

defined( 'ABSPATH' ) || exit;

final class CheckoutSessionService {

	public const SESSION_KEY = \MP\CustomCheckout\Checkout\Routing\CheckoutSessionService::SESSION_KEY;
	public const FLOW_TTL_SECONDS = \MP\CustomCheckout\Checkout\Routing\CheckoutSessionService::FLOW_TTL_SECONDS;

	public static function register(): void {
		\MP\CustomCheckout\Checkout\Routing\CheckoutSessionService::register();
	}

	public static function __callStatic( string $name, array $arguments ) {
		return \MP\CustomCheckout\Checkout\Routing\CheckoutSessionService::{$name}( ...$arguments );
	}
}
