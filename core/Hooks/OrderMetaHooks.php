<?php
/**
 * Сохранение кастомных данных заказа (order meta).
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout\Hooks;

use MP\CustomCheckout\DependencyFailureGuard;

defined( 'ABSPATH' ) || exit;

/**
 * Class OrderMetaHooks
 */
final class OrderMetaHooks {

	/**
	 * Регистрация хуков WooCommerce для записи meta заказа.
	 */
	public static function register(): void {
		if ( ! DependencyFailureGuard::is_woocommerce_integration_ready() ) {
			return;
		}

		add_action( 'woocommerce_checkout_order_created', array( __CLASS__, 'on_checkout_order_created' ), 10, 2 );
	}

	/**
	 * @param \WC_Order $order Объект заказа.
	 * @param array     $data  Данные checkout.
	 */
	public static function on_checkout_order_created( $order, $data = array() ): void {
		if ( ! $order instanceof \WC_Order ) {
			return;
		}

		/**
		 * Сохранение кастомных полей checkout в заказ.
		 *
		 * @param \WC_Order $order Объект заказа.
		 * @param array     $data  Данные формы checkout.
		 */
		do_action( 'mp_custom_checkout_save_order_meta', $order, $data );
	}
}
