<?php
/**
 * AJAX: выдача права входа на checkout (sticky-корзина / «Перейти к оформлению»).
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout\Checkout\Hooks;

use MP\CustomCheckout\DependencyFailureGuard;
use MP\CustomCheckout\Checkout\Routing\CheckoutEntryService;
use MP\CustomCheckout\Routing\CheckoutRouteConfig;

defined( 'ABSPATH' ) || exit;

final class CheckoutEntryAjaxHooks {

	public const ACTION = 'mp_cc_set_checkout_entry';

	public static function register(): void {
		add_action( 'wp_ajax_' . self::ACTION, array( __CLASS__, 'handle' ) );
		add_action( 'wp_ajax_nopriv_' . self::ACTION, array( __CLASS__, 'handle' ) );
	}

	public static function handle(): void {
		if ( ! DependencyFailureGuard::is_woocommerce_integration_ready() ) {
			wp_send_json_error( array( 'message' => __( 'WooCommerce недоступен.', 'mp-custom-checkout' ) ), 503 );
		}
		if ( ! CheckoutEntryService::validate_ajax_nonce() ) {
			wp_send_json_error( array( 'code' => 'invalid_nonce', 'message' => __( 'Неверный запрос.', 'mp-custom-checkout' ) ), 403 );
		}
		if ( ! function_exists( 'WC' ) || ! WC()->cart || WC()->cart->is_empty() ) {
			wp_send_json_error( array( 'code' => 'empty_cart', 'message' => __( 'Корзина пуста.', 'mp-custom-checkout' ) ), 400 );
		}
		$allowed = apply_filters( 'mp_custom_checkout_validate_entry_source', true );
		if ( is_wp_error( $allowed ) ) {
			wp_send_json_error(
				array(
					'code'    => $allowed->get_error_code(),
					'message' => $allowed->get_error_message(),
				),
				400
			);
		}
		$result = CheckoutEntryService::grant_entry_eligibility();
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'code' => $result->get_error_code(), 'message' => $result->get_error_message() ), 500 );
		}
		$checkout_url = CheckoutRouteConfig::get_checkout_url();
		wp_send_json_success( array( 'checkout_url' => $checkout_url ) );
	}
}
