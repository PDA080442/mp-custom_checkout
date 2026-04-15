<?php
/**
 * Безопасное получение настроек с подстановкой значений по умолчанию.
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Class SafeSettingsResolver
 */
final class SafeSettingsResolver {

	/**
	 * @var array<string, mixed>|null
	 */
	private static $merged_cache = null;

	/**
	 * Полное дерево значений по умолчанию (все разделы).
	 *
	 * @return array<string, mixed>
	 */
	public static function get_defaults_tree(): array {
		$tree = array();

		foreach ( OptionKeys::all_section_keys() as $section_key ) {
			if ( OptionKeys::KEY_LABELS === $section_key ) {
				$tree[ $section_key ] = DefaultLabelsRegistry::all();
				continue;
			}
			if ( OptionKeys::KEY_FEATURE_FLAGS === $section_key ) {
				$tree[ $section_key ] = DefaultFeatureFlagsRegistry::all();
				continue;
			}
			if ( OptionKeys::KEY_DESIGN_TOKENS === $section_key ) {
				$tree[ $section_key ] = DefaultDesignTokensRegistry::all();
				continue;
			}
			if ( OptionKeys::KEY_REGISTRY === $section_key ) {
				$tree[ $section_key ] = ScenarioStepRegistry::default_storage_snapshot();
				continue;
			}

			if ( OptionKeys::SECTION_GENERAL === $section_key ) {
				$tree[ $section_key ] = array(
					'route_slug' => 'mp-checkout',
				);
				continue;
			}

			$tree[ $section_key ] = array();
		}

		return $tree;
	}

	/**
	 * Сброс кэша после обновления опций.
	 */
	public static function clear_cache(): void {
		self::$merged_cache = null;
	}

	/**
	 * Слияние сохранённых настроек с дефолтами (пользователь перекрывает дефолты).
	 *
	 * @return array<string, mixed>
	 */
	public static function get_merged(): array {
		if ( null !== self::$merged_cache ) {
			return self::$merged_cache;
		}

		$stored = get_option( OptionKeys::MAIN, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		$defaults       = self::get_defaults_tree();
		self::$merged_cache = array_replace_recursive( $defaults, $stored );

		return self::$merged_cache;
	}

	/**
	 * Значение по пути через точку, например `labels.step_1.title` или `feature_flags.custom_checkout_route`.
	 *
	 * @param mixed $default Fallback, если путь не найден.
	 * @return mixed
	 */
	public static function get( string $dot_path, $default = null ) {
		$data = self::get_merged();
		$keys = explode( '.', $dot_path );
		$node = $data;

		foreach ( $keys as $key ) {
			if ( ! is_array( $node ) || ! array_key_exists( $key, $node ) ) {
				return $default;
			}
			$node = $node[ $key ];
		}

		return $node;
	}

	/**
	 * Весь раздел по ключу {@see OptionKeys::SECTION_*} или {@see OptionKeys::KEY_*}.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_section( string $section_key ): array {
		$value = self::get( $section_key, array() );
		return is_array( $value ) ? $value : array();
	}
}
