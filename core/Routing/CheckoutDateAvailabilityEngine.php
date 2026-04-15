<?php
/**
 * Серверный движок расчета доступных дат доставки/самовывоза.
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout\Routing;

use MP\CustomCheckout\Settings\SafeSettingsResolver;
use MP\CustomCheckout\Settings\ScenarioStepRegistry;

defined( 'ABSPATH' ) || exit;

final class CheckoutDateAvailabilityEngine {

	/**
	 * @return array<string, mixed>
	 */
	public static function build_rules( string $scenario ): array {
		$scenario = CheckoutScenarioRules::sanitize_scenario( $scenario );
		$settings = SafeSettingsResolver::get_section( 'step_3' );
		$timezone = wp_timezone();
		$today    = ( new \DateTimeImmutable( 'now', $timezone ) )->setTime( 0, 0, 0 );

		$lead_time_days = self::lead_time_days( $scenario, $settings );
		$max_days_ahead = self::max_days_ahead( $scenario, $settings );
		$min_date       = $today->modify( '+' . $lead_time_days . ' days' );
		$max_date       = $today->modify( '+' . $max_days_ahead . ' days' );

		$allowed_weekdays = self::allowed_weekdays( $scenario, $settings );
		$blocked_dates    = self::blocked_dates( $settings );
		$available_dates  = self::available_dates( $min_date, $max_date, $allowed_weekdays, $blocked_dates );

		return array(
			'mode'             => self::mode( $scenario ),
			'lead_time_days'   => $lead_time_days,
			'allow_weekends'   => in_array( 0, $allowed_weekdays, true ) || in_array( 6, $allowed_weekdays, true ),
			'max_days_ahead'   => $max_days_ahead,
			'min_date'         => $min_date->format( 'Y-m-d' ),
			'max_date'         => $max_date->format( 'Y-m-d' ),
			'allowed_weekdays' => array_values( $allowed_weekdays ),
			'blocked_dates'    => array_values( $blocked_dates ),
			'available_dates'  => $available_dates,
			'diagnostics'      => array(
				'scenario'              => $scenario,
				'timezone'              => wp_timezone_string(),
				'total_available_dates' => count( $available_dates ),
				'total_blocked_dates'   => count( $blocked_dates ),
				'source'                => 'server_engine_v1',
			),
		);
	}

	private static function mode( string $scenario ): string {
		if ( ScenarioStepRegistry::SCENARIO_PICKUP === $scenario ) {
			return 'pickup_slots';
		}
		if ( ScenarioStepRegistry::SCENARIO_KRASNOYARSK_DELIVERY === $scenario ) {
			return 'city_delivery_slots';
		}
		return 'intercity_delivery_windows';
	}

	private static function lead_time_days( string $scenario, array $settings ): int {
		// Same-day и прошедшие даты запрещены всегда: минимум +1 день.
		$configured = isset( $settings['min_lead_time_days'][ $scenario ] ) ? (int) $settings['min_lead_time_days'][ $scenario ] : 0;
		if ( $configured > 0 ) {
			return max( 1, $configured );
		}
		return ScenarioStepRegistry::SCENARIO_OTHER_CITY_DELIVERY === $scenario ? 2 : 1;
	}

	private static function max_days_ahead( string $scenario, array $settings ): int {
		$configured = isset( $settings['max_days_ahead'][ $scenario ] ) ? (int) $settings['max_days_ahead'][ $scenario ] : 0;
		if ( $configured > 0 ) {
			return $configured;
		}
		if ( ScenarioStepRegistry::SCENARIO_PICKUP === $scenario ) {
			return 14;
		}
		if ( ScenarioStepRegistry::SCENARIO_KRASNOYARSK_DELIVERY === $scenario ) {
			return 21;
		}
		return 30;
	}

	/**
	 * @return array<int, int>
	 */
	private static function allowed_weekdays( string $scenario, array $settings ): array {
		$defaults = array(
			ScenarioStepRegistry::SCENARIO_PICKUP              => array( 1, 2, 3, 4, 5, 6 ),
			ScenarioStepRegistry::SCENARIO_KRASNOYARSK_DELIVERY => array( 1, 2, 3, 4, 5, 6 ),
			ScenarioStepRegistry::SCENARIO_OTHER_CITY_DELIVERY  => array( 1, 3, 5 ),
		);
		$raw      = isset( $settings['weekday_rules'][ $scenario ] ) && is_array( $settings['weekday_rules'][ $scenario ] )
			? $settings['weekday_rules'][ $scenario ]
			: ( $defaults[ $scenario ] ?? array( 1, 2, 3, 4, 5 ) );

		$result = array();
		foreach ( $raw as $value ) {
			$day = is_numeric( $value ) ? (int) $value : -1;
			if ( $day < 0 || $day > 6 ) {
				continue;
			}
			$result[] = $day;
		}
		$result = array_values( array_unique( $result ) );
		sort( $result );

		if ( empty( $result ) ) {
			return array( 1, 2, 3, 4, 5 );
		}

		return $result;
	}

	/**
	 * @return array<int, string>
	 */
	private static function blocked_dates( array $settings ): array {
		$raw      = array();
		if ( isset( $settings['holiday_dates'] ) && is_array( $settings['holiday_dates'] ) ) {
			$raw = array_merge( $raw, $settings['holiday_dates'] );
		}
		if ( isset( $settings['closed_dates'] ) && is_array( $settings['closed_dates'] ) ) {
			$raw = array_merge( $raw, $settings['closed_dates'] );
		}

		$result = array();
		foreach ( $raw as $value ) {
			$date = self::normalize_date( is_scalar( $value ) ? (string) $value : '' );
			if ( '' !== $date ) {
				$result[] = $date;
			}
		}

		$result = array_values( array_unique( $result ) );
		sort( $result );
		return $result;
	}

	/**
	 * @param array<int, int>    $allowed_weekdays
	 * @param array<int, string> $blocked_dates
	 * @return array<int, string>
	 */
	private static function available_dates( \DateTimeImmutable $min_date, \DateTimeImmutable $max_date, array $allowed_weekdays, array $blocked_dates ): array {
		$blocked_map = array_fill_keys( $blocked_dates, true );
		$result      = array();
		$cursor      = $min_date;

		while ( $cursor <= $max_date ) {
			$weekday = (int) $cursor->format( 'w' );
			$iso     = $cursor->format( 'Y-m-d' );
			if ( in_array( $weekday, $allowed_weekdays, true ) && ! isset( $blocked_map[ $iso ] ) ) {
				$result[] = $iso;
			}
			$cursor = $cursor->modify( '+1 day' );
		}

		return $result;
	}

	private static function normalize_date( string $raw ): string {
		$raw = trim( $raw );
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $raw ) ) {
			return '';
		}
		$parts = explode( '-', $raw );
		if ( 3 !== count( $parts ) ) {
			return '';
		}
		$year  = (int) $parts[0];
		$month = (int) $parts[1];
		$day   = (int) $parts[2];
		if ( ! checkdate( $month, $day, $year ) ) {
			return '';
		}
		return sprintf( '%04d-%02d-%02d', $year, $month, $day );
	}
}

