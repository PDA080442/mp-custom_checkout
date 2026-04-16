<?php
/**
 * Health checks для служебной диагностики checkout.
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout\Diagnostics;

use MP\CustomCheckout\Logging\CheckoutLogStore;
use MP\CustomCheckout\Settings\FeatureFlagResolver;

defined( 'ABSPATH' ) || exit;

final class CheckoutHealthChecks {
	/**
	 * @return array<int, array<string, string>>
	 */
	public static function collect(): array {
		$rows = array();
		$rows[] = self::row(
			'WooCommerce integration',
			( function_exists( 'WC' ) && WC() ) ? 'ok' : 'fail',
			( function_exists( 'WC' ) && WC() ) ? 'WooCommerce доступен.' : 'WooCommerce недоступен.'
		);
		$rows[] = self::row(
			'Checkout testing mode',
			FeatureFlagResolver::is_enabled( 'checkout_testing_mode', false ) ? 'warn' : 'ok',
			FeatureFlagResolver::is_enabled( 'checkout_testing_mode', false ) ? 'Включен тестовый режим оплаты.' : 'Тестовый режим отключен.'
		);
		$logs = CheckoutLogStore::all();
		$critical = 0;
		foreach ( $logs as $log ) {
			if ( isset( $log['channel'] ) && 'critical' === (string) $log['channel'] ) {
				$critical++;
			}
		}
		$rows[] = self::row(
			'Critical checkout logs',
			$critical > 0 ? 'warn' : 'ok',
			$critical > 0 ? sprintf( 'Найдено критических логов: %d', $critical ) : 'Критические логи не обнаружены.'
		);
		$rows[] = self::row(
			'AJAX endpoint',
			has_action( 'wp_ajax_mp_cc_checkout' ) ? 'ok' : 'fail',
			has_action( 'wp_ajax_mp_cc_checkout' ) ? 'AJAX endpoint зарегистрирован.' : 'AJAX endpoint не зарегистрирован.'
		);
		return $rows;
	}

	/**
	 * @return array<string, int>
	 */
	public static function summary(): array {
		$checks = self::collect();
		$summary = array(
			'ok' => 0,
			'warn' => 0,
			'fail' => 0,
			'total' => count( $checks ),
		);
		foreach ( $checks as $row ) {
			$status = isset( $row['status'] ) ? (string) $row['status'] : 'ok';
			if ( ! isset( $summary[ $status ] ) ) {
				continue;
			}
			$summary[ $status ]++;
		}
		return $summary;
	}

	/**
	 * @return array<string, string>
	 */
	private static function row( string $name, string $status, string $message ): array {
		return array(
			'name'    => $name,
			'status'  => $status,
			'message' => $message,
		);
	}
}

