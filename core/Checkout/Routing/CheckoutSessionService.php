<?php
/**
 * Изолированная сессия checkout-flow (шаг, snapshot, ответы).
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout\Checkout\Routing;

use MP\CustomCheckout\DependencyFailureGuard;
use MP\CustomCheckout\Hooks\CheckoutRouteHooks;
use MP\CustomCheckout\Routing\CheckoutScenarioRules;
use MP\CustomCheckout\Routing\CheckoutStepManager;
use MP\CustomCheckout\Routing\PickupPointRegistry;
use MP\CustomCheckout\Settings\SafeSettingsResolver;
use MP\CustomCheckout\Settings\ScenarioStepRegistry;

defined( 'ABSPATH' ) || exit;

final class CheckoutSessionService {

	public const SESSION_KEY = 'mp_cc_checkout_flow';
	public const FLOW_TTL_SECONDS = 7200;

	public static function register(): void {
		add_action( 'template_redirect', array( __CLASS__, 'maybe_initialize_context' ), 4 );
		add_action( 'woocommerce_thankyou', array( __CLASS__, 'clear_on_success_thankyou' ), 20, 1 );
		add_action( 'mp_custom_checkout_success_screen', array( __CLASS__, 'clear_on_success' ), 5, 2 );
		add_action( 'woocommerce_cart_emptied', array( __CLASS__, 'clear_on_abandoned_flow' ), 20 );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_clear_on_order_cancel' ), 2 );
	}

	public static function maybe_initialize_context(): void {
		if ( ! CheckoutRouteHooks::is_checkout_route() ) {
			return;
		}
		$flow = self::get_flow();
		if ( self::is_flow_stale( $flow ) ) {
			$flow = array();
		}
		if ( ! isset( $flow['context_id'] ) || ! is_string( $flow['context_id'] ) || '' === $flow['context_id'] ) {
			$flow = self::build_initial_flow();
			if ( empty( $flow ) ) {
				self::log_session_error( 'session_init_failed' );
				return;
			}
		} else {
			$flow['updated_at'] = time();
		}
		if ( ! isset( $flow['answers'] ) || ! is_array( $flow['answers'] ) ) {
			$flow['answers'] = self::default_answers_structure();
		} else {
			$flow['answers'] = self::merge_answers_with_defaults( $flow['answers'] );
		}
		$step_manager = new CheckoutStepManager( $flow );
		$active_step  = $step_manager->get_current_step_id();
		if ( is_string( $active_step ) && '' !== $active_step ) {
			$flow['current_step'] = $active_step;
		}
		self::persist_flow( $flow );
	}

	/** @return array<string, mixed> */
	public static function get_flow(): array {
		$session = self::get_wc_session();
		if ( ! $session ) {
			return array();
		}
		$flow = $session->get( self::SESSION_KEY, array() );
		return is_array( $flow ) ? $flow : array();
	}

	public static function set_current_step( string $step_id ): void {
		$flow = self::ensure_initialized();
		if ( empty( $flow ) ) {
			return;
		}
		$step_id = sanitize_key( $step_id );
		if ( '' === $step_id ) {
			return;
		}
		$flow['current_step'] = $step_id;
		$flow['updated_at']   = time();
		self::persist_flow( $flow );
	}

	public static function set_scenario( string $scenario ): void {
		$flow = self::ensure_initialized();
		if ( empty( $flow ) ) {
			return;
		}
		$previous_scenario = isset( $flow['scenario'] ) ? CheckoutScenarioRules::sanitize_scenario( (string) $flow['scenario'] ) : '';
		$scenario          = CheckoutScenarioRules::sanitize_scenario( $scenario );
		$flow['scenario']  = $scenario;
		$flow['updated_at'] = time();
		$answers = isset( $flow['answers'] ) && is_array( $flow['answers'] ) ? $flow['answers'] : self::default_answers_structure();
		$answers = self::merge_answers_with_defaults( $answers );
		if ( '' !== $previous_scenario && $previous_scenario !== $scenario ) {
			$answers = self::reset_dependent_answers_for_scenario_switch( $answers, $scenario );
		}
		$answers['scenario'] = array(
			'id'    => $scenario,
			'label' => CheckoutScenarioRules::scenario_label( $scenario ),
		);
		if ( ScenarioStepRegistry::SCENARIO_PICKUP === $scenario ) {
			$selected_point = isset( $answers['scenario']['pickup_point']['id'] ) ? sanitize_key( (string) $answers['scenario']['pickup_point']['id'] ) : '';
			$point          = PickupPointRegistry::find_by_id( $selected_point );
			$answers['scenario']['pickup_point'] = $point;
		}
		$flow['answers']  = $answers;
		$flow['snapshot'] = self::build_snapshot();
		$step_manager     = new CheckoutStepManager( $flow );
		$current_step     = $step_manager->get_current_step_id();
		if ( is_string( $current_step ) && '' !== $current_step ) {
			$flow['current_step'] = $current_step;
		}
		if ( '' !== $previous_scenario && $previous_scenario !== $scenario ) {
			do_action( 'mp_custom_checkout_log', 'info', '[scenario_switch] applied', array( 'from' => $previous_scenario, 'to' => $scenario, 'current_step' => isset( $flow['current_step'] ) ? (string) $flow['current_step'] : '' ) );
		}
		self::persist_flow( $flow );
	}

	/** @param array<string, mixed> $answers @return array<string, mixed> */
	private static function reset_dependent_answers_for_scenario_switch( array $answers, string $scenario ): array {
		$date_prev   = isset( $answers['date_conditions'] ) && is_array( $answers['date_conditions'] ) ? $answers['date_conditions'] : array();
		$had_confirm = ! empty( $date_prev['conditions_confirmed'] );
		$answers['date_conditions'] = array();
		if ( $had_confirm ) {
			do_action( 'mp_custom_checkout_log', 'info', '[conditions_step] reset_on_scenario_switch', array( 'scenario' => $scenario ) );
		}
		$contact     = isset( $answers['contact_billing'] ) && is_array( $answers['contact_billing'] ) ? $answers['contact_billing'] : array();
		$field_rules = CheckoutScenarioRules::build( $scenario );
		$hide_address = isset( $field_rules['field_rules']['hide_address_fields'] ) ? (bool) $field_rules['field_rules']['hide_address_fields'] : false;
		if ( $hide_address ) {
			foreach ( array( 'address_1', 'address_2', 'city', 'state', 'postcode', 'country', 'shipping_address', 'shipping_city', 'shipping_postcode' ) as $field_key ) {
				if ( array_key_exists( $field_key, $contact ) ) {
					unset( $contact[ $field_key ] );
				}
			}
		}
		$answers['contact_billing'] = $contact;
		return $answers;
	}

	/** @param array<string, mixed> $answers */
	public static function set_step_answers( string $step_id, array $answers ): void {
		$flow = self::ensure_initialized();
		if ( empty( $flow ) ) {
			return;
		}
		$step_id = sanitize_key( $step_id );
		if ( '' === $step_id ) {
			return;
		}
		$storage_key             = self::normalize_answers_storage_key( $step_id );
		$current                 = isset( $flow['answers'] ) && is_array( $flow['answers'] ) ? $flow['answers'] : self::default_answers_structure();
		$current                 = self::merge_answers_with_defaults( $current );
		$sanitized_answers       = self::sanitize_recursive( $answers );
		if ( 'date_conditions' === $storage_key ) {
			$selected_date = isset( $sanitized_answers['selected_date'] ) ? (string) $sanitized_answers['selected_date'] : '';
			if ( '' === $selected_date ) {
				do_action( 'mp_custom_checkout_log', 'warning', '[date_sync] session_save_without_selected_date', array( 'step_id' => $step_id ) );
			}
		}
		$current[ $storage_key ] = $sanitized_answers;
		$flow['answers']         = $current;
		$flow['updated_at']      = time();
		self::persist_flow( $flow );
	}

	public static function clear_on_success( $order_id, $order ): void {
		unset( $order_id, $order );
		self::clear();
	}

	public static function clear_on_success_thankyou( $order_id ): void {
		unset( $order_id );
		self::clear();
	}

	public static function clear_on_abandoned_flow(): void {
		self::clear();
	}

	public static function maybe_clear_on_order_cancel(): void {
		if ( ! function_exists( 'is_wc_endpoint_url' ) ) {
			return;
		}
		if ( is_wc_endpoint_url( 'order-cancel' ) ) {
			self::clear();
		}
	}

	public static function clear(): void {
		$session = self::get_wc_session();
		if ( ! $session ) {
			return;
		}
		$session->set( self::SESSION_KEY, array() );
	}

	/** @return array<string, mixed> */
	private static function build_initial_flow(): array {
		$step_order = self::get_initial_step_order();
		$first_step = isset( $step_order[0] ) && is_string( $step_order[0] ) ? $step_order[0] : ScenarioStepRegistry::STEP_CART;
		$scenario   = self::get_initial_scenario();
		$flow       = array(
			'context_id'   => wp_generate_uuid4(),
			'created_at'   => time(),
			'updated_at'   => time(),
			'current_step' => $first_step,
			'step_order'   => $step_order,
			'scenario'     => $scenario,
			'snapshot'     => self::build_snapshot(),
			'answers'      => self::default_answers_structure(),
		);
		return (array) apply_filters( 'mp_custom_checkout_session_initial_flow', $flow );
	}

	/** @return array<string, mixed> */
	private static function build_snapshot(): array {
		$snapshot = array(
			'generated_at'    => time(),
			'currency'        => function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : '',
			'items_count'     => 0,
			'cart_hash'       => '',
			'subtotal'        => '',
			'total'           => '',
			'needs_shipping'  => false,
		);
		if ( function_exists( 'WC' ) && WC()->cart instanceof \WC_Cart ) {
			$cart = WC()->cart;
			$snapshot['items_count']    = (int) $cart->get_cart_contents_count();
			$snapshot['cart_hash']      = (string) $cart->get_cart_hash();
			$snapshot['subtotal']       = (string) $cart->get_cart_subtotal();
			$snapshot['total']          = (string) wc_price( (float) $cart->get_total( 'edit' ) );
			$snapshot['needs_shipping'] = (bool) $cart->needs_shipping();
		}
		return (array) apply_filters( 'mp_custom_checkout_session_snapshot', $snapshot );
	}

	private static function get_wc_session(): ?\WC_Session {
		if ( ! DependencyFailureGuard::is_woocommerce_integration_ready() ) {
			return null;
		}
		if ( ! function_exists( 'WC' ) || ! WC()->session instanceof \WC_Session ) {
			return null;
		}
		return WC()->session;
	}

	/** @return array<string, mixed> */
	private static function ensure_initialized(): array {
		$flow = self::get_flow();
		if ( ! empty( $flow ) ) {
			return $flow;
		}
		$flow = self::build_initial_flow();
		if ( empty( $flow ) ) {
			self::log_session_error( 'session_auto_init_failed' );
			return array();
		}
		self::persist_flow( $flow );
		return $flow;
	}

	/** @param array<string, mixed> $flow */
	private static function persist_flow( array $flow ): void {
		$session = self::get_wc_session();
		if ( ! $session ) {
			self::log_session_error( 'session_missing_on_persist' );
			return;
		}
		$session->set( self::SESSION_KEY, $flow );
	}

	/** @param mixed $value @return mixed */
	private static function sanitize_recursive( $value ) {
		if ( is_array( $value ) ) {
			$result = array();
			foreach ( $value as $key => $item ) {
				$clean_key = is_string( $key ) ? sanitize_key( $key ) : $key;
				$result[ $clean_key ] = self::sanitize_recursive( $item );
			}
			return $result;
		}
		if ( is_bool( $value ) || is_int( $value ) || is_float( $value ) ) {
			return $value;
		}
		if ( is_scalar( $value ) ) {
			return sanitize_text_field( (string) $value );
		}
		return '';
	}

	private static function log_session_error( string $code ): void {
		do_action( 'mp_custom_checkout_log', 'error', sprintf( '[checkout_session] %s', $code ), array( 'code' => $code ) );
	}

	/** @return array<int, string> */
	private static function get_initial_step_order(): array {
		$stored = SafeSettingsResolver::get( 'registry.step_order', ScenarioStepRegistry::default_step_order() );
		if ( ! is_array( $stored ) ) {
			return ScenarioStepRegistry::default_step_order();
		}
		$order = array();
		foreach ( $stored as $item ) {
			if ( ! is_string( $item ) ) {
				continue;
			}
			$key = sanitize_key( $item );
			if ( '' !== $key ) {
				$order[] = $key;
			}
		}
		$order = array_values( array_unique( $order ) );
		return ! empty( $order ) ? $order : ScenarioStepRegistry::default_step_order();
	}

	private static function get_initial_scenario(): string {
		$scenario = SafeSettingsResolver::get( 'step_2.default_scenario', '' );
		if ( ! is_string( $scenario ) || '' === $scenario ) {
			$scenario = SafeSettingsResolver::get( 'registry.default_scenario', ScenarioStepRegistry::SCENARIO_PICKUP );
		}
		$scenario = sanitize_key( is_string( $scenario ) ? $scenario : '' );
		$known    = array_keys( ScenarioStepRegistry::scenarios() );
		return in_array( $scenario, $known, true ) ? $scenario : ScenarioStepRegistry::SCENARIO_PICKUP;
	}

	/** @return array<string, mixed> */
	public static function get_public_state(): array {
		$flow = self::get_flow();
		if ( empty( $flow ) || self::is_flow_stale( $flow ) ) {
			return array();
		}
		$flow['answers'] = self::merge_answers_with_defaults( isset( $flow['answers'] ) && is_array( $flow['answers'] ) ? $flow['answers'] : array() );
		$flow['expires_at'] = isset( $flow['updated_at'] ) && is_numeric( $flow['updated_at'] ) ? ( (int) $flow['updated_at'] + self::FLOW_TTL_SECONDS ) : ( time() + self::FLOW_TTL_SECONDS );
		return $flow;
	}

	/** @param array<string, mixed> $flow */
	public static function validate_context_id( array $flow, string $context_id ): bool {
		$context_id = sanitize_text_field( $context_id );
		if ( '' === $context_id ) {
			return true;
		}
		$current = isset( $flow['context_id'] ) ? sanitize_text_field( (string) $flow['context_id'] ) : '';
		return '' !== $current && hash_equals( $current, $context_id );
	}

	/** @param array<string, mixed> $flow */
	private static function is_flow_stale( array $flow ): bool {
		if ( empty( $flow ) ) {
			return false;
		}
		$updated_at = isset( $flow['updated_at'] ) ? (int) $flow['updated_at'] : 0;
		if ( $updated_at <= 0 ) {
			return true;
		}
		return ( time() - $updated_at ) > self::FLOW_TTL_SECONDS;
	}

	/** @return array<string, mixed> */
	private static function default_answers_structure(): array {
		return array(
			'step_one'        => array(),
			'scenario'        => array(),
			'date_conditions' => array(),
			'contact_billing' => array(),
			'discounts'       => array(
				'coupons'   => array(),
				'gift_card' => array(),
			),
		);
	}

	/** @param array<string, mixed> $answers @return array<string, mixed> */
	private static function merge_answers_with_defaults( array $answers ): array {
		return array_replace_recursive( self::default_answers_structure(), $answers );
	}

	private static function normalize_answers_storage_key( string $step_id ): string {
		$step_id = sanitize_key( $step_id );
		if ( ScenarioStepRegistry::STEP_CART === $step_id ) {
			return 'step_one';
		}
		if ( ScenarioStepRegistry::STEP_DATE === $step_id || ScenarioStepRegistry::STEP_CONDITIONS === $step_id ) {
			return 'date_conditions';
		}
		if ( ScenarioStepRegistry::STEP_CONTACT_PAYMENT === $step_id ) {
			return 'contact_billing';
		}
		if ( 'scenario' === $step_id ) {
			return 'scenario';
		}
		if ( 'discounts' === $step_id || 'coupons' === $step_id || 'gift_card' === $step_id ) {
			return 'discounts';
		}
		return $step_id;
	}
}
