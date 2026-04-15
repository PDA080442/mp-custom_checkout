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
		add_action( 'mp_custom_checkout_email_order_meta', array( __CLASS__, 'render_scenario_meta' ), 10, 4 );
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

	/**
	 * Вывод выбранного сценария в email заказа.
	 *
	 * @param \WC_Order $order Заказ.
	 * @param bool      $sent_to_admin Админу.
	 * @param bool      $plain_text Текстовый формат.
	 * @param \WC_Email|false $email Письмо.
	 */
	public static function render_scenario_meta( $order, $sent_to_admin, $plain_text, $email = null ): void {
		unset( $sent_to_admin, $email );
		if ( ! $order instanceof \WC_Order ) {
			return;
		}

		$scenario_label = (string) $order->get_meta( '_mp_cc_scenario_label', true );
		if ( '' === $scenario_label ) {
			$scenario_id    = (string) $order->get_meta( '_mp_cc_scenario_id', true );
			$scenario_label = '' !== $scenario_id ? $scenario_id : '';
		}

		if ( '' === $scenario_label ) {
			return;
		}

		$title = __( 'Сценарий получения', 'mp-custom-checkout' );

		if ( $plain_text ) {
			echo "\n" . sanitize_text_field( $title ) . ': ' . sanitize_text_field( $scenario_label ) . "\n";
			return;
		}

		echo '<p><strong>' . esc_html( $title ) . ':</strong> ' . esc_html( $scenario_label ) . '</p>';
	}
}
