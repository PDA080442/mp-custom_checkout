<?php
/**
 * Маршрутизация кастомного URL checkout (rewrite, query var).
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout\Hooks;

use MP\CustomCheckout\DependencyFailureGuard;
use MP\CustomCheckout\Routing\CheckoutRouteConfig;
use MP\CustomCheckout\Settings\DefaultFeatureFlagsRegistry;
use MP\CustomCheckout\Settings\FeatureFlagResolver;

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
		// В админке WooCommerce (в т.ч. экран Платежи) роутинг checkout не должен вмешиваться вообще.
		if ( function_exists( 'is_admin' ) && is_admin() && ! wp_doing_ajax() ) {
			return;
		}
		add_action( 'init', array( __CLASS__, 'add_rewrite_rules' ), 10 );
		add_filter( 'query_vars', array( __CLASS__, 'register_query_vars' ) );
		add_filter( 'woocommerce_get_checkout_url', array( __CLASS__, 'filter_woocommerce_checkout_url' ), 20, 2 );
		// Последним в цепочке: иначе другие плагины снова выставляют true и тема/WC тянут нативный form-checkout поверх SPA.
		add_filter( 'woocommerce_is_checkout', array( __CLASS__, 'filter_woocommerce_is_checkout' ), 99999, 1 );
		add_action( 'wp', array( __CLASS__, 'setup_virtual_checkout_main_query' ), 99 );
		add_filter( 'document_title_parts', array( __CLASS__, 'filter_document_title_parts' ), 20, 1 );
		add_filter( 'body_class', array( __CLASS__, 'filter_body_class' ), 99999, 1 );
		add_action( 'template_redirect', array( __CLASS__, 'early_load_cart' ), 1 );
		add_action( 'template_redirect', array( __CLASS__, 'template_redirect' ), 5 );
	}

	/**
	 * Поднять сессию корзины до инициализации flow и до любых выводов шаблона.
	 */
	public static function early_load_cart(): void {
		if ( ! self::is_checkout_route() ) {
			return;
		}
		if ( ! DependencyFailureGuard::is_woocommerce_integration_ready() ) {
			return;
		}
		if ( ! function_exists( 'wc_load_cart' ) ) {
			return;
		}
		wc_load_cart();
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

		if ( ! FeatureFlagResolver::is_enabled( DefaultFeatureFlagsRegistry::FLAG_CUSTOM_CHECKOUT_ROUTE, true ) ) {
			$url = function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : home_url( '/' );
			wp_safe_redirect( $url );
			exit;
		}

		do_action( 'mp_custom_checkout_route_dispatch' );
	}

	/**
	 * Все ссылки WooCommerce на оформление заказа (корзина, виджеты и т.д.) ведут на URL кастомного checkout.
	 *
	 * @param string       $checkout_url URL из WooCommerce.
	 * @param string|false $endpoint     Endpoint (order-pay и т.д.) — оставляем стандартный URL.
	 */
	public static function filter_woocommerce_checkout_url( $checkout_url, $endpoint = '' ) {
		if ( is_admin() && ! wp_doing_ajax() ) {
			return $checkout_url;
		}
		if ( ! FeatureFlagResolver::is_enabled( DefaultFeatureFlagsRegistry::FLAG_CUSTOM_CHECKOUT_ROUTE, true ) ) {
			return $checkout_url;
		}
		if ( ! DependencyFailureGuard::is_woocommerce_integration_ready() ) {
			return $checkout_url;
		}
		if ( is_string( $endpoint ) && '' !== $endpoint ) {
			return $checkout_url;
		}

		return CheckoutRouteConfig::get_checkout_url();
	}

	/**
	 * На URL кастомного checkout нельзя считать запрос «нативной» страницей WC: при is_checkout() === true тема и WC
	 * выводят полный form-checkout.php (все поля и шаги на одной странице) поверх нашего SPA — визуально «4 шага в одном».
	 * Корзина и сессия поднимаются через wc_load_cart() и AJAX плагина; для стандартной страницы checkout в админке WC фильтр не трогаем.
	 *
	 * @param bool $is_checkout Значение из WooCommerce.
	 */
	public static function filter_woocommerce_is_checkout( $is_checkout ): bool {
		if ( is_admin() && ! wp_doing_ajax() ) {
			return (bool) $is_checkout;
		}
		if ( self::is_checkout_route() ) {
			return false;
		}

		return (bool) $is_checkout;
	}

	/**
	 * Виртуальный rewrite без страницы в БД: иначе главный запрос часто как «лента», тема рисует blog/archive.
	 */
	public static function setup_virtual_checkout_main_query(): void {
		if ( ! function_exists( 'is_admin' ) || is_admin() ) {
			return;
		}
		if ( ! self::is_checkout_route() ) {
			return;
		}

		global $wp_query;
		if ( ! $wp_query instanceof \WP_Query ) {
			return;
		}

		$wp_query->is_home            = false;
		$wp_query->is_front_page      = false;
		$wp_query->is_posts_page      = false;
		$wp_query->is_archive         = false;
		$wp_query->is_post_type_archive = false;
		$wp_query->is_category        = false;
		$wp_query->is_tag             = false;
		$wp_query->is_tax             = false;
		$wp_query->is_author          = false;
		$wp_query->is_date            = false;
		$wp_query->is_search          = false;
		$wp_query->is_404             = false;
		$wp_query->is_page            = true;
		$wp_query->is_singular        = true;
		$wp_query->is_single          = false;
	}

	/**
	 * @param array<string, string> $parts
	 * @return array<string, string>
	 */
	public static function filter_document_title_parts( array $parts ): array {
		if ( ! self::is_checkout_route() ) {
			return $parts;
		}
		$parts['title'] = __( 'Оформление заказа', 'mp-custom-checkout' );
		return $parts;
	}

	/**
	 * @param array<int, string> $classes
	 * @return array<int, string>
	 */
	public static function filter_body_class( array $classes ): array {
		if ( ! self::is_checkout_route() ) {
			return $classes;
		}
		$classes = array_values( array_diff( $classes, array( 'woocommerce-checkout', 'woocommerce-page' ) ) );
		$classes[] = 'mp-custom-checkout';
		return array_values( array_unique( array_filter( $classes ) ) );
	}

	/**
	 * Текущий запрос — кастомный checkout (ЧПУ или ?mpcc_checkout=1 для plain permalinks).
	 */
	public static function is_checkout_route(): bool {
		if ( function_exists( 'is_admin' ) && is_admin() && ! wp_doing_ajax() ) {
			return false;
		}
		if ( isset( $_GET[ self::QUERY_VAR ] ) ) {
			$raw = wp_unslash( $_GET[ self::QUERY_VAR ] );
			return '1' === (string) $raw;
		}

		if ( 1 === (int) get_query_var( self::QUERY_VAR, 0 ) ) {
			return true;
		}

		if ( ! FeatureFlagResolver::is_enabled( DefaultFeatureFlagsRegistry::FLAG_CUSTOM_CHECKOUT_ROUTE, true ) ) {
			return false;
		}

		$slug = CheckoutRouteConfig::get_rewrite_slug();
		if ( '' !== $slug && function_exists( 'is_page' ) && is_page( $slug ) ) {
			return true;
		}

		return false;
	}
}
