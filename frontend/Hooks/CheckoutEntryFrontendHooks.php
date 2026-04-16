<?php
/**
 * Фронтенд: скрипт привязки перехода к оформлению из sticky-корзины.
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout\Frontend\Hooks;

use MP\CustomCheckout\DependencyFailureGuard;
use MP\CustomCheckout\Routing\CheckoutRouteConfig;

defined( 'ABSPATH' ) || exit;

final class CheckoutEntryFrontendHooks {
	public const HANDLE_SCRIPT = 'mp-cc-checkout-entry';

	public static function register(): void {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ), 25 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'bind_sticky_cart_hook' ), 99 );
	}

	public static function enqueue(): void {
		if ( is_admin() ) {
			return;
		}
		if ( ! apply_filters( 'mp_custom_checkout_enqueue_entry_script', true ) ) {
			return;
		}
		if ( ! DependencyFailureGuard::is_woocommerce_integration_ready() ) {
			return;
		}
		wp_register_script( self::HANDLE_SCRIPT, MP_CUSTOM_CHECKOUT_URL . 'assets/js/checkout-entry.js', array( 'jquery' ), MP_CUSTOM_CHECKOUT_VERSION, true );
		wp_enqueue_script( self::HANDLE_SCRIPT );
		wp_localize_script(
			self::HANDLE_SCRIPT,
			'mpCcCheckoutEntry',
			array(
				'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
				'action'       => \MP\CustomCheckout\Checkout\Hooks\CheckoutEntryAjaxHooks::ACTION,
				'nonce'        => wp_create_nonce( 'mp_cc_checkout_entry' ),
				'checkoutUrl'  => CheckoutRouteConfig::get_checkout_url(),
				'i18n'         => array(
					'emptyCart' => __( 'Корзина пуста. Добавьте товары.', 'mp-custom-checkout' ),
					'error'     => __( 'Не удалось подготовить оформление заказа.', 'mp-custom-checkout' ),
				),
			)
		);
		do_action( 'mp_custom_checkout_bind_sticky_cart', self::HANDLE_SCRIPT );
	}

	public static function bind_sticky_cart_hook(): void {
		if ( ! DependencyFailureGuard::is_woocommerce_integration_ready() ) {
			return;
		}
		do_action( 'mp_custom_checkout_sticky_cart_ready' );
	}
}
