<?php
/**
 * Редирект со стандартного order-received на кастомный success URL.
 * Вызывает woocommerce_thankyou до редиректа для совместимости с WC и плагинами.
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout\Routing;

use MP\CustomCheckout\DependencyFailureGuard;

defined( 'ABSPATH' ) || exit;

/**
 * Class CheckoutSuccessOrderReceivedRedirect
 */
final class CheckoutSuccessOrderReceivedRedirect {

	/**
	 * Регистрация раннего редиректа.
	 */
	public static function register(): void {
		add_action( 'template_redirect', array( __CLASS__, 'maybe_redirect' ), 1 );
	}

	/**
	 * Редирект с endpoint order-received на кастомный экран успеха.
	 */
	public static function maybe_redirect(): void {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}

		if ( ! DependencyFailureGuard::is_woocommerce_integration_ready() ) {
			return;
		}

		if ( CheckoutSuccessRouteHooks::is_success_route() ) {
			return;
		}

		if ( ! function_exists( 'is_wc_endpoint_url' ) || ! is_wc_endpoint_url( 'order-received' ) ) {
			return;
		}

		$order_id = 0;
		if ( function_exists( 'wc_get_order_id_from_query' ) ) {
			$order_id = absint( wc_get_order_id_from_query() );
		}
		if ( $order_id <= 0 ) {
			$order_id = absint( get_query_var( 'order-received' ) );
		}

		if ( $order_id <= 0 ) {
			return;
		}

		$order_key = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : '';
		if ( '' === $order_key ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order instanceof \WC_Order ) {
			return;
		}

		if ( ! hash_equals( $order->get_order_key(), $order_key ) ) {
			return;
		}

		/**
		 * Разрешить редирект на кастомный success (например для отладки gateway).
		 *
		 * @param bool     $allow    Разрешить редирект.
		 * @param \WC_Order $order   Заказ.
		 */
		$allow = apply_filters( 'mp_custom_checkout_redirect_order_received_to_success', true, $order );
		if ( ! $allow ) {
			return;
		}

		/**
		 * Совместимость: стандартный хук thank you до ухода со страницы WC.
		 *
		 * @param int $order_id ID заказа.
		 */
		do_action( 'woocommerce_thankyou', $order_id );

		$url = CheckoutSuccessRouteConfig::get_success_url( $order_id, $order_key );

		wp_safe_redirect( $url );
		exit;
	}
}
