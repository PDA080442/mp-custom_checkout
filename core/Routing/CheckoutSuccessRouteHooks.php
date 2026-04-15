<?php
/**
 * Маршрут страницы успешного заказа (после оплаты).
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout\Routing;

use MP\CustomCheckout\Settings\SafeSettingsResolver;

defined( 'ABSPATH' ) || exit;

/**
 * Class CheckoutSuccessRouteHooks
 */
final class CheckoutSuccessRouteHooks {

	public const QUERY_VAR_SUCCESS = 'mpcc_success';

	public const QUERY_VAR_ORDER_ID = 'mp_cc_order_id';

	public const QUERY_VAR_ORDER_KEY = 'mp_cc_order_key';

	public const DEFAULT_SLUG = 'mp-checkout-success';

	/**
	 * Регистрация rewrite и query vars.
	 */
	public static function register(): void {
		add_action( 'init', array( __CLASS__, 'add_rewrite_rules' ), 11 );
		add_filter( 'query_vars', array( __CLASS__, 'register_query_vars' ) );
	}

	/**
	 * Правила ЧПУ для страницы успеха.
	 */
	public static function add_rewrite_rules(): void {
		$slug = self::get_rewrite_slug();
		if ( '' === $slug ) {
			return;
		}

		add_rewrite_rule(
			'^' . preg_quote( $slug, '/' ) . '/?$',
			'index.php?' . self::QUERY_VAR_SUCCESS . '=1',
			'top'
		);
	}

	/**
	 * @param array<int, string> $vars Query vars.
	 * @return array<int, string>
	 */
	public static function register_query_vars( array $vars ): array {
		$vars[] = self::QUERY_VAR_SUCCESS;
		$vars[] = self::QUERY_VAR_ORDER_ID;
		$vars[] = self::QUERY_VAR_ORDER_KEY;
		return $vars;
	}

	/**
	 * Slug сегмента URL.
	 */
	public static function get_rewrite_slug(): string {
		$slug = SafeSettingsResolver::get( 'general.success_route_slug', self::DEFAULT_SLUG );
		if ( ! is_string( $slug ) || '' === $slug ) {
			$slug = self::DEFAULT_SLUG;
		}

		$slug = sanitize_title( $slug );
		if ( '' === $slug ) {
			$slug = self::DEFAULT_SLUG;
		}

		return (string) apply_filters( 'mp_custom_checkout_success_rewrite_slug', $slug );
	}

	/**
	 * Текущий запрос — страница успеха.
	 */
	public static function is_success_route(): bool {
		if ( isset( $_GET[ self::QUERY_VAR_SUCCESS ] ) ) {
			return '1' === (string) wp_unslash( $_GET[ self::QUERY_VAR_SUCCESS ] );
		}

		return 1 === (int) get_query_var( self::QUERY_VAR_SUCCESS, 0 );
	}
}
