<?php
/**
 * CSS/JS экрана успеха (маршрут после оплаты).
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout\Frontend\Hooks;

use MP\CustomCheckout\DependencyFailureGuard;

defined( 'ABSPATH' ) || exit;

final class CheckoutSuccessFrontendHooks {
	public const HANDLE_STYLE  = 'mp-cc-checkout-success';
	public const HANDLE_SCRIPT = 'mp-cc-checkout-success';

	public static function register(): void {
		add_action( 'wp', array( __CLASS__, 'maybe_enqueue' ), 10 );
		add_filter( 'body_class', array( __CLASS__, 'body_class' ), 10, 1 );
	}

	public static function maybe_enqueue(): void {
		if ( ! \MP\CustomCheckout\Checkout\Routing\CheckoutSuccessRouteHooks::is_success_route() ) {
			return;
		}
		if ( ! DependencyFailureGuard::is_woocommerce_integration_ready() ) {
			return;
		}
		wp_enqueue_style( self::HANDLE_STYLE, MP_CUSTOM_CHECKOUT_URL . 'assets/css/checkout-success.css', array(), MP_CUSTOM_CHECKOUT_VERSION );
		wp_enqueue_script( self::HANDLE_SCRIPT, MP_CUSTOM_CHECKOUT_URL . 'assets/js/checkout-success.js', array(), MP_CUSTOM_CHECKOUT_VERSION, true );
	}

	public static function body_class( array $classes ): array {
		if ( ! \MP\CustomCheckout\Checkout\Routing\CheckoutSuccessRouteHooks::is_success_route() ) {
			return $classes;
		}
		$classes[] = 'mp-cc-success-page';
		return $classes;
	}
}
