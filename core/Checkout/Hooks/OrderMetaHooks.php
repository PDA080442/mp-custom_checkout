<?php
/**
 * Сохранение кастомных данных заказа (order meta).
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout\Checkout\Hooks;

use MP\CustomCheckout\DependencyFailureGuard;
use MP\CustomCheckout\Checkout\Order\OrderMetaKeys;
use MP\CustomCheckout\Hooks\CheckoutRouteHooks;
use MP\CustomCheckout\Routing\CheckoutConditionsSummaryBuilder;
use MP\CustomCheckout\Routing\CheckoutDateAvailabilityEngine;
use MP\CustomCheckout\Routing\CheckoutScenarioRules;
use MP\CustomCheckout\Checkout\Routing\CheckoutSessionService;
use MP\CustomCheckout\Integrations\WooCommerce\CdekWcSessionBridge;
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
		add_action( 'mp_custom_checkout_save_order_meta', array( __CLASS__, 'save_cdek_pvz_meta' ), 14, 2 );
		add_action( 'mp_custom_checkout_save_order_meta', array( __CLASS__, 'save_conditions_summary_meta' ), 20, 2 );
		add_action( 'mp_custom_checkout_save_order_meta', array( __CLASS__, 'save_discounts_meta' ), 22, 2 );
		add_filter( 'woocommerce_checkout_update_customer_data', array( __CLASS__, 'maybe_skip_customer_data_sync' ), 10, 1 );
	}

	/**
	 * Приводит страну к коду ISO из списка WooCommerce, если введён известный код или локализованное название.
	 * Иначе возвращает исходную строку (для редких кейсов и обратной совместимости).
	 */
	public static function normalize_billing_country_value( string $raw ): string {
		$raw = trim( $raw );
		if ( '' === $raw ) {
			return '';
		}
		if ( ! function_exists( 'WC' ) ) {
			return 2 === strlen( $raw ) ? strtoupper( $raw ) : $raw;
		}
		$wc = WC();
		if ( ! $wc instanceof \WooCommerce || ! $wc->countries instanceof \WC_Countries ) {
			return 2 === strlen( $raw ) ? strtoupper( $raw ) : $raw;
		}
		$countries = $wc->countries->get_allowed_countries();
		if ( ! is_array( $countries ) ) {
			$countries = array();
		}
		$shipping = $wc->countries->get_shipping_countries();
		if ( is_array( $shipping ) ) {
			foreach ( $shipping as $code => $label ) {
				if ( ! array_key_exists( $code, $countries ) ) {
					$countries[ $code ] = $label;
				}
			}
		}
		$upper = strtoupper( $raw );
		if ( 2 === strlen( $upper ) && preg_match( '/^[A-Z]{2}$/', $upper ) && isset( $countries[ $upper ] ) ) {
			return $upper;
		}
		foreach ( $countries as $code => $label ) {
			$code = (string) $code;
			if ( 0 === strcasecmp( $raw, $code ) || 0 === strcasecmp( $raw, (string) $label ) ) {
				return strtoupper( $code );
			}
		}
		return $raw;
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
		self::apply_billing_shipping_contact_to_target( $order, $contact, $hide_address );
		$custom_meta_map = array(
			OrderMetaKeys::BILLING_PATRONYMIC   => 'billing_patronymic',
			OrderMetaKeys::GENDER              => 'billing_gender',
			OrderMetaKeys::BILLING_BIRTHDATE   => 'billing_birthdate',
			OrderMetaKeys::PHONE_COUNTRY_ISO   => 'phone_country_iso',
			OrderMetaKeys::PHONE_DIAL_CODE     => 'phone_dial_code',
			OrderMetaKeys::BILLING_PHONE_LOCAL => 'billing_phone_national',
			OrderMetaKeys::ADDRESS_COUNTRY_CODE => 'country',
			OrderMetaKeys::ADDRESS_REGION_CODE  => 'state',
			OrderMetaKeys::ADDRESS_CITY         => 'city',
			OrderMetaKeys::ADDRESS_LINE1        => 'address_1',
			OrderMetaKeys::ADDRESS_LINE2        => 'address_2',
			OrderMetaKeys::ADDRESS_POSTCODE     => 'postcode',
		);
		foreach ( $custom_meta_map as $meta_key => $contact_key ) {
			if ( ! array_key_exists( $contact_key, $contact ) ) {
				continue;
			}
			if ( $hide_address && in_array( $contact_key, array( 'country', 'state', 'city', 'address_1', 'address_2', 'postcode' ), true ) ) {
				$order->delete_meta_data( $meta_key );
				continue;
			}
			$value = sanitize_text_field( (string) $contact[ $contact_key ] );
			if ( 'country' === $contact_key ) {
				$value = self::normalize_billing_country_value( $value );
			}
			if ( '' === $value ) {
				$order->delete_meta_data( $meta_key );
			} else {
				$order->update_meta_data( $meta_key, $value );
			}
		}
		if ( array_key_exists( 'order_notes', $contact ) ) { $note_value = sanitize_textarea_field( (string) $contact['order_notes'] ); $order->set_customer_note( $note_value ); if ( '' === $note_value ) { $order->delete_meta_data( OrderMetaKeys::ORDER_NOTES ); } else { $order->update_meta_data( OrderMetaKeys::ORDER_NOTES, $note_value ); } }
	}

	/**
	 * Те же billing/shipping поля, что на заказе в {@see self::apply_contact_fields_to_order()}, для сессии WC customer.
	 */
	public static function apply_contact_location_to_customer( \WC_Customer $customer, string $scenario, array $contact ): void {
		if ( empty( $contact ) ) {
			return;
		}
		$scenario     = CheckoutScenarioRules::sanitize_scenario( $scenario );
		$rules        = CheckoutScenarioRules::build( $scenario );
		$field_rules  = isset( $rules['field_rules'] ) && is_array( $rules['field_rules'] ) ? $rules['field_rules'] : array();
		$hide_address = ! empty( $field_rules['hide_address_fields'] );
		self::apply_billing_shipping_contact_to_target( $customer, $contact, $hide_address );
	}

	/**
	 * @param \WC_Order|\WC_Customer $target
	 */
	private static function apply_billing_shipping_contact_to_target( $target, array $contact, bool $hide_address ): void {
		$billing_map = array(
			'billing_first_name' => 'set_billing_first_name',
			'billing_last_name'  => 'set_billing_last_name',
			'billing_email'      => 'set_billing_email',
			'billing_phone'      => 'set_billing_phone',
			'country'            => 'set_billing_country',
			'state'              => 'set_billing_state',
			'city'               => 'set_billing_city',
			'address_1'          => 'set_billing_address_1',
			'address_2'          => 'set_billing_address_2',
			'postcode'           => 'set_billing_postcode',
		);
		foreach ( $billing_map as $contact_key => $setter ) {
			if ( ! array_key_exists( $contact_key, $contact ) ) {
				continue;
			}
			if ( $hide_address && in_array( $contact_key, array( 'country', 'state', 'city', 'address_1', 'address_2', 'postcode' ), true ) ) {
				continue;
			}
			$value = sanitize_text_field( (string) $contact[ $contact_key ] );
			if ( 'country' === $contact_key ) {
				$value = self::normalize_billing_country_value( $value );
			}
			if ( method_exists( $target, $setter ) ) {
				$target->{$setter}( $value );
			}
		}
		if ( ! $hide_address ) {
			$shipping_map = array(
				'country'   => 'set_shipping_country',
				'state'     => 'set_shipping_state',
				'city'      => 'set_shipping_city',
				'address_1' => 'set_shipping_address_1',
				'address_2' => 'set_shipping_address_2',
				'postcode'  => 'set_shipping_postcode',
			);
			foreach ( $shipping_map as $contact_key => $setter ) {
				if ( ! array_key_exists( $contact_key, $contact ) ) {
					continue;
				}
				$value = sanitize_text_field( (string) $contact[ $contact_key ] );
				if ( 'country' === $contact_key ) {
					$value = self::normalize_billing_country_value( $value );
				}
				if ( method_exists( $target, $setter ) ) {
					$target->{$setter}( $value );
				}
			}
		}
	}

	public static function save_scenario_meta( $order, $data = array() ): void {
		unset( $data ); if ( ! $order instanceof \WC_Order ) { return; }
		$flow = CheckoutSessionService::get_flow(); $scenario = isset( $flow['scenario'] ) ? (string) $flow['scenario'] : ''; $scenario = CheckoutScenarioRules::sanitize_scenario( $scenario ); $rules = CheckoutScenarioRules::build( $scenario );
		$serialized = isset( $rules['serialize'] ) && is_array( $rules['serialize'] ) ? $rules['serialize'] : array( 'id' => $scenario ); $scenario_label = isset( $serialized['label'] ) ? (string) $serialized['label'] : CheckoutScenarioRules::scenario_label( $scenario ); $scenario_label = CheckoutScenarioRules::normalize_label_for_output( $scenario_label, $scenario ); $serialized['label'] = $scenario_label;
		$order->update_meta_data( OrderMetaKeys::SCENARIO_ID, $scenario );
		$order->update_meta_data( OrderMetaKeys::SCENARIO_LABEL, $scenario_label );
		$order->update_meta_data( OrderMetaKeys::SCENARIO_PAYLOAD, wp_json_encode( $serialized ) );
		if ( ScenarioStepRegistry::SCENARIO_PICKUP === $scenario ) { $answers = isset( $flow['answers'] ) && is_array( $flow['answers'] ) ? $flow['answers'] : array(); $scenario_box = isset( $answers['scenario'] ) && is_array( $answers['scenario'] ) ? $answers['scenario'] : array(); $pickup_point = isset( $scenario_box['pickup_point'] ) && is_array( $scenario_box['pickup_point'] ) ? $scenario_box['pickup_point'] : array(); if ( ! empty( $pickup_point ) ) { $order->update_meta_data( OrderMetaKeys::PICKUP_POINT_ID, isset( $pickup_point['id'] ) ? sanitize_key( (string) $pickup_point['id'] ) : '' ); $order->update_meta_data( OrderMetaKeys::PICKUP_POINT_TITLE, isset( $pickup_point['title'] ) ? sanitize_text_field( (string) $pickup_point['title'] ) : '' ); $order->update_meta_data( OrderMetaKeys::PICKUP_POINT_ADDRESS, isset( $pickup_point['address'] ) ? sanitize_text_field( (string) $pickup_point['address'] ) : '' ); $order->update_meta_data( OrderMetaKeys::PICKUP_POINT_DESCRIPTION, isset( $pickup_point['description'] ) ? sanitize_text_field( (string) $pickup_point['description'] ) : '' ); $order->update_meta_data( OrderMetaKeys::PICKUP_POINT_PAYLOAD, wp_json_encode( $pickup_point ) ); } } else { $order->delete_meta_data( OrderMetaKeys::PICKUP_POINT_ID ); $order->delete_meta_data( OrderMetaKeys::PICKUP_POINT_TITLE ); $order->delete_meta_data( OrderMetaKeys::PICKUP_POINT_ADDRESS ); $order->delete_meta_data( OrderMetaKeys::PICKUP_POINT_DESCRIPTION ); $order->delete_meta_data( OrderMetaKeys::PICKUP_POINT_PAYLOAD ); }
	}

	public static function save_selected_date_meta( $order, $data = array() ): void { unset( $data ); if ( ! $order instanceof \WC_Order ) { return; } $flow = CheckoutSessionService::get_flow(); $scenario = isset( $flow['scenario'] ) ? CheckoutScenarioRules::sanitize_scenario( (string) $flow['scenario'] ) : ScenarioStepRegistry::SCENARIO_PICKUP; $answers = isset( $flow['answers'] ) && is_array( $flow['answers'] ) ? $flow['answers'] : array(); $date_box = isset( $answers['date_conditions'] ) && is_array( $answers['date_conditions'] ) ? $answers['date_conditions'] : array(); $selected = isset( $date_box['selected_date'] ) ? sanitize_text_field( (string) $date_box['selected_date'] ) : ''; if ( '' === $selected ) { return; } $available = CheckoutDateAvailabilityEngine::build_rules( $scenario ); $allowed = isset( $available['available_dates'] ) && is_array( $available['available_dates'] ) ? $available['available_dates'] : array(); if ( ! in_array( $selected, $allowed, true ) ) { do_action( 'mp_custom_checkout_log', 'error', '[date_sync] order_meta_date_rejected', array( 'order_id' => $order->get_id(), 'selected_date' => $selected, 'scenario' => $scenario ) ); return; } $label = $selected; $dt = \DateTimeImmutable::createFromFormat( 'Y-m-d', $selected, wp_timezone() ); if ( $dt instanceof \DateTimeImmutable ) { $label = $dt->format( 'd.m.Y' ); } $order->update_meta_data( OrderMetaKeys::SELECTED_DATE, $selected ); $order->update_meta_data( OrderMetaKeys::SELECTED_DATE_LABEL, $label ); }

	/**
	 * MP-cc meta для ПВЗ СДЭК (method=pvz): отчёты и QA; при смене метода — ключи удаляются (не «прилипают»).
	 */
	public static function save_cdek_pvz_meta( $order, $data = array() ): void {
		unset( $data );
		if ( ! $order instanceof \WC_Order ) {
			return;
		}
		$flow     = CheckoutSessionService::get_flow();
		$delivery = CdekWcSessionBridge::get_merged_delivery_answers( is_array( $flow ) ? $flow : array() );
		$method   = sanitize_key( (string) ( $delivery['shipping_method_id'] ?? '' ) );
		if ( 'pvz' !== $method ) {
			$order->delete_meta_data( OrderMetaKeys::CDEK_OFFICE_CODE );
			$order->delete_meta_data( OrderMetaKeys::CDEK_RATE_ID );
			return;
		}
		$office = trim( (string) ( $delivery['cdek_office_code'] ?? '' ) );

		$actual_rate_id = '';
		foreach ( $order->get_items( 'shipping' ) as $ship_item ) {
			if ( ! $ship_item instanceof \WC_Order_Item_Shipping ) {
				continue;
			}
			if ( 'official_cdek' !== $ship_item->get_method_id() ) {
				continue;
			}
			$actual_rate_id = CdekWcSessionBridge::OFFICIAL_CDEK_PREFIX . (string) $ship_item->get_instance_id();
			break;
		}

		if ( '' !== $actual_rate_id ) {
			$order->update_meta_data( OrderMetaKeys::CDEK_RATE_ID, $actual_rate_id );
			if ( '' !== $office ) {
				$order->update_meta_data( OrderMetaKeys::CDEK_OFFICE_CODE, $office );
			} else {
				$order->delete_meta_data( OrderMetaKeys::CDEK_OFFICE_CODE );
			}
			return;
		}

		$order->delete_meta_data( OrderMetaKeys::CDEK_OFFICE_CODE );
		$order->delete_meta_data( OrderMetaKeys::CDEK_RATE_ID );
		do_action(
			'mp_custom_checkout_log',
			'info',
			'[pvz] order_meta_skipped_no_cdek_line',
			array(
				'order_id'             => (int) $order->get_id(),
				'flow_method'          => 'pvz',
				'flow_office_present' => '' !== $office,
			)
		);
	}
	public static function save_conditions_summary_meta( $order, $data = array() ): void { unset( $data ); if ( ! $order instanceof \WC_Order ) { return; } $flow = CheckoutSessionService::get_flow(); $scenario = isset( $flow['scenario'] ) ? CheckoutScenarioRules::sanitize_scenario( (string) $flow['scenario'] ) : ScenarioStepRegistry::SCENARIO_PICKUP; $text = CheckoutConditionsSummaryBuilder::build_for_flow( $scenario, $flow ); $text = (string) apply_filters( 'mp_custom_checkout_order_conditions_summary', $text, $order, $scenario, $flow ); if ( '' !== trim( $text ) ) { $order->update_meta_data( OrderMetaKeys::CONDITIONS_SUMMARY, $text ); } else { $order->delete_meta_data( OrderMetaKeys::CONDITIONS_SUMMARY ); } }
	public static function save_discounts_meta( $order, $data = array() ): void { unset( $data ); if ( ! $order instanceof \WC_Order ) { return; } $flow = CheckoutSessionService::get_flow(); $answers = isset( $flow['answers'] ) && is_array( $flow['answers'] ) ? $flow['answers'] : array(); $discounts = isset( $answers['discounts'] ) && is_array( $answers['discounts'] ) ? $answers['discounts'] : array(); $coupon_codes = isset( $discounts['coupons'] ) && is_array( $discounts['coupons'] ) ? array_values( array_map( 'sanitize_text_field', $discounts['coupons'] ) ) : array(); $gift_card_codes = isset( $discounts['gift_card'] ) && is_array( $discounts['gift_card'] ) ? array_values( array_map( 'sanitize_text_field', $discounts['gift_card'] ) ) : array(); if ( ! empty( $coupon_codes ) ) { $order->update_meta_data( OrderMetaKeys::APPLIED_COUPONS, wp_json_encode( $coupon_codes ) ); } else { $order->delete_meta_data( OrderMetaKeys::APPLIED_COUPONS ); } if ( ! empty( $gift_card_codes ) ) { $order->update_meta_data( OrderMetaKeys::APPLIED_GIFT_CARDS, wp_json_encode( $gift_card_codes ) ); } else { $order->delete_meta_data( OrderMetaKeys::APPLIED_GIFT_CARDS ); } $coupon_total = (float) $order->get_discount_total(); $order->update_meta_data( OrderMetaKeys::COUPON_DISCOUNT_TOTAL, (string) $coupon_total ); $gift_total = 0.0; foreach ( $order->get_items( 'fee' ) as $item ) { if ( ! $item instanceof \WC_Order_Item_Fee ) { continue; } $name = (string) $item->get_name(); $total = (float) $item->get_total(); if ( $total >= 0 ) { continue; } $lc_name = function_exists( 'mb_strtolower' ) ? mb_strtolower( $name ) : strtolower( $name ); if ( false === strpos( $lc_name, 'gift' ) && false === strpos( $lc_name, 'подар' ) && false === strpos( $lc_name, 'pw' ) ) { continue; } $gift_total += abs( $total ); } $order->update_meta_data( OrderMetaKeys::GIFT_CARD_TOTAL, (string) $gift_total ); }
}
