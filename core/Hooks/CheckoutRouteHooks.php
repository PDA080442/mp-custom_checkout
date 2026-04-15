<?php
/**
 * Маршрутизация кастомного URL checkout (rewrite, query var).
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout\Hooks;

use MP\CustomCheckout\Routing\CheckoutRouteConfig;

defined( 'ABSPATH' ) || exit;

/**
 * Class CheckoutRouteHooks
 */
final class CheckoutRouteHooks {

	public const QUERY_VAR = 'mpcc_checkout';

	/**
	 * Дефолтный slug (если не задан в настройках).
	 *
	 * @deprecated Используйте {@see CheckoutRouteConfig::DEFAULT_SLUG}.
	 */
	public const REWRITE_SLUG = 'mp-checkout';

	/**
	 * Регистрация rewrite и фильтров запроса.
	 */
	public static function register(): void {
		add_action( 'init', array( __CLASS__, 'add_rewrite_rules' ), 10 );
		add_filter( 'query_vars', array( __CLASS__, 'register_query_vars' ) );
		add_action( 'template_redirect', array( __CLASS__, 'template_redirect' ), 5 );
	}

	/**
	 * Правила ЧПУ для страницы checkout (slug из настроек / фильтра).
	 */
	public static function add_rewrite_rules(): void {
		$slug = CheckoutRouteConfig::get_rewrite_slug();
		if ( '' === $slug ) {
			return;
		}

		add_rewrite_rule(
			'^' . preg_quote( $slug, '/' ) . '/?$',
			'index.php?' . self::QUERY_VAR . '=1',
			'top'
		);
	}

	/**
	 * @param array<int, string> $vars Query vars.
	 * @return array<int, string>
	 */
	public static function register_query_vars( array $vars ): array {
		$vars[] = self::QUERY_VAR;
		return $vars;
	}

	/**
	 * Точка входа в обработку маршрута.
	 */
	public static function template_redirect(): void {
		if ( ! self::is_checkout_route() ) {
			return;
		}

		do_action( 'mp_custom_checkout_route_dispatch' );
	}

	/**
	 * Текущий запрос — кастомный checkout (ЧПУ или ?mpcc_checkout=1 для plain permalinks).
	 */
	public static function is_checkout_route(): bool {
		if ( isset( $_GET[ self::QUERY_VAR ] ) ) {
			$raw = wp_unslash( $_GET[ self::QUERY_VAR ] );
			return '1' === (string) $raw;
		}

		return 1 === (int) get_query_var( self::QUERY_VAR, 0 );
	}
}
