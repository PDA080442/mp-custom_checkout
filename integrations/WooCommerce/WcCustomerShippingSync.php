<?php
/**
 * Синхронизация контакта/адреса checkout с WC customer и пересчёт корзины (§28.3).
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout\Integrations\WooCommerce;

use MP\CustomCheckout\Checkout\Hooks\OrderMetaHooks;
use MP\CustomCheckout\Checkout\Routing\CheckoutSessionService;
use MP\CustomCheckout\DependencyFailureGuard;
use MP\CustomCheckout\Routing\CheckoutScenarioRules;
use MP\CustomCheckout\Settings\SafeSettingsResolver;
use MP\CustomCheckout\Settings\ScenarioStepRegistry;

defined( 'ABSPATH' ) || exit;

/**
 * Синхронизация адреса с WC Customer и пересчёт корзины при сохранении шагов checkout.
 *
 * Выполняется при сохранении шага (в т.ч. в режиме «catalog»): переносим адрес в WC Customer и вызываем
 * {@see \WC_Cart::calculate_totals()}, чтобы плагины доставки посчитали пакеты и ставки для снимка в
 * {@see CheckoutRouteContext::get_cart_data()}. Как именно в итоге смешиваются каталог и WC — см.
 * {@see CheckoutRouteContext::get_cart_data()} и {@see self::is_woocommerce_pricing_mode()}.
 */
final class WcCustomerShippingSync {

	private const LOG_PREFIX = '[wc_customer_shipping_sync]';

	public const PRICING_MODE_WC = 'woocommerce';

	public static function is_woocommerce_pricing_mode(): bool {
		$mode = SafeSettingsResolver::get( 'delivery.pricing_mode', 'catalog' );

		return self::PRICING_MODE_WC === sanitize_key( (string) $mode );
	}

	public static function after_session_set_answers( string $step_id ): void {
		self::run( 'session_set_answers', sanitize_key( $step_id ) );
	}

	public static function after_session_set_scenario(): void {
		self::run( 'session_set_scenario', '' );
	}

	/**
	 * Полная синхронизация WC_Customer / сессии доставки СДЭК и пересчёт корзины перед созданием заказа из MP.
	 */
	public static function before_create_order_from_cart(): void {
		self::sync_customer_from_flow_and_recalculate_cart();
	}

	private static function run( string $reason, string $step_id ): void {
		if ( ! DependencyFailureGuard::is_woocommerce_integration_ready() ) {
			return;
		}
		if ( 'session_set_answers' === $reason && ! self::step_triggers_resync( $step_id ) ) {
			return;
		}
		self::sync_customer_from_flow_and_recalculate_cart();
	}

	private static function sync_customer_from_flow_and_recalculate_cart(): void {
		if ( ! function_exists( 'WC' ) ) {
			return;
		}
		$wc = WC();
		if ( ! $wc->customer instanceof \WC_Customer || ! $wc->cart instanceof \WC_Cart ) {
			return;
		}
		$flow = CheckoutSessionService::get_flow();
		if ( empty( $flow ) || ! is_array( $flow ) ) {
			return;
		}
		$answers  = isset( $flow['answers'] ) && is_array( $flow['answers'] ) ? $flow['answers'] : array();
		$contact  = self::merge_contact_from_answers( $answers );
		$scenario = isset( $flow['scenario'] ) ? CheckoutScenarioRules::sanitize_scenario( (string) $flow['scenario'] ) : ScenarioStepRegistry::SCENARIO_PICKUP;
		$merged_delivery = array_replace(
			isset( $answers['step_one'] ) && is_array( $answers['step_one'] ) ? $answers['step_one'] : array(),
			isset( $answers['date_conditions'] ) && is_array( $answers['date_conditions'] ) ? $answers['date_conditions'] : array()
		);
		$scenario = CheckoutScenarioRules::elevate_scenario_if_pickup_but_carrier_method_selected( $scenario, $merged_delivery );

		OrderMetaHooks::apply_contact_location_to_customer( $wc->customer, $scenario, $contact );
		$wc->customer->save();

		CdekWcSessionBridge::sync_session_before_cart_totals();
		$wc->cart->calculate_totals();

		self::log_if_chosen_shipping_not_in_packages();
	}

	private static function step_triggers_resync( string $step_id ): bool {
		return in_array(
			$step_id,
			array(
				ScenarioStepRegistry::STEP_ADDRESS_DELIVERY,
				ScenarioStepRegistry::STEP_RECIPIENT,
				ScenarioStepRegistry::STEP_PAYMENT,
				ScenarioStepRegistry::STEP_CONFIRM,
				'contact_payment',
				'date',
				'conditions',
				'scenario',
			),
			true
		);
	}

	/**
	 * Как на заказе: приоритет у contact_billing над полями из step_one (address_delivery).
	 *
	 * @param array<string, mixed> $answers
	 * @return array<string, mixed>
	 */
	private static function merge_contact_from_answers( array $answers ): array {
		$step_one = isset( $answers['step_one'] ) && is_array( $answers['step_one'] ) ? $answers['step_one'] : array();
		$billing  = isset( $answers['contact_billing'] ) && is_array( $answers['contact_billing'] ) ? $answers['contact_billing'] : array();

		return array_merge( $step_one, $billing );
	}

	private static function log_if_chosen_shipping_not_in_packages(): void {
		if ( ! WC()->session ) {
			return;
		}
		$chosen   = (array) WC()->session->get( 'chosen_shipping_methods' );
		$packages = WC()->shipping()->get_packages();
		foreach ( $packages as $i => $package ) {
			if ( ! is_array( $package ) ) {
				continue;
			}
			$rates = isset( $package['rates'] ) && is_array( $package['rates'] ) ? $package['rates'] : array();
			$ids   = array_keys( $rates );
			$pick  = isset( $chosen[ $i ] ) ? (string) $chosen[ $i ] : '';
			if ( '' === $pick ) {
				continue;
			}
			if ( isset( $rates[ $pick ] ) ) {
				continue;
			}
			do_action(
				'mp_custom_checkout_log',
				'warning',
				self::LOG_PREFIX . ' chosen_method_not_in_rates',
				array(
					'package_index'    => (int) $i,
					'chosen_method_id' => $pick,
					'rate_id_count'    => count( $ids ),
					'rate_id_sample'   => array_slice( array_map( 'strval', $ids ), 0, 15 ),
				)
			);
		}
	}
}
