<?php
/**
 * Флаг сессии WooCommerce: допустимый вход на кастомный checkout (из sticky-корзины).
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout\Checkout\Routing;

use MP\CustomCheckout\DependencyFailureGuard;

defined( 'ABSPATH' ) || exit;

final class CheckoutEntryService {

	public const SESSION_KEY = 'mp_cc_checkout_entry';

	public static function register(): void {
		add_action( 'woocommerce_thankyou', array( __CLASS__, 'revoke_after_order' ), 5, 1 );
	}

	public static function has_entry_eligibility(): bool {
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			return false;
		}

		$val = WC()->session->get( self::SESSION_KEY, false );
		if ( false === $val || '' === $val ) {
			return false;
		}

		if ( true === $val || 'yes' === $val || 1 === $val || '1' === $val ) {
			return true;
		}

		if ( is_numeric( $val ) && (int) $val > 0 ) {
			return true;
		}

		return false;
	}

	/**
	 * @return true|\WP_Error
	 */
	public static function grant_entry_eligibility() {
		if ( ! DependencyFailureGuard::is_woocommerce_integration_ready() ) {
			return new \WP_Error( 'mp_cc_no_session', __( 'Сессия WooCommerce недоступна.', 'mp-custom-checkout' ) );
		}

		if ( ! WC()->session ) {
			return new \WP_Error( 'mp_cc_no_session', __( 'Сессия WooCommerce недоступна.', 'mp-custom-checkout' ) );
		}

		WC()->session->set( self::SESSION_KEY, time() );

		do_action( 'mp_custom_checkout_entry_eligibility_granted' );

		return true;
	}

	public static function revoke_entry_eligibility(): void {
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			return;
		}

		WC()->session->set( self::SESSION_KEY, false );

		do_action( 'mp_custom_checkout_entry_eligibility_revoked' );
	}

	public static function revoke_after_order( $order_id ): void {
		unset( $order_id );
		self::revoke_entry_eligibility();
	}

	public static function validate_ajax_nonce(): bool {
		return ! empty( $_POST['nonce'] )
			&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'mp_cc_checkout_entry' );
	}
}
