<?php
/**
 * Миграции структуры опций между версиями схемы настроек.
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Class SettingsMigrationManager
 */
final class SettingsMigrationManager {

	/**
	 * @var array<string, callable(array):array>
	 */
	private static $migrations = array();

	/**
	 * Регистрирует миграцию после версии схемы в БД `$after_version` (переход к следующей версии).
	 *
	 * @param string   $after_version Версия, с которой начинается применение (например `0` → переход к `1`).
	 * @param callable $callback      function( array $settings ): array
	 */
	public static function register_migration( string $after_version, callable $callback ): void {
		self::$migrations[ $after_version ] = $callback;
	}

	/**
	 * Выполняет миграции до {@see OptionKeys::SETTINGS_SCHEMA_VERSION} и синхронизирует опции.
	 */
	public static function maybe_migrate(): void {
		$stored_version = get_option( OptionKeys::DB_VERSION, '0' );
		if ( ! is_string( $stored_version ) && ! is_numeric( $stored_version ) ) {
			$stored_version = '0';
		}
		$stored_version = (string) $stored_version;
		if ( '' === $stored_version ) {
			$stored_version = '0';
		}

		$target = OptionKeys::SETTINGS_SCHEMA_VERSION;
		if ( version_compare( $stored_version, $target, '>=' ) ) {
			return;
		}

		self::register_builtin_migrations();

		$settings = get_option( OptionKeys::MAIN, array() );
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		$cursor = $stored_version;
		while ( version_compare( $cursor, $target, '<' ) ) {
			if ( isset( self::$migrations[ $cursor ] ) ) {
				$settings = call_user_func( self::$migrations[ $cursor ], $settings );
				if ( ! is_array( $settings ) ) {
					$settings = array();
				}
			}
			$next = self::bump_schema_version( $cursor );
			if ( null === $next ) {
				break;
			}
			$cursor = $next;
		}

		$defaults = SafeSettingsResolver::get_defaults_tree();
		$settings = array_replace_recursive( $defaults, $settings );

		update_option( OptionKeys::MAIN, $settings, false );
		update_option( OptionKeys::DB_VERSION, $target, false );

		SafeSettingsResolver::clear_cache();
	}

	/**
	 * Встроенные миграции (добавлять при выпуске новой SETTINGS_SCHEMA_VERSION).
	 */
	private static function register_builtin_migrations(): void {
		if ( array_key_exists( '0', self::$migrations ) ) {
			return;
		}

		self::$migrations['0'] = static function ( array $settings ): array {
			return $settings;
		};

		self::$migrations['1'] = static function ( array $settings ): array {
			$map = array(
				'cart'            => ScenarioStepRegistry::STEP_ADDRESS_DELIVERY,
				'date'            => ScenarioStepRegistry::STEP_ADDRESS_DELIVERY,
				'conditions'      => ScenarioStepRegistry::STEP_CONFIRM,
				'contact_payment' => ScenarioStepRegistry::STEP_RECIPIENT,
			);
			$registry = isset( $settings[ OptionKeys::KEY_REGISTRY ] ) && is_array( $settings[ OptionKeys::KEY_REGISTRY ] )
				? $settings[ OptionKeys::KEY_REGISTRY ]
				: array();
			$order = isset( $registry['step_order'] ) && is_array( $registry['step_order'] ) ? $registry['step_order'] : array();
			$normalized = array();
			foreach ( $order as $raw_step ) {
				if ( ! is_string( $raw_step ) ) {
					continue;
				}
				$step = sanitize_key( $raw_step );
				if ( isset( $map[ $step ] ) ) {
					$step = $map[ $step ];
				}
				if ( '' !== $step ) {
					$normalized[] = $step;
				}
			}
			$normalized = array_values( array_unique( $normalized ) );
			$registry['step_order'] = ! empty( $normalized ) ? $normalized : ScenarioStepRegistry::default_step_order();
			$registry['step_definitions'] = ScenarioStepRegistry::step_definitions();
			$settings[ OptionKeys::KEY_REGISTRY ] = $registry;
			return $settings;
		};

		self::$migrations['2'] = static function ( array $settings ): array {
			$delivery = isset( $settings[ OptionKeys::SECTION_DELIVERY ] ) && is_array( $settings[ OptionKeys::SECTION_DELIVERY ] )
				? $settings[ OptionKeys::SECTION_DELIVERY ]
				: array();
			$catalog = isset( $delivery['shipping_catalog'] ) && is_array( $delivery['shipping_catalog'] ) ? $delivery['shipping_catalog'] : array();
			$methods = isset( $catalog['methods'] ) && is_array( $catalog['methods'] ) ? $catalog['methods'] : array();

			$target_prices = array(
				'post_russia' => array( 'price' => 321, 'eta' => '' ),
				'courier'     => array( 'price' => 375, 'eta' => '2 дней', 'tariffs' => array(
					'express'  => array( 'price' => 550, 'eta' => '2 дней' ),
					'standard' => array( 'price' => 375, 'eta' => '2 дней' ),
				) ),
				'pvz'         => array( 'price' => 185, 'eta' => '2 дней', 'tariffs' => array(
					'express'  => array( 'price' => 360, 'eta' => '2 дней' ),
					'standard' => array( 'price' => 185, 'eta' => '2 дней' ),
				) ),
				'krasnoyarsk_delivery' => array( 'price' => 400 ),
				'pickup'      => array( 'price' => 0 ),
			);

			foreach ( $target_prices as $method_id => $fields ) {
				if ( ! isset( $methods[ $method_id ] ) || ! is_array( $methods[ $method_id ] ) ) {
					continue;
				}
				foreach ( $fields as $field_key => $field_value ) {
					if ( 'tariffs' === $field_key && is_array( $field_value ) ) {
						$existing_tariffs = isset( $methods[ $method_id ]['tariffs'] ) && is_array( $methods[ $method_id ]['tariffs'] )
							? $methods[ $method_id ]['tariffs']
							: array();
						foreach ( $field_value as $tariff_id => $tariff_fields ) {
							if ( ! isset( $existing_tariffs[ $tariff_id ] ) || ! is_array( $existing_tariffs[ $tariff_id ] ) ) {
								continue;
							}
							foreach ( $tariff_fields as $tkey => $tval ) {
								$existing_tariffs[ $tariff_id ][ $tkey ] = $tval;
							}
						}
						$methods[ $method_id ]['tariffs'] = $existing_tariffs;
						continue;
					}
					$methods[ $method_id ][ $field_key ] = $field_value;
				}
			}

			$catalog['methods'] = $methods;
			$delivery['shipping_catalog'] = $catalog;
			$settings[ OptionKeys::SECTION_DELIVERY ] = $delivery;

			$pickup = isset( $settings[ OptionKeys::SECTION_PICKUP ] ) && is_array( $settings[ OptionKeys::SECTION_PICKUP ] )
				? $settings[ OptionKeys::SECTION_PICKUP ]
				: array();
			$points = isset( $pickup['points'] ) && is_array( $pickup['points'] ) ? $pickup['points'] : array();
			if ( isset( $points[0] ) && is_array( $points[0] ) ) {
				$points[0]['address'] = 'г. Красноярск, ул. Маерчака, д. 10, оф. 17-13';
			}
			$pickup['points'] = $points;
			$settings[ OptionKeys::SECTION_PICKUP ] = $pickup;

			return $settings;
		};

		self::$migrations['3'] = static function ( array $settings ): array {
			$delivery = isset( $settings[ OptionKeys::SECTION_DELIVERY ] ) && is_array( $settings[ OptionKeys::SECTION_DELIVERY ] )
				? $settings[ OptionKeys::SECTION_DELIVERY ]
				: array();
			$catalog = isset( $delivery['shipping_catalog'] ) && is_array( $delivery['shipping_catalog'] ) ? $delivery['shipping_catalog'] : array();
			$methods = isset( $catalog['methods'] ) && is_array( $catalog['methods'] ) ? $catalog['methods'] : array();
			if ( isset( $methods['pickup'] ) && is_array( $methods['pickup'] ) ) {
				$vis = isset( $methods['pickup']['visibility_scenarios'] ) && is_array( $methods['pickup']['visibility_scenarios'] )
					? $methods['pickup']['visibility_scenarios']
					: array();
				$vis = array_values( array_filter( array_map( 'sanitize_key', $vis ) ) );
				// Раньше самовывоз был только в сценарии pickup — на шаге «город» метод не показывался.
				if ( array( 'pickup' ) === $vis ) {
					$methods['pickup']['visibility_scenarios'] = array( 'pickup', 'krasnoyarsk_delivery', 'other_city_delivery' );
				}
			}
			$catalog['methods'] = $methods;
			$delivery['shipping_catalog'] = $catalog;
			$settings[ OptionKeys::SECTION_DELIVERY ] = $delivery;

			return $settings;
		};

		self::$migrations['4'] = static function ( array $settings ): array {
			$registry = isset( $settings[ OptionKeys::KEY_REGISTRY ] ) && is_array( $settings[ OptionKeys::KEY_REGISTRY ] )
				? $settings[ OptionKeys::KEY_REGISTRY ]
				: array();
			$order = isset( $registry['step_order'] ) && is_array( $registry['step_order'] ) ? $registry['step_order'] : array();
			$normalized = array();
			foreach ( $order as $raw_step ) {
				if ( ! is_string( $raw_step ) ) {
					continue;
				}
				$step = sanitize_key( $raw_step );
				if ( '' !== $step ) {
					$normalized[] = $step;
				}
			}
			$normalized = array_values( array_unique( $normalized ) );

			$idx_confirm   = array_search( ScenarioStepRegistry::STEP_CONFIRM, $normalized, true );
			$idx_recipient = array_search( ScenarioStepRegistry::STEP_RECIPIENT, $normalized, true );
			$idx_payment   = array_search( ScenarioStepRegistry::STEP_PAYMENT, $normalized, true );

			// Подтверждение должно быть после адреса получателя и оплаты.
			$confirm_too_early =
				false !== $idx_confirm
				&& (
					( false !== $idx_recipient && (int) $idx_confirm < (int) $idx_recipient )
					|| ( false !== $idx_payment && (int) $idx_confirm < (int) $idx_payment )
				);

			if ( $confirm_too_early ) {
				$normalized = ScenarioStepRegistry::default_step_order();
			}

			$registry['step_order'] = ! empty( $normalized ) ? $normalized : ScenarioStepRegistry::default_step_order();
			$settings[ OptionKeys::KEY_REGISTRY ] = $registry;

			return $settings;
		};

		self::$migrations['5'] = static function ( array $settings ): array {
			$s4 = isset( $settings[ OptionKeys::SECTION_STEP_4 ] ) && is_array( $settings[ OptionKeys::SECTION_STEP_4 ] )
				? $settings[ OptionKeys::SECTION_STEP_4 ]
				: array();
			$cb = isset( $s4['contact_block'] ) && is_array( $s4['contact_block'] ) ? $s4['contact_block'] : array();
			$cur = isset( $cb['phone_country_codes'] ) && is_array( $cb['phone_country_codes'] ) ? $cb['phone_country_codes'] : array();
			$by_iso = array();
			foreach ( $cur as $row ) {
				if ( is_array( $row ) && isset( $row['iso'] ) ) {
					$iso_key = strtoupper( (string) $row['iso'] );
					if ( '' !== $iso_key ) {
						$by_iso[ $iso_key ] = $row;
					}
				}
			}
			$defaults = SafeSettingsResolver::default_phone_country_codes();
			$out        = array();
			foreach ( $defaults as $def ) {
				if ( ! is_array( $def ) ) {
					continue;
				}
				$iso = strtoupper( (string) ( $def['iso'] ?? '' ) );
				if ( isset( $by_iso[ $iso ] ) && is_array( $by_iso[ $iso ] ) ) {
					$merged = array_merge( $def, $by_iso[ $iso ] );
					if ( isset( $merged['national_digits'] ) ) {
						$merged['national_digits'] = max( 1, min( 15, (int) $merged['national_digits'] ) );
					} else {
						$merged['national_digits'] = (int) ( $def['national_digits'] ?? 10 );
					}
					$dial = isset( $merged['dial'] ) ? trim( (string) $merged['dial'] ) : '';
					if ( '' === $dial ) {
						$merged['dial'] = (string) ( $def['dial'] ?? '' );
					}
					$lab = isset( $merged['label'] ) ? trim( (string) $merged['label'] ) : '';
					if ( '' === $lab ) {
						$merged['label'] = (string) ( $def['label'] ?? $iso );
					}
					$out[] = $merged;
				} else {
					$out[] = $def;
				}
			}
			$cb['phone_country_codes'] = $out;
			$s4['contact_block']       = $cb;
			$settings[ OptionKeys::SECTION_STEP_4 ] = $s4;

			return $settings;
		};

		self::$migrations['6'] = static function ( array $settings ): array {
			$s4 = isset( $settings[ OptionKeys::SECTION_STEP_4 ] ) && is_array( $settings[ OptionKeys::SECTION_STEP_4 ] )
				? $settings[ OptionKeys::SECTION_STEP_4 ]
				: array();
			$cb = isset( $s4['contact_block'] ) && is_array( $s4['contact_block'] ) ? $s4['contact_block'] : array();
			$cur = isset( $cb['phone_country_codes'] ) && is_array( $cb['phone_country_codes'] ) ? $cb['phone_country_codes'] : array();
			foreach ( $cur as $idx => $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				$iso = isset( $row['iso'] ) ? strtoupper( preg_replace( '/[^A-Za-z]/', '', (string) $row['iso'] ) ) : '';
				if ( 2 === strlen( $iso ) ) {
					$cur[ $idx ]['label'] = $iso;
				}
			}
			$cb['phone_country_codes'] = $cur;
			$s4['contact_block']       = $cb;
			$settings[ OptionKeys::SECTION_STEP_4 ] = $s4;

			return $settings;
		};

		self::$migrations['7'] = static function ( array $settings ): array {
			$s4 = isset( $settings[ OptionKeys::SECTION_STEP_4 ] ) && is_array( $settings[ OptionKeys::SECTION_STEP_4 ] )
				? $settings[ OptionKeys::SECTION_STEP_4 ]
				: array();
			$cb  = isset( $s4['coupon_block'] ) && is_array( $s4['coupon_block'] ) ? $s4['coupon_block'] : array();
			$old = array(
				'title'           => 'Подарочная карта',
				'intro'           => 'Введите код подарочной карты.',
				'input_label'     => 'Номер подарочной карты',
				'placeholder'     => 'Например, GIFT-123',
				'empty_message'   => 'Введите код.',
				'success_message' => 'Код применён.',
				'error_message'   => 'Не удалось применить код.',
			);
			$new = array(
				'title'           => 'Промокод',
				'intro'           => '',
				'input_label'     => 'Промокод',
				'placeholder'     => 'Например, SALE10',
				'empty_message'   => 'Введите промокод.',
				'success_message' => 'Промокод применён.',
				'error_message'   => 'Не удалось применить промокод. Проверьте написание и срок действия купона.',
			);
			foreach ( $old as $key => $legacy_val ) {
				if ( ! array_key_exists( $key, $cb ) ) {
					continue;
				}
				if ( (string) $cb[ $key ] === $legacy_val && isset( $new[ $key ] ) ) {
					$cb[ $key ] = $new[ $key ];
				}
			}
			$s4['coupon_block']                      = $cb;
			$settings[ OptionKeys::SECTION_STEP_4 ] = $s4;

			return $settings;
		};

		self::$migrations['8'] = static function ( array $settings ): array {
			$s4 = isset( $settings[ OptionKeys::SECTION_STEP_4 ] ) && is_array( $settings[ OptionKeys::SECTION_STEP_4 ] )
				? $settings[ OptionKeys::SECTION_STEP_4 ]
				: array();
			$cb = isset( $s4['coupon_block'] ) && is_array( $s4['coupon_block'] ) ? $s4['coupon_block'] : array();
			if ( isset( $cb['intro'] ) && 'Купон создаётся в WooCommerce: Маркетинг → Купоны.' === (string) $cb['intro'] ) {
				$cb['intro'] = '';
			}
			$s4['coupon_block']                      = $cb;
			$settings[ OptionKeys::SECTION_STEP_4 ] = $s4;

			return $settings;
		};
	}

	/**
	 * Следующая известная версия схемы после текущей или null, если конец цепочки.
	 */
	private static function bump_schema_version( string $current ): ?string {
		$chain = array(
			'0' => '1',
			'1' => '2',
			'2' => '3',
			'3' => '4',
			'4' => '5',
			'5' => '6',
			'6' => '7',
			'7' => '8',
			'8' => '9',
			'9' => null,
		);

		return array_key_exists( $current, $chain ) ? $chain[ $current ] : null;
	}
}
