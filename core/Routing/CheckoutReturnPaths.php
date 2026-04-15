<?php
/**
 * Безопасные URL возврата: магазин, каталог, корзина.
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout\Routing;

defined( 'ABSPATH' ) || exit;

/**
 * Class CheckoutReturnPaths
 */
final class CheckoutReturnPaths {

	/**
	 * URL страницы магазина WooCommerce.
	 */
	public static function get_shop_url(): string {
		if ( function_exists( 'wc_get_page_id' ) ) {
			$shop_id = (int) wc_get_page_id( 'shop' );
			if ( $shop_id > 0 ) {
				$url = get_permalink( $shop_id );
				if ( is_string( $url ) && '' !== $url ) {
					return $url;
				}
			}
		}

		$archive = get_post_type_archive_link( 'product' );
		if ( is_string( $archive ) && '' !== $archive ) {
			return $archive;
		}

		return home_url( '/' );
	}

	/**
	 * URL корзины.
	 */
	public static function get_cart_url(): string {
		if ( function_exists( 'wc_get_cart_url' ) ) {
			return wc_get_cart_url();
		}

		return home_url( '/' );
	}

	/**
	 * URL возврата в зависимости от причины fallback.
	 *
	 * @param array<string, mixed> $context Контекст.
	 */
	public static function get_url_for_reason( string $reason_code, array $context = array() ): string {
		unset( $context );

		$default = self::get_cart_url();
		if ( 'empty_cart' === $reason_code ) {
			$default = self::get_shop_url();
		}

		if ( 'entry_not_allowed' === $reason_code ) {
			$default = self::get_cart_url();
		}

		return (string) apply_filters( 'mp_custom_checkout_return_url', $default, $reason_code, $context );
	}
}
