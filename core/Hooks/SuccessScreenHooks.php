<?php
/**
 * Ранее: woocommerce_thankyou + do_action( 'mp_custom_checkout_success_screen' ).
 * Событие успеха теперь вызывается на кастомном маршруте ({@see CheckoutSuccessController}).
 *
 * @package MP_Custom_Checkout
 * @deprecated Оставлено для совместимости автозагрузки; регистрация хуков не выполняется.
 */

namespace MP\CustomCheckout\Hooks;

defined( 'ABSPATH' ) || exit;

/**
 * Class SuccessScreenHooks
 */
final class SuccessScreenHooks {

	/**
	 * Не используется — см. CheckoutSuccessController.
	 */
	public static function register(): void {
	}
}
