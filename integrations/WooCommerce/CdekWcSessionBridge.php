<?php
/**
 * Синхронизация WC-сессии СДЭК (official_cdek) и chosen_shipping_methods с ответами MP checkout (§28 / план CDEK).
 *
 * Карта полей ПВЗ и жизненный цикл: `docs/pvz-data-contract.md` (§29.1).
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout\Integrations\WooCommerce;

use MP\CustomCheckout\Checkout\Routing\CheckoutSessionService;
use MP\CustomCheckout\DependencyFailureGuard;
use MP\CustomCheckout\Settings\SafeSettingsResolver;

defined( 'ABSPATH' ) || exit;

/**
 * Пишет в {@see \WC()->session} то же, что на классическом checkout даёт плагин СДЭК:
 * `official_cdek_office_code`, `chosen_shipping_methods[0]` = полный id ставки (`official_cdek:<instance>`).
 */
final class CdekWcSessionBridge {

	public const SESSION_OFFICE_KEY = 'official_cdek_office_code';

	public const OFFICIAL_CDEK_PREFIX = 'official_cdek:';

	/**
	 * @param array<string, mixed> $flow
	 * @return array<string, mixed>
	 */
	public static function get_merged_delivery_answers( array $flow ): array {
		$answers = isset( $flow['answers'] ) && is_array( $flow['answers'] ) ? $flow['answers'] : array();
		$step    = isset( $answers['step_one'] ) && is_array( $answers['step_one'] ) ? $answers['step_one'] : array();
		$date    = isset( $answers['date_conditions'] ) && is_array( $answers['date_conditions'] ) ? $answers['date_conditions'] : array();
		// Код ПВЗ — только из step_one; legacy в date_conditions не должен перекрывать очистку (§29.1 deep-audit).
		unset( $date['cdek_office_code'] );

		return array_replace( $step, $date );
	}

	public static function resolve_wc_rate_id_from_catalog( string $method_id, string $tariff_id ): string {
		$method_id = sanitize_key( $method_id );
		$tariff_id = sanitize_key( $tariff_id );
		$delivery  = SafeSettingsResolver::get_section( 'delivery' );
		$catalog   = isset( $delivery['shipping_catalog'] ) && is_array( $delivery['shipping_catalog'] ) ? $delivery['shipping_catalog'] : array();
		$methods   = isset( $catalog['methods'] ) && is_array( $catalog['methods'] ) ? $catalog['methods'] : array();
		if ( '' === $method_id || ! isset( $methods[ $method_id ] ) || ! is_array( $methods[ $method_id ] ) ) {
			return '';
		}
		$row = $methods[ $method_id ];
		if ( '' !== $tariff_id && ! empty( $row['tariffs'] ) && is_array( $row['tariffs'] ) ) {
			if ( isset( $row['tariffs'][ $tariff_id ] ) && is_array( $row['tariffs'][ $tariff_id ] ) ) {
				$t = trim( (string) ( $row['tariffs'][ $tariff_id ]['wc_rate_id'] ?? '' ) );
				if ( '' !== $t ) {
					return $t;
				}
			}
		}

		return trim( (string) ( $row['wc_rate_id'] ?? '' ) );
	}

	/**
	 * Вызывать до {@see \WC_Cart::calculate_totals()} после обновления WC_Customer / flow.
	 */
	public static function sync_session_before_cart_totals(): void {
		if ( ! DependencyFailureGuard::is_woocommerce_integration_ready() ) {
			return;
		}
		if ( ! function_exists( 'WC' ) || ! WC()->session instanceof \WC_Session || ! WC()->cart instanceof \WC_Cart ) {
			return;
		}
		$session = WC()->session;
		$cart    = WC()->cart;
		$flow    = CheckoutSessionService::get_flow();
		if ( empty( $flow ) || ! is_array( $flow ) ) {
			return;
		}
		$delivery   = self::get_merged_delivery_answers( $flow );
		$method_id  = isset( $delivery['shipping_method_id'] ) ? sanitize_key( (string) $delivery['shipping_method_id'] ) : '';
		$tariff_id  = isset( $delivery['shipping_tariff_id'] ) ? sanitize_key( (string) $delivery['shipping_tariff_id'] ) : '';
		$office_raw = isset( $delivery['cdek_office_code'] ) ? (string) $delivery['cdek_office_code'] : '';
		$office     = sanitize_text_field( $office_raw );

		$rate_id = self::resolve_wc_rate_id_from_catalog( $method_id, $tariff_id );
		$is_cdek = ( '' !== $rate_id && 0 === strpos( $rate_id, self::OFFICIAL_CDEK_PREFIX ) );

		$target_office = ( 'pvz' === $method_id && $is_cdek && '' !== $office ) ? $office : '';
		$current_office = (string) $session->get( self::SESSION_OFFICE_KEY, '' );
		if ( $current_office !== $target_office ) {
			$session->set( self::SESSION_OFFICE_KEY, $target_office );
		}

		if ( ! $cart->needs_shipping() ) {
			return;
		}

		$chosen = (array) $session->get( 'chosen_shipping_methods', array() );
		if ( '' === $rate_id ) {
			if ( array_key_exists( 0, $chosen ) ) {
				unset( $chosen[0] );
				$session->set( 'chosen_shipping_methods', $chosen );
			}

			return;
		}
		$current_rate = isset( $chosen[0] ) ? (string) $chosen[0] : '';
		if ( $current_rate !== $rate_id ) {
			$chosen[0] = $rate_id;
			$session->set( 'chosen_shipping_methods', $chosen );
		}
	}
}
