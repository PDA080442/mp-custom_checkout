<?php
/**
 * Ранний graceful-fail при отсутствии критичных зависимостей WooCommerce.
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout;

defined( 'ABSPATH' ) || exit;

/**
 * Class DependencyFailureGuard
 */
final class DependencyFailureGuard {

	/**
	 * @var bool|null null — ещё не проверяли, true — можно подключать checkout, false — нет.
	 */
	private static $woocommerce_ready = null;

	/**
	 * Регистрация проверок и уведомлений.
	 */
	public static function boot(): void {
		add_action( 'plugins_loaded', array( __CLASS__, 'on_plugins_loaded' ), 5 );
		add_action( 'woocommerce_loaded', array( __CLASS__, 'on_woocommerce_loaded' ), 5 );
		add_action( 'woocommerce_init', array( __CLASS__, 'on_woocommerce_init' ), 20 );
	}

	/**
	 * @return bool Можно ли безопасно использовать интеграции WooCommerce.
	 */
	public static function is_woocommerce_integration_ready(): bool {
		return true === self::$woocommerce_ready;
	}

	/**
	 * plugins_loaded: плагин WooCommerce должен быть активирован.
	 */
	public static function on_plugins_loaded(): void {
		if ( WooCommerceDependencyValidator::is_woocommerce_plugin_active() ) {
			return;
		}

		self::$woocommerce_ready = false;
		add_action( 'admin_notices', array( __CLASS__, 'render_woocommerce_inactive_notice' ) );
	}

	/**
	 * woocommerce_loaded: WC(), checkout, payment gateways.
	 */
	public static function on_woocommerce_loaded(): void {
		if ( false === self::$woocommerce_ready ) {
			return;
		}

		if ( ! WooCommerceDependencyValidator::is_woocommerce_runtime_ready() ) {
			self::$woocommerce_ready = false;
			add_action( 'admin_notices', array( __CLASS__, 'render_woocommerce_runtime_notice' ) );
		}
	}

	/**
	 * woocommerce_init: cart и session.
	 */
	public static function on_woocommerce_init(): void {
		if ( false === self::$woocommerce_ready ) {
			return;
		}

		if ( ! WooCommerceDependencyValidator::is_cart_and_session_ready() ) {
			self::$woocommerce_ready = false;
			add_action( 'admin_notices', array( __CLASS__, 'render_woocommerce_cart_session_notice' ) );
			return;
		}

		self::$woocommerce_ready = true;
	}

	/**
	 * Админка: WooCommerce не активирован.
	 */
	public static function render_woocommerce_inactive_notice(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		echo '<div class="notice notice-error"><p>';
		echo esc_html(
			__( 'MP Custom Checkout требует активированный плагин WooCommerce.', 'mp-custom-checkout' )
		);
		echo '</p></div>';
	}

	/**
	 * Админка: не удалось получить checkout / payment gateways.
	 */
	public static function render_woocommerce_runtime_notice(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) {
			return;
		}

		echo '<div class="notice notice-error"><p>';
		echo esc_html(
			__( 'MP Custom Checkout: среда WooCommerce неполная (checkout или платёжные шлюзы недоступны).', 'mp-custom-checkout' )
		);
		echo '</p></div>';
	}

	/**
	 * Админка: нет cart или session.
	 */
	public static function render_woocommerce_cart_session_notice(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) {
			return;
		}

		echo '<div class="notice notice-error"><p>';
		echo esc_html(
			__( 'MP Custom Checkout: корзина или сессия WooCommerce недоступны.', 'mp-custom-checkout' )
		);
		echo '</p></div>';
	}
}
