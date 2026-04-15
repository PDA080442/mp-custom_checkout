<?php
/**
 * Подключение CSS/JS в админке настроек плагина.
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout\Hooks;

defined( 'ABSPATH' ) || exit;

/**
 * Class AdminAssetsHooks
 */
final class AdminAssetsHooks {

	public const PAGE_SLUG = 'mp-custom-checkout';

	public const HANDLE_STYLE  = 'mp-cc-admin';
	public const HANDLE_SCRIPT = 'mp-cc-admin';

	/**
	 * Регистрация admin_enqueue_scripts.
	 */
	public static function register(): void {
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ), 10 );
	}

	/**
	 * Подключение только на странице настроек плагина.
	 *
	 * @param string $hook_suffix Текущий экран.
	 */
	public static function enqueue( string $hook_suffix ): void {
		if ( false === strpos( $hook_suffix, self::PAGE_SLUG ) ) {
			return;
		}

		wp_enqueue_style(
			self::HANDLE_STYLE,
			MP_CUSTOM_CHECKOUT_URL . 'assets/css/admin.css',
			array(),
			MP_CUSTOM_CHECKOUT_VERSION
		);

		wp_enqueue_script(
			self::HANDLE_SCRIPT,
			MP_CUSTOM_CHECKOUT_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			MP_CUSTOM_CHECKOUT_VERSION,
			true
		);

		do_action( 'mp_custom_checkout_enqueue_admin_assets', $hook_suffix );
	}
}
