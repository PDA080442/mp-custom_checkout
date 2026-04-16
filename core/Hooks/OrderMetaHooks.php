<?php
/**
 * Сохранение кастомных данных заказа (order meta).
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout\Hooks;

use MP\CustomCheckout\DependencyFailureGuard;
use MP\CustomCheckout\Routing\CheckoutConditionsSummaryBuilder;
use MP\CustomCheckout\Routing\CheckoutDateAvailabilityEngine;
use MP\CustomCheckout\Routing\CheckoutScenarioRules;
use MP\CustomCheckout\Routing\CheckoutSessionService;
use MP\CustomCheckout\Settings\ScenarioStepRegistry;

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
		add_action( 'mp_custom_checkout_save_order_meta', array( __CLASS__, 'save_selected_date_meta' ), 12, 2 );
		add_action( 'mp_custom_checkout_save_order_meta', array( __CLASS__, 'save_conditions_confirmation_meta' ), 14, 2 );
		add_action( 'mp_custom_checkout_save_order_meta', array( __CLASS__, 'save_conditions_summary_meta' ), 20, 2 );
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

		$serialized      = isset( $rules['serialize'] ) && is_array( $rules['serialize'] ) ? $rules['serialize'] : array( 'id' => $scenario );
		$scenario_label  = isset( $serialized['label'] ) ? (string) $serialized['label'] : CheckoutScenarioRules::scenario_label( $scenario );
		$scenario_label  = CheckoutScenarioRules::normalize_label_for_output( $scenario_label, $scenario );
		$serialized['label'] = $scenario_label;

		$order->update_meta_data( '_mp_cc_scenario_id', $scenario );
		$order->update_meta_data( '_mp_cc_scenario_label', $scenario_label );
		$order->update_meta_data( '_mp_cc_scenario_payload', wp_json_encode( $serialized ) );
		if ( ScenarioStepRegistry::SCENARIO_PICKUP === $scenario ) {
			$answers      = isset( $flow['answers'] ) && is_array( $flow['answers'] ) ? $flow['answers'] : array();
			$scenario_box = isset( $answers['scenario'] ) && is_array( $answers['scenario'] ) ? $answers['scenario'] : array();
			$pickup_point = isset( $scenario_box['pickup_point'] ) && is_array( $scenario_box['pickup_point'] ) ? $scenario_box['pickup_point'] : array();
			if ( ! empty( $pickup_point ) ) {
				$order->update_meta_data( '_mp_cc_pickup_point_id', isset( $pickup_point['id'] ) ? sanitize_key( (string) $pickup_point['id'] ) : '' );
				$order->update_meta_data( '_mp_cc_pickup_point_title', isset( $pickup_point['title'] ) ? sanitize_text_field( (string) $pickup_point['title'] ) : '' );
				$order->update_meta_data( '_mp_cc_pickup_point_address', isset( $pickup_point['address'] ) ? sanitize_text_field( (string) $pickup_point['address'] ) : '' );
				$order->update_meta_data( '_mp_cc_pickup_point_description', isset( $pickup_point['description'] ) ? sanitize_text_field( (string) $pickup_point['description'] ) : '' );
				$order->update_meta_data( '_mp_cc_pickup_point_payload', wp_json_encode( $pickup_point ) );
			}
		}
	}

	/**
	 * Сохраняет выбранную дату из checkout-flow в order meta.
	 *
	 * @param \WC_Order $order Заказ.
	 * @param array     $data  Данные checkout.
	 */
	public static function save_selected_date_meta( $order, $data = array() ): void {
		unset( $data );
		if ( ! $order instanceof \WC_Order ) {
			return;
		}

		$flow      = CheckoutSessionService::get_flow();
		$scenario  = isset( $flow['scenario'] ) ? CheckoutScenarioRules::sanitize_scenario( (string) $flow['scenario'] ) : ScenarioStepRegistry::SCENARIO_PICKUP;
		$answers   = isset( $flow['answers'] ) && is_array( $flow['answers'] ) ? $flow['answers'] : array();
		$date_box  = isset( $answers['date_conditions'] ) && is_array( $answers['date_conditions'] ) ? $answers['date_conditions'] : array();
		$selected  = isset( $date_box['selected_date'] ) ? sanitize_text_field( (string) $date_box['selected_date'] ) : '';
		if ( '' === $selected ) {
			return;
		}
		$available = CheckoutDateAvailabilityEngine::build_rules( $scenario );
		$allowed   = isset( $available['available_dates'] ) && is_array( $available['available_dates'] ) ? $available['available_dates'] : array();
		if ( ! in_array( $selected, $allowed, true ) ) {
			do_action(
				'mp_custom_checkout_log',
				'error',
				'[date_sync] order_meta_date_rejected',
				array(
					'order_id'      => $order->get_id(),
					'selected_date' => $selected,
					'scenario'      => $scenario,
				)
			);
			return;
		}

		$label = $selected;
		$dt    = \DateTimeImmutable::createFromFormat( 'Y-m-d', $selected, wp_timezone() );
		if ( $dt instanceof \DateTimeImmutable ) {
			$label = $dt->format( 'd.m.Y' );
		}
		$order->update_meta_data( '_mp_cc_selected_date', $selected );
		$order->update_meta_data( '_mp_cc_selected_date_label', $label );
	}

	/**
	 * Аудит: факт подтверждения шага условий (если был отмечен чекбокс в сессии).
	 *
	 * @param \WC_Order $order Заказ.
	 * @param array     $data  Данные checkout.
	 */
	public static function save_conditions_confirmation_meta( $order, $data = array() ): void {
		unset( $data );
		if ( ! $order instanceof \WC_Order ) {
			return;
		}

		$flow     = CheckoutSessionService::get_flow();
		$answers  = isset( $flow['answers'] ) && is_array( $flow['answers'] ) ? $flow['answers'] : array();
		$date_box = isset( $answers['date_conditions'] ) && is_array( $answers['date_conditions'] ) ? $answers['date_conditions'] : array();
		$raw      = isset( $date_box['conditions_confirmed'] ) ? $date_box['conditions_confirmed'] : false;
		$confirmed = false;
		if ( true === $raw || 1 === $raw || '1' === (string) $raw ) {
			$confirmed = true;
		} elseif ( is_string( $raw ) ) {
			$confirmed = in_array( strtolower( trim( $raw ) ), array( 'true', 'yes', 'on' ), true );
		}

		$order->update_meta_data( '_mp_cc_conditions_confirmed', $confirmed ? 'yes' : 'no' );
		if ( $confirmed ) {
			$order->update_meta_data( '_mp_cc_conditions_confirmed_at', (string) time() );
		} else {
			$order->delete_meta_data( '_mp_cc_conditions_confirmed_at' );
		}
	}

	/**
	 * Текст условий получения (единый для писем, админки и списка заказов).
	 *
	 * @param \WC_Order $order Заказ.
	 * @param array     $data  Данные checkout.
	 */
	public static function save_conditions_summary_meta( $order, $data = array() ): void {
		unset( $data );
		if ( ! $order instanceof \WC_Order ) {
			return;
		}

		$flow     = CheckoutSessionService::get_flow();
		$scenario = isset( $flow['scenario'] ) ? CheckoutScenarioRules::sanitize_scenario( (string) $flow['scenario'] ) : ScenarioStepRegistry::SCENARIO_PICKUP;
		$text     = CheckoutConditionsSummaryBuilder::build_for_flow( $scenario, $flow );

		/**
		 * Текст условий при сохранении заказа (после сборки из сессии).
		 *
		 * @param string               $text     Текст.
		 * @param \WC_Order            $order    Заказ.
		 * @param string               $scenario Сценарий.
		 * @param array<string, mixed> $flow     Flow.
		 */
		$text = (string) apply_filters( 'mp_custom_checkout_order_conditions_summary', $text, $order, $scenario, $flow );

		if ( '' !== trim( $text ) ) {
			$order->update_meta_data( CheckoutConditionsSummaryBuilder::ORDER_META_KEY, $text );
		} else {
			$order->delete_meta_data( CheckoutConditionsSummaryBuilder::ORDER_META_KEY );
		}
	}
}
