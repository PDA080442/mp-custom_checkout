<?php
/**
 * Публичный URL и slug маршрута кастомного checkout.
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout\Routing;

use MP\CustomCheckout\Hooks\CheckoutRouteHooks;
use MP\CustomCheckout\Settings\SafeSettingsResolver;

defined( 'ABSPATH' ) || exit;

/**
 * Class CheckoutRouteConfig
 */
final class CheckoutRouteConfig {

	public const DEFAULT_SLUG = 'mp-checkout';

	/**
	 * Slug пути (сегмент URL без слешей).
	 */
	public static function get_rewrite_slug(): string {
		$slug = SafeSettingsResolver::get( 'general.route_slug', self::DEFAULT_SLUG );
		if ( ! is_string( $slug ) || '' === $slug ) {
			$slug = self::DEFAULT_SLUG;
		}

		$slug = sanitize_title( $slug );
		if ( '' === $slug ) {
			$slug = self::DEFAULT_SLUG;
		}

		return (string) apply_filters( 'mp_custom_checkout_rewrite_slug', $slug );
	}

	/**
	 * Полный URL страницы checkout (ЧПУ или с query-arg при plain permalinks).
	 *
	 * @param array<string, scalar|null> $query_args Дополнительные GET-параметры.
	 */
	public static function get_checkout_url( array $query_args = array() ): string {
		if ( CheckoutPermalinkCompatibility::is_plain_permalinks() ) {
			$url = add_query_arg(
				array_merge(
					array( CheckoutRouteHooks::QUERY_VAR => '1' ),
					$query_args
				),
				home_url( '/' )
			);

			return (string) apply_filters( 'mp_custom_checkout_url', $url, $query_args );
		}

		$path = trailingslashit( self::get_rewrite_slug() );
		$base = trailingslashit( home_url( $path ) );

		if ( ! empty( $query_args ) ) {
			$base = add_query_arg( array_map( 'strval', $query_args ), $base );
		}

		return (string) apply_filters( 'mp_custom_checkout_url', $base, $query_args );
	}

	/**
	 * Относительный путь для ссылок внутри сайта.
	 */
	public static function get_rewrite_slug_for_routing(): string {
		return self::get_rewrite_slug();
	}
}
