<?php
/**
 * Маршрутизация кастомного URL checkout (rewrite, query var).
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout\Hooks;

defined( 'ABSPATH' ) || exit;

/**
 * Class CheckoutRouteHooks
 */
final class CheckoutRouteHooks {

	public const QUERY_VAR = 'mpcc_checkout';

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
	 * Правила ЧПУ для страницы checkout.
	 */
	public static function add_rewrite_rules(): void {
		add_rewrite_rule(
			self::REWRITE_SLUG . '/?$',
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
	 * Точка расширения: разбор кастомного checkout (рендер позже).
	 */
	public static function template_redirect(): void {
		if ( ! self::is_checkout_route() ) {
			return;
		}

		do_action( 'mp_custom_checkout_route_dispatch' );
	}

	/**
	 * Текущий запрос — кастомный checkout.
	 */
	public static function is_checkout_route(): bool {
		return 1 === (int) get_query_var( self::QUERY_VAR, 0 );
	}
}
