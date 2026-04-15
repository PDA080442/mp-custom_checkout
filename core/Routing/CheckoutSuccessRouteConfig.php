<?php
/**
 * URL страницы успешного заказа.
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout\Routing;

defined( 'ABSPATH' ) || exit;

/**
 * Class CheckoutSuccessRouteConfig
 */
final class CheckoutSuccessRouteConfig {

	/**
	 * Полный URL экрана успеха с параметрами заказа.
	 *
	 * @param int    $order_id  ID заказа.
	 * @param string $order_key Ключ заказа WooCommerce.
	 */
	public static function get_success_url( int $order_id, string $order_key ): string {
		$order_id = max( 0, $order_id );
		$key      = sanitize_text_field( $order_key );

		$args = array(
			CheckoutSuccessRouteHooks::QUERY_VAR_SUCCESS   => '1',
			CheckoutSuccessRouteHooks::QUERY_VAR_ORDER_ID  => (string) $order_id,
			CheckoutSuccessRouteHooks::QUERY_VAR_ORDER_KEY => $key,
		);

		if ( CheckoutPermalinkCompatibility::is_plain_permalinks() ) {
			$url = add_query_arg( $args, home_url( '/' ) );
			return (string) apply_filters( 'mp_custom_checkout_success_url', $url, $order_id, $key );
		}

		$path = trailingslashit( CheckoutSuccessRouteHooks::get_rewrite_slug() );
		$url  = trailingslashit( home_url( $path ) );
		$url  = add_query_arg( $args, $url );

		return (string) apply_filters( 'mp_custom_checkout_success_url', $url, $order_id, $key );
	}
}
