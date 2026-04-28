<?php
/**
 * Проверка наличия WooCommerce и runtime API (checkout, cart, session).
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout;

defined( 'ABSPATH' ) || exit;

/**
 * Class WooCommerceDependencyValidator
 */
final class WooCommerceDependencyValidator {

	/**
	 * Путь к основному файлу плагина WooCommerce относительно wp-content/plugins.
	 */
	private const WOOCOMMERCE_PLUGIN_FILE = 'woocommerce/woocommerce.php';

	/**
	 * Активен ли плагин WooCommerce в single-site / текущем сайте сети.
	 */
	public static function is_woocommerce_plugin_active(): bool {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		if ( is_multisite() && function_exists( 'is_plugin_active_for_network' ) && is_plugin_active_for_network( self::WOOCOMMERCE_PLUGIN_FILE ) ) {
			return true;
		}

		return is_plugin_active( self::WOOCOMMERCE_PLUGIN_FILE );
	}

	/**
	 * Доступен ли объект WooCommerce и базовые API после загрузки WC.
	 * Вызывать не раньше хука {@see 'woocommerce_loaded'}.
	 */
	public static function is_woocommerce_runtime_ready(): bool {
		if ( ! function_exists( 'WC' ) ) {
			return false;
		}

		$wc = WC();
		if ( ! $wc ) {
			return false;
		}
		/*
		 * ВАЖНО: не трогаем checkout()/payment_gateways() на ранней стадии woocommerce_loaded.
		 * Ранний вызов payment_gateways() может зафиксировать неполный список шлюзов
		 * (часть плагинов регистрирует gateways позже), что ломает экран WooCommerce → Платежи.
		 */
		return true;
	}

	/**
	 * Совпадает с {@see \WC::is_request()} для типа {@code frontend}: только в этом случае
	 * в {@see \WC::init()} вызывается {@see wc_load_cart()} и поднимаются session + cart.
	 * В обычном wp-admin и при REST без фронта WooCommerce намеренно не инициализирует их.
	 */
	private static function is_wc_loading_cart_and_session_this_request(): bool {
		if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
			return false;
		}

		$wc = function_exists( 'WC' ) ? WC() : null;
		if ( ! $wc ) {
			return false;
		}

		$like_frontend = ( ! is_admin() || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) );
		if ( ! $like_frontend ) {
			return false;
		}

		if ( method_exists( $wc, 'is_rest_api_request' ) && $wc->is_rest_api_request() ) {
			return false;
		}

		return true;
	}

	/**
	 * Проверка cart и session API (после полной инициализации WooCommerce на запросе).
	 * Вызывать не раньше хука {@see 'woocommerce_init'}.
	 */
	public static function is_cart_and_session_ready(): bool {
		if ( ! function_exists( 'WC' ) || ! WC() ) {
			return false;
		}

		if ( ! self::is_wc_loading_cart_and_session_this_request() ) {
			return true;
		}

		$cart = WC()->cart;
		if ( ! $cart instanceof \WC_Cart ) {
			return false;
		}

		$session = WC()->session;
		if ( ! $session instanceof \WC_Session ) {
			return false;
		}

		return true;
	}
}
