<?php
/**
 * Хранилище логов checkout (option-based ring buffer).
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout\Logging;

defined( 'ABSPATH' ) || exit;

final class CheckoutLogStore {
	private const OPTION_KEY = 'mp_cc_checkout_logs';
	private const MAX_RECORDS = 1000;

	/**
	 * @param array<string, mixed> $record
	 */
	public static function append( array $record ): void {
		$rows   = self::all();
		$rows[] = $record;
		if ( count( $rows ) > self::MAX_RECORDS ) {
			$rows = array_slice( $rows, -1 * self::MAX_RECORDS );
		}
		update_option( self::OPTION_KEY, $rows, false );
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public static function all(): array {
		$value = get_option( self::OPTION_KEY, array() );
		return is_array( $value ) ? array_values( array_filter( $value, 'is_array' ) ) : array();
	}

	public static function clear(): void {
		update_option( self::OPTION_KEY, array(), false );
	}

	/**
	 * @param array<string, string> $filters
	 * @return array<int, array<string, mixed>>
	 */
	public static function query( array $filters = array() ): array {
		$rows  = array_reverse( self::all() );
		$level = isset( $filters['level'] ) ? sanitize_key( $filters['level'] ) : '';
		$source = isset( $filters['source'] ) ? sanitize_key( $filters['source'] ) : '';
		$channel = isset( $filters['channel'] ) ? sanitize_key( $filters['channel'] ) : '';
		$search = isset( $filters['search'] ) ? strtolower( sanitize_text_field( $filters['search'] ) ) : '';
		$event = isset( $filters['event_type'] ) ? sanitize_key( $filters['event_type'] ) : '';

		return array_values(
			array_filter(
				$rows,
				static function ( $row ) use ( $level, $source, $channel, $search, $event ): bool {
					if ( ! is_array( $row ) ) {
						return false;
					}
					if ( '' !== $level && (string) ( $row['level'] ?? '' ) !== $level ) {
						return false;
					}
					if ( '' !== $source && (string) ( $row['source'] ?? '' ) !== $source ) {
						return false;
					}
					if ( '' !== $channel && (string) ( $row['channel'] ?? '' ) !== $channel ) {
						return false;
					}
					if ( '' !== $event && (string) ( $row['event_type'] ?? '' ) !== $event ) {
						return false;
					}
					if ( '' !== $search ) {
						$haystack = strtolower( wp_json_encode( $row ) ?: '' );
						if ( false === strpos( $haystack, $search ) ) {
							return false;
						}
					}
					return true;
				}
			)
		);
	}
}

