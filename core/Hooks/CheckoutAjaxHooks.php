<?php
/**
 * AJAX endpoint'ы checkout (авторизованные и гости).
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout\Hooks;

use MP\CustomCheckout\DependencyFailureGuard;

defined( 'ABSPATH' ) || exit;

/**
 * Class CheckoutAjaxHooks
 */
final class CheckoutAjaxHooks {

	public const ACTION = 'mp_cc_checkout';

	/**
	 * Регистрация wp_ajax_*.
	 */
	public static function register(): void {
		add_action( 'wp_ajax_' . self::ACTION, array( __CLASS__, 'handle' ) );
		add_action( 'wp_ajax_nopriv_' . self::ACTION, array( __CLASS__, 'handle' ) );
	}

	/**
	 * Общая точка входа AJAX (логика будет расширена).
	 */
	public static function handle(): void {
		if ( ! DependencyFailureGuard::is_woocommerce_integration_ready() ) {
			wp_send_json_error(
				array( 'message' => __( 'WooCommerce недоступен.', 'mp-custom-checkout' ) ),
				503
			);
		}

		check_ajax_referer( 'mp_cc_checkout', 'nonce' );

		$sub_action = isset( $_POST['sub_action'] ) ? sanitize_key( wp_unslash( $_POST['sub_action'] ) ) : '';

		/**
		 * Обработка поддействий checkout AJAX (подписки реализуют сценарии).
		 *
		 * @param string $sub_action Поддействие.
		 */
		do_action( 'mp_custom_checkout_ajax_request', $sub_action );

		wp_send_json_success( array( 'sub_action' => $sub_action ) );
	}
}
