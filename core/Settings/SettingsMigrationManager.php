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
	}

	/**
	 * Следующая известная версия схемы после текущей или null, если конец цепочки.
	 */
	private static function bump_schema_version( string $current ): ?string {
		$chain = array(
			'0' => '1',
			'1' => '2',
			'2' => null,
		);

		return array_key_exists( $current, $chain ) ? $chain[ $current ] : null;
	}
}
