<?php
/**
 * Сохранение кастомных данных заказа (order meta).
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout\Checkout\Hooks;

use MP\CustomCheckout\DependencyFailureGuard;
use MP\CustomCheckout\Hooks\CheckoutRouteHooks;
use MP\CustomCheckout\Routing\CheckoutConditionsSummaryBuilder;
use MP\CustomCheckout\Routing\CheckoutDateAvailabilityEngine;
use MP\CustomCheckout\Routing\CheckoutScenarioRules;
use MP\CustomCheckout\Checkout\Routing\CheckoutSessionService;
use MP\CustomCheckout\Settings\ScenarioStepRegistry;

defined( 'ABSPATH' ) || exit;

final class OrderMetaHooks {

	public static function register(): void {
		if ( ! DependencyFailureGuard::is_woocommerce_integration_ready() ) {
			return;
		}
		add_action( 'woocommerce_checkout_order_created', array( __CLASS__, 'on_checkout_order_created' ), 10, 2 );
		add_action( 'woocommerce_checkout_order_created', array( __CLASS__, 'apply_contact_fields_to_order' ), 11, 2 );
		add_action( 'mp_custom_checkout_save_order_meta', array( __CLASS__, 'save_scenario_meta' ), 10, 2 );
		add_action( 'mp_custom_checkout_save_order_meta', array( __CLASS__, 'save_selected_date_meta' ), 12, 2 );
		add_action( 'mp_custom_checkout_save_order_meta', array( __CLASS__, 'save_conditions_confirmation_meta' ), 14, 2 );
		add_action( 'mp_custom_checkout_save_order_meta', array( __CLASS__, 'save_conditions_summary_meta' ), 20, 2 );
		add_action( 'mp_custom_checkout_save_order_meta', array( __CLASS__, 'save_discounts_meta' ), 22, 2 );
		add_filter( 'woocommerce_checkout_update_customer_data', array( __CLASS__, 'maybe_skip_customer_data_sync' ), 10, 1 );
	}

	public static function maybe_skip_customer_data_sync( bool $should_update ): bool {
		if ( ! $should_update ) { return false; }
		$flow = CheckoutSessionService::get_flow();
		if ( empty( $flow ) ) { return $should_update; }
		$answers = isset( $flow['answers'] ) && is_array( $flow['answers'] ) ? $flow['answers'] : array();
		$contact = isset( $answers['contact_billing'] ) && is_array( $answers['contact_billing'] ) ? $answers['contact_billing'] : array();
		if ( ! empty( $contact ) ) { return false; }
		return CheckoutRouteHooks::is_checkout_route() ? false : $should_update;
	}

	public static function on_checkout_order_created( $order, $data = array() ): void {
		if ( ! $order instanceof \WC_Order ) { return; }
		do_action( 'mp_custom_checkout_save_order_meta', $order, $data );
	}

	public static function apply_contact_fields_to_order( $order, $data = array() ): void {
		unset( $data ); if ( ! $order instanceof \WC_Order ) { return; }
		$flow = CheckoutSessionService::get_flow(); $answers = isset( $flow['answers'] ) && is_array( $flow['answers'] ) ? $flow['answers'] : array(); $contact = isset( $answers['contact_billing'] ) && is_array( $answers['contact_billing'] ) ? $answers['contact_billing'] : array(); if ( empty( $contact ) ) { return; }
		$scenario = isset( $flow['scenario'] ) ? CheckoutScenarioRules::sanitize_scenario( (string) $flow['scenario'] ) : ScenarioStepRegistry::SCENARIO_PICKUP; $rules = CheckoutScenarioRules::build( $scenario ); $field_rules = isset( $rules['field_rules'] ) && is_array( $rules['field_rules'] ) ? $rules['field_rules'] : array(); $hide_address = ! empty( $field_rules['hide_address_fields'] );
		$billing_map = array( 'billing_first_name' => 'set_billing_first_name', 'billing_last_name' => 'set_billing_last_name', 'billing_email' => 'set_billing_email', 'billing_phone' => 'set_billing_phone', 'country' => 'set_billing_country', 'state' => 'set_billing_state', 'city' => 'set_billing_city', 'address_1' => 'set_billing_address_1', 'address_2' => 'set_billing_address_2', 'postcode' => 'set_billing_postcode' );
		foreach ( $billing_map as $contact_key => $setter ) { if ( ! array_key_exists( $contact_key, $contact ) ) { continue; } if ( $hide_address && in_array( $contact_key, array( 'country', 'state', 'city', 'address_1', 'address_2', 'postcode' ), true ) ) { continue; } $value = sanitize_text_field( (string) $contact[ $contact_key ] ); if ( method_exists( $order, $setter ) ) { $order->{$setter}( $value ); } }
		if ( ! $hide_address ) { $shipping_map = array( 'country' => 'set_shipping_country', 'state' => 'set_shipping_state', 'city' => 'set_shipping_city', 'address_1' => 'set_shipping_address_1', 'address_2' => 'set_shipping_address_2', 'postcode' => 'set_shipping_postcode' ); foreach ( $shipping_map as $contact_key => $setter ) { if ( ! array_key_exists( $contact_key, $contact ) ) { continue; } $value = sanitize_text_field( (string) $contact[ $contact_key ] ); if ( method_exists( $order, $setter ) ) { $order->{$setter}( $value ); } } }
		$custom_meta_map = array( '_mp_cc_billing_patronymic' => 'billing_patronymic', '_mp_cc_gender' => 'billing_gender', '_mp_cc_billing_birthdate' => 'billing_birthdate', '_mp_cc_phone_country_iso' => 'phone_country_iso', '_mp_cc_phone_dial_code' => 'phone_dial_code', '_mp_cc_billing_phone_local' => 'billing_phone_national', '_mp_cc_address_country_code' => 'country', '_mp_cc_address_region_code' => 'state', '_mp_cc_address_city' => 'city', '_mp_cc_address_line1' => 'address_1', '_mp_cc_address_line2' => 'address_2', '_mp_cc_address_postcode' => 'postcode' );
		foreach ( $custom_meta_map as $meta_key => $contact_key ) { if ( ! array_key_exists( $contact_key, $contact ) ) { continue; } if ( $hide_address && in_array( $contact_key, array( 'country', 'state', 'city', 'address_1', 'address_2', 'postcode' ), true ) ) { $order->delete_meta_data( $meta_key ); continue; } $value = sanitize_text_field( (string) $contact[ $contact_key ] ); if ( '' === $value ) { $order->delete_meta_data( $meta_key ); } else { $order->update_meta_data( $meta_key, $value ); } }
		if ( array_key_exists( 'order_notes', $contact ) ) { $note_value = sanitize_textarea_field( (string) $contact['order_notes'] ); $order->set_customer_note( $note_value ); if ( '' === $note_value ) { $order->delete_meta_data( '_mp_cc_order_notes' ); } else { $order->update_meta_data( '_mp_cc_order_notes', $note_value ); } }
	}

	public static function save_scenario_meta( $order, $data = array() ): void {
		unset( $data ); if ( ! $order instanceof \WC_Order ) { return; }
		$flow = CheckoutSessionService::get_flow(); $scenario = isset( $flow['scenario'] ) ? (string) $flow['scenario'] : ''; $scenario = CheckoutScenarioRules::sanitize_scenario( $scenario ); $rules = CheckoutScenarioRules::build( $scenario );
		$serialized = isset( $rules['serialize'] ) && is_array( $rules['serialize'] ) ? $rules['serialize'] : array( 'id' => $scenario ); $scenario_label = isset( $serialized['label'] ) ? (string) $serialized['label'] : CheckoutScenarioRules::scenario_label( $scenario ); $scenario_label = CheckoutScenarioRules::normalize_label_for_output( $scenario_label, $scenario ); $serialized['label'] = $scenario_label;
		$order->update_meta_data( '_mp_cc_scenario_id', $scenario ); $order->update_meta_data( '_mp_cc_scenario_label', $scenario_label ); $order->update_meta_data( '_mp_cc_scenario_payload', wp_json_encode( $serialized ) );
		if ( ScenarioStepRegistry::SCENARIO_PICKUP === $scenario ) { $answers = isset( $flow['answers'] ) && is_array( $flow['answers'] ) ? $flow['answers'] : array(); $scenario_box = isset( $answers['scenario'] ) && is_array( $answers['scenario'] ) ? $answers['scenario'] : array(); $pickup_point = isset( $scenario_box['pickup_point'] ) && is_array( $scenario_box['pickup_point'] ) ? $scenario_box['pickup_point'] : array(); if ( ! empty( $pickup_point ) ) { $order->update_meta_data( '_mp_cc_pickup_point_id', isset( $pickup_point['id'] ) ? sanitize_key( (string) $pickup_point['id'] ) : '' ); $order->update_meta_data( '_mp_cc_pickup_point_title', isset( $pickup_point['title'] ) ? sanitize_text_field( (string) $pickup_point['title'] ) : '' ); $order->update_meta_data( '_mp_cc_pickup_point_address', isset( $pickup_point['address'] ) ? sanitize_text_field( (string) $pickup_point['address'] ) : '' ); $order->update_meta_data( '_mp_cc_pickup_point_description', isset( $pickup_point['description'] ) ? sanitize_text_field( (string) $pickup_point['description'] ) : '' ); $order->update_meta_data( '_mp_cc_pickup_point_payload', wp_json_encode( $pickup_point ) ); } }
	}

	public static function save_selected_date_meta( $order, $data = array() ): void { unset( $data ); if ( ! $order instanceof \WC_Order ) { return; } $flow = CheckoutSessionService::get_flow(); $scenario = isset( $flow['scenario'] ) ? CheckoutScenarioRules::sanitize_scenario( (string) $flow['scenario'] ) : ScenarioStepRegistry::SCENARIO_PICKUP; $answers = isset( $flow['answers'] ) && is_array( $flow['answers'] ) ? $flow['answers'] : array(); $date_box = isset( $answers['date_conditions'] ) && is_array( $answers['date_conditions'] ) ? $answers['date_conditions'] : array(); $selected = isset( $date_box['selected_date'] ) ? sanitize_text_field( (string) $date_box['selected_date'] ) : ''; if ( '' === $selected ) { return; } $available = CheckoutDateAvailabilityEngine::build_rules( $scenario ); $allowed = isset( $available['available_dates'] ) && is_array( $available['available_dates'] ) ? $available['available_dates'] : array(); if ( ! in_array( $selected, $allowed, true ) ) { do_action( 'mp_custom_checkout_log', 'error', '[date_sync] order_meta_date_rejected', array( 'order_id' => $order->get_id(), 'selected_date' => $selected, 'scenario' => $scenario ) ); return; } $label = $selected; $dt = \DateTimeImmutable::createFromFormat( 'Y-m-d', $selected, wp_timezone() ); if ( $dt instanceof \DateTimeImmutable ) { $label = $dt->format( 'd.m.Y' ); } $order->update_meta_data( '_mp_cc_selected_date', $selected ); $order->update_meta_data( '_mp_cc_selected_date_label', $label ); }
	public static function save_conditions_confirmation_meta( $order, $data = array() ): void { unset( $data ); if ( ! $order instanceof \WC_Order ) { return; } $flow = CheckoutSessionService::get_flow(); $answers = isset( $flow['answers'] ) && is_array( $flow['answers'] ) ? $flow['answers'] : array(); $date_box = isset( $answers['date_conditions'] ) && is_array( $answers['date_conditions'] ) ? $answers['date_conditions'] : array(); $raw = isset( $date_box['conditions_confirmed'] ) ? $date_box['conditions_confirmed'] : false; $confirmed = false; if ( true === $raw || 1 === $raw || '1' === (string) $raw ) { $confirmed = true; } elseif ( is_string( $raw ) ) { $confirmed = in_array( strtolower( trim( $raw ) ), array( 'true', 'yes', 'on' ), true ); } $order->update_meta_data( '_mp_cc_conditions_confirmed', $confirmed ? 'yes' : 'no' ); if ( $confirmed ) { $order->update_meta_data( '_mp_cc_conditions_confirmed_at', (string) time() ); } else { $order->delete_meta_data( '_mp_cc_conditions_confirmed_at' ); } }
	public static function save_conditions_summary_meta( $order, $data = array() ): void { unset( $data ); if ( ! $order instanceof \WC_Order ) { return; } $flow = CheckoutSessionService::get_flow(); $scenario = isset( $flow['scenario'] ) ? CheckoutScenarioRules::sanitize_scenario( (string) $flow['scenario'] ) : ScenarioStepRegistry::SCENARIO_PICKUP; $text = CheckoutConditionsSummaryBuilder::build_for_flow( $scenario, $flow ); $text = (string) apply_filters( 'mp_custom_checkout_order_conditions_summary', $text, $order, $scenario, $flow ); if ( '' !== trim( $text ) ) { $order->update_meta_data( CheckoutConditionsSummaryBuilder::ORDER_META_KEY, $text ); } else { $order->delete_meta_data( CheckoutConditionsSummaryBuilder::ORDER_META_KEY ); } }
	public static function save_discounts_meta( $order, $data = array() ): void { unset( $data ); if ( ! $order instanceof \WC_Order ) { return; } $flow = CheckoutSessionService::get_flow(); $answers = isset( $flow['answers'] ) && is_array( $flow['answers'] ) ? $flow['answers'] : array(); $discounts = isset( $answers['discounts'] ) && is_array( $answers['discounts'] ) ? $answers['discounts'] : array(); $coupon_codes = isset( $discounts['coupons'] ) && is_array( $discounts['coupons'] ) ? array_values( array_map( 'sanitize_text_field', $discounts['coupons'] ) ) : array(); $gift_card_codes = isset( $discounts['gift_card'] ) && is_array( $discounts['gift_card'] ) ? array_values( array_map( 'sanitize_text_field', $discounts['gift_card'] ) ) : array(); if ( ! empty( $coupon_codes ) ) { $order->update_meta_data( '_mp_cc_applied_coupons', wp_json_encode( $coupon_codes ) ); } else { $order->delete_meta_data( '_mp_cc_applied_coupons' ); } if ( ! empty( $gift_card_codes ) ) { $order->update_meta_data( '_mp_cc_applied_gift_cards', wp_json_encode( $gift_card_codes ) ); } else { $order->delete_meta_data( '_mp_cc_applied_gift_cards' ); } $coupon_total = (float) $order->get_discount_total(); $order->update_meta_data( '_mp_cc_coupon_discount_total', (string) $coupon_total ); $gift_total = 0.0; foreach ( $order->get_items( 'fee' ) as $item ) { if ( ! $item instanceof \WC_Order_Item_Fee ) { continue; } $name = (string) $item->get_name(); $total = (float) $item->get_total(); if ( $total >= 0 ) { continue; } $lc_name = function_exists( 'mb_strtolower' ) ? mb_strtolower( $name ) : strtolower( $name ); if ( false === strpos( $lc_name, 'gift' ) && false === strpos( $lc_name, 'подар' ) && false === strpos( $lc_name, 'pw' ) ) { continue; } $gift_total += abs( $total ); } $order->update_meta_data( '_mp_cc_gift_card_total', (string) $gift_total ); }
	}
}
