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
					'route_slug'          => 'mp-checkout',
					'require_entry_gate'  => true,
					'success_route_slug'  => 'mp-checkout-success',
				);
				continue;
			}
			if ( OptionKeys::SECTION_STEP_1 === $section_key ) {
				$tree[ $section_key ] = array(
					'labels' => array(
						'title'          => 'Корзина',
						'summary_title'  => 'Сводка заказа',
						'subtotal_label' => 'Подытог',
						'items_label'    => 'Позиций',
						'continue_label' => 'Продолжить оформление',
						'return_label'   => 'Вернуться в магазин',
						'empty_title'    => 'Корзина пуста',
					),
					'product_meta_visibility' => array(
						'show_image'      => true,
						'show_sku'        => true,
						'show_variation'  => true,
						'show_price'      => true,
						'show_subtotal'   => true,
					),
					'quantity_controls' => array(
						'enabled'            => true,
						'allow_manual_input' => true,
						'show_increment'     => true,
						'show_decrement'     => true,
					),
					'empty_state' => array(
						'message'     => 'Добавьте товары, чтобы продолжить оформление.',
						'cta_label'   => 'Вернуться в магазин',
						'cta_enabled' => true,
					),
					'style_controls' => array(
						'card_compact'       => false,
						'card_emphasis'      => 'default',
						'summary_emphasis'   => 'default',
					),
					'layout_order' => array(
						'secondary_order' => array( 'price', 'sku', 'variation', 'quantity', 'subtotal', 'remove' ),
					),
					'responsive' => array(
						'desktop_mode'       => 'comfortable',
						'tablet_mode'        => 'comfortable',
						'mobile_mode'        => 'compact',
						'hide_media_mobile'  => false,
					),
					'admin_preview' => array(
						'enabled' => true,
					),
				);
				continue;
			}
			if ( OptionKeys::SECTION_PICKUP === $section_key ) {
				$tree[ $section_key ] = array(
					'enable_point_selection' => false,
					'map_slot_enabled'       => true,
					'points'                 => array(
						array(
							'id'          => 'pickup_main',
							'title'       => 'Основная точка самовывоза',
							'address'     => 'г. Красноярск, ул. Примерная, 1',
							'description' => 'Ежедневно с 10:00 до 20:00',
							'map_hint'    => 'Слот карты/схемы будет подключен здесь.',
						),
					),
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
