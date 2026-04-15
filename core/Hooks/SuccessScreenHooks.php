<?php
/**
 * Финальный экран после успешной оплаты (thank you).
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout\Hooks;

use MP\CustomCheckout\DependencyFailureGuard;

defined( 'ABSPATH' ) || exit;

/**
 * Class SuccessScreenHooks
 */
final class SuccessScreenHooks {

	/**
	 * Регистрация хуков страницы благодарности.
	 */
	public static function register(): void {
		if ( ! DependencyFailureGuard::is_woocommerce_integration_ready() ) {
			return;
		}

		add_action( 'woocommerce_thankyou', array( __CLASS__, 'on_thankyou' ), 5, 1 );
	}

	/**
	 * @param int $order_id ID заказа.
	 */
	public static function on_thankyou( $order_id ): void {
		$order_id = absint( $order_id );
		if ( $order_id <= 0 ) {
			return;
		}

		/**
		 * Кастомный успешный экран / данные после оплаты.
		 *
		 * @param int $order_id ID заказа.
		 */
		do_action( 'mp_custom_checkout_success_screen', $order_id );
	}
}
