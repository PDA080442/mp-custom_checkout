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
			'map_widget'             => self::map_widget_config( isset( $config['map_widget'] ) && is_array( $config['map_widget'] ) ? $config['map_widget'] : array() ),
			'points'                 => self::points(),
		);
	}

	/**
	 * @param array<string, mixed> $map
	 * @return array<string, mixed>
	 */
	private static function map_widget_config( array $map ): array {
		$lat  = isset( $map['center_lat'] ) && is_numeric( $map['center_lat'] ) ? (float) $map['center_lat'] : 56.010563;
		$lng  = isset( $map['center_lng'] ) && is_numeric( $map['center_lng'] ) ? (float) $map['center_lng'] : 92.852572;
		$zoom = isset( $map['zoom'] ) ? (int) $map['zoom'] : 14;
		$zoom = max( 2, min( 19, $zoom ) );
		return array(
			'enabled'             => ! isset( $map['enabled'] ) || (bool) $map['enabled'],
			'provider'            => isset( $map['provider'] ) ? sanitize_key( (string) $map['provider'] ) : 'yandex',
			'api_key'             => isset( $map['api_key'] ) ? sanitize_text_field( (string) $map['api_key'] ) : '',
			'center_lat'          => $lat,
			'center_lng'          => $lng,
			'zoom'                => $zoom,
			'marker_label'        => isset( $map['marker_label'] ) ? sanitize_text_field( (string) $map['marker_label'] ) : 'Пункт самовывоза',
			'marker_hint'         => isset( $map['marker_hint'] ) ? sanitize_text_field( (string) $map['marker_hint'] ) : 'Заберите заказ в рабочие часы.',
			'fallback_title'      => isset( $map['fallback_title'] ) ? sanitize_text_field( (string) $map['fallback_title'] ) : 'Карта временно недоступна',
			'fallback_message'    => isset( $map['fallback_message'] ) ? sanitize_text_field( (string) $map['fallback_message'] ) : 'Посмотрите адрес пункта самовывоза выше и постройте маршрут в приложении карт.',
			'desktop_height'      => max( 160, min( 520, isset( $map['desktop_height'] ) ? (int) $map['desktop_height'] : 250 ) ),
			'mobile_height'       => max( 120, min( 420, isset( $map['mobile_height'] ) ? (int) $map['mobile_height'] : 190 ) ),
			'diagnostics_enabled' => ! isset( $map['diagnostics_enabled'] ) || (bool) $map['diagnostics_enabled'],
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

