<?php
/**
 * Сохранение кастомных данных заказа (order meta).
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout\Hooks;

use MP\CustomCheckout\DependencyFailureGuard;
use MP\CustomCheckout\Routing\CheckoutScenarioRules;
use MP\CustomCheckout\Routing\CheckoutSessionService;

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
		add_action( 'mp_custom_checkout_save_order_meta', array( __CLASS__, 'save_scenario_meta' ), 10, 2 );
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

	/**
	 * Сериализация сценария оформления в мета заказа.
	 *
	 * @param \WC_Order $order Заказ.
	 * @param array     $data  Данные checkout.
	 */
	public static function save_scenario_meta( $order, $data = array() ): void {
		unset( $data );
		if ( ! $order instanceof \WC_Order ) {
			return;
		}

		$flow      = CheckoutSessionService::get_flow();
		$scenario  = isset( $flow['scenario'] ) ? (string) $flow['scenario'] : '';
		$scenario  = CheckoutScenarioRules::sanitize_scenario( $scenario );
		$rules     = CheckoutScenarioRules::build( $scenario );

		$serialized = isset( $rules['serialize'] ) && is_array( $rules['serialize'] ) ? $rules['serialize'] : array( 'id' => $scenario );
		$scenario_label = isset( $serialized['label'] ) ? (string) $serialized['label'] : CheckoutScenarioRules::scenario_label( $scenario );

		$order->update_meta_data( '_mp_cc_scenario_id', $scenario );
		$order->update_meta_data( '_mp_cc_scenario_label', $scenario_label );
		$order->update_meta_data( '_mp_cc_scenario_payload', wp_json_encode( $serialized ) );
	}
}
