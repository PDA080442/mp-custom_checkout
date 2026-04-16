<?php
/**
 * Рендер кастомного экрана успеха после оплаты.
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout\Checkout\Routing;

use MP\CustomCheckout\DependencyFailureGuard;
use MP\CustomCheckout\Routing\CheckoutReturnPaths;
use MP\CustomCheckout\Routing\CheckoutSuccessPresenter;

defined( 'ABSPATH' ) || exit;

final class CheckoutSuccessController {

	/**
	 * Подписка на template_redirect.
	 */
	public static function register(): void {
		add_action( 'template_redirect', array( __CLASS__, 'dispatch' ), 6 );
	}

	/**
	 * Вывод шаблона успеха и привязка к статусу заказа / оплате.
	 */
	public static function dispatch(): void {
		if ( ! CheckoutSuccessRouteHooks::is_success_route() ) {
			return;
		}

		if ( ! DependencyFailureGuard::is_woocommerce_integration_ready() ) {
			wp_safe_redirect( home_url( '/' ) );
			exit;
		}

		$order_id = isset( $_GET[ CheckoutSuccessRouteHooks::QUERY_VAR_ORDER_ID ] )
			? absint( wp_unslash( $_GET[ CheckoutSuccessRouteHooks::QUERY_VAR_ORDER_ID ] ) )
			: absint( get_query_var( CheckoutSuccessRouteHooks::QUERY_VAR_ORDER_ID, 0 ) );

		$order_key = isset( $_GET[ CheckoutSuccessRouteHooks::QUERY_VAR_ORDER_KEY ] )
			? sanitize_text_field( wp_unslash( $_GET[ CheckoutSuccessRouteHooks::QUERY_VAR_ORDER_KEY ] ) )
			: sanitize_text_field( (string) get_query_var( CheckoutSuccessRouteHooks::QUERY_VAR_ORDER_KEY, '' ) );

		if ( $order_id <= 0 || '' === $order_key ) {
			do_action( 'mp_custom_checkout_success_route_invalid', $order_id, $order_key );
			wp_safe_redirect( CheckoutReturnPaths::get_shop_url() );
			exit;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order instanceof \WC_Order ) {
			do_action( 'mp_custom_checkout_success_order_not_found', $order_id );
			wp_safe_redirect( CheckoutReturnPaths::get_shop_url() );
			exit;
		}

		if ( ! hash_equals( $order->get_order_key(), $order_key ) ) {
			do_action( 'mp_custom_checkout_success_order_key_mismatch', $order_id );
			wp_safe_redirect( CheckoutReturnPaths::get_shop_url() );
			exit;
		}

		do_action( 'mp_custom_checkout_success_validate_order', $order );

		nocache_headers();
		header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );

		$presenter = CheckoutSuccessPresenter::build( $order );

		do_action( 'mp_custom_checkout_success_screen', $order->get_id(), $order );

		$mp_cc_success_presenter = $presenter;
		$mp_cc_success_order     = $order;

		$template = MP_CUSTOM_CHECKOUT_PATH . 'templates/checkout-success.php';
		if ( ! is_readable( $template ) ) {
			wp_safe_redirect( CheckoutReturnPaths::get_shop_url() );
			exit;
		}

		status_header( 200 );

		include $template;

		exit;
	}
}
