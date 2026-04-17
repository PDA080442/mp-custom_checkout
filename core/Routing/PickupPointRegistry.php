<?php
/**
 * Модель точек самовывоза (single сейчас, multi-ready архитектура).
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout\Routing;

use MP\CustomCheckout\Settings\SafeSettingsResolver;

defined( 'ABSPATH' ) || exit;

final class PickupPointRegistry {

	/**
	 * @return array<string, mixed>
	 */
	public static function config(): array {
		$config = SafeSettingsResolver::get_section( 'pickup' );
		if ( empty( $config ) ) {
			$config = array();
		}

		return array(
			'enable_point_selection' => ! empty( $config['enable_point_selection'] ),
			'map_slot_enabled'       => ! isset( $config['map_slot_enabled'] ) || (bool) $config['map_slot_enabled'],
			'points'                 => self::points(),
		);
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public static function points(): array {
		$config = SafeSettingsResolver::get_section( 'pickup' );
		$points = isset( $config['points'] ) && is_array( $config['points'] ) ? $config['points'] : array();

		$normalized = array();
		foreach ( $points as $point ) {
			if ( ! is_array( $point ) ) {
				continue;
			}
			$id = isset( $point['id'] ) ? sanitize_key( (string) $point['id'] ) : '';
			if ( '' === $id ) {
				continue;
			}
			$normalized[] = array(
				'id'          => $id,
				'title'       => isset( $point['title'] ) ? sanitize_text_field( (string) $point['title'] ) : $id,
				'address'     => isset( $point['address'] ) ? sanitize_text_field( (string) $point['address'] ) : '',
				'description' => isset( $point['description'] ) ? sanitize_text_field( (string) $point['description'] ) : '',
				'map_hint'    => isset( $point['map_hint'] ) ? sanitize_text_field( (string) $point['map_hint'] ) : '',
			);
		}

		if ( empty( $normalized ) ) {
			$normalized[] = array(
				'id'          => 'pickup_main',
				'title'       => 'Основная точка самовывоза',
				'address'     => 'г. Красноярск, ул. Примерная, 1',
				'description' => 'Ежедневно с 10:00 до 20:00',
				'map_hint'    => 'Слот карты/схемы будет подключен здесь.',
			);
		}

		return array_values( $normalized );
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function default_point(): array {
		$points = self::points();
		return isset( $points[0] ) && is_array( $points[0] ) ? $points[0] : array();
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function find_by_id( string $id ): array {
		$id = sanitize_key( $id );
		if ( '' === $id ) {
			return self::default_point();
		}

		foreach ( self::points() as $point ) {
			if ( isset( $point['id'] ) && $id === sanitize_key( (string) $point['id'] ) ) {
				return $point;
			}
		}

		return self::default_point();
	}
}

