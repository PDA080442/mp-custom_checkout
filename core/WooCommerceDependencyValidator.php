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

		$checkout = $wc->checkout();
		if ( ! $checkout instanceof \WC_Checkout ) {
			return false;
		}

		$gateways = $wc->payment_gateways();
		if ( ! $gateways instanceof \WC_Payment_Gateways ) {
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
