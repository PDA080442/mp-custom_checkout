<?php
/**
 * Вывод данных checkout в письмах WooCommerce.
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout\Hooks;

use MP\CustomCheckout\DependencyFailureGuard;

defined( 'ABSPATH' ) || exit;

/**
 * Class EmailHooks
 */
final class EmailHooks {

	/**
	 * Регистрация хуков шаблонов email.
	 */
	public static function register(): void {
		if ( ! DependencyFailureGuard::is_woocommerce_integration_ready() ) {
			return;
		}

		add_action( 'woocommerce_email_order_meta', array( __CLASS__, 'render_email_order_meta' ), 10, 4 );
	}

	/**
	 * @param \WC_Order|false $order           Заказ.
	 * @param bool              $sent_to_admin Админу.
	 * @param bool              $plain_text      Текстовое письмо.
	 * @param \WC_Email|false   $email           Объект письма.
	 */
	public static function render_email_order_meta( $order, $sent_to_admin, $plain_text, $email = null ): void {
		if ( ! $order instanceof \WC_Order ) {
			return;
		}

		/**
		 * Вывод дополнительных полей checkout в письме.
		 *
		 * @param \WC_Order  $order           Заказ.
		 * @param bool       $sent_to_admin Админу.
		 * @param bool       $plain_text      Текстовое письмо.
		 * @param \WC_Email  $email           Письмо.
		 */
		do_action( 'mp_custom_checkout_email_order_meta', $order, $sent_to_admin, $plain_text, $email );
	}
}
