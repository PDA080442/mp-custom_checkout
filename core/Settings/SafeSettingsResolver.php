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
			if ( OptionKeys::SECTION_STEP_2 === $section_key ) {
				$tree[ $section_key ] = array(
					'default_scenario' => ScenarioStepRegistry::SCENARIO_PICKUP,
					'card_order'       => array( 'pickup', 'delivery' ),
					'cards'            => array(
						'pickup'   => array(
							'title'            => 'Самовывоз',
							'description'      => 'Заберите заказ в удобное время в точке самовывоза.',
							'helper'           => 'Обычно готово к выдаче в день заказа.',
							'icon_variant'     => 'pickup',
							'icon_style'       => 'soft',
						),
						'delivery' => array(
							'title'            => 'Доставка',
							'description'      => 'Выберите доставку по городу или в другой город.',
							'helper'           => 'Стоимость и сроки зависят от адреса.',
							'icon_variant'     => 'delivery',
							'icon_style'       => 'soft',
						),
					),
					'responsive'       => array(
						'desktop_columns' => 2,
						'tablet_columns'  => 1,
						'mobile_columns'  => 1,
						'card_density'    => 'comfortable',
					),
					'admin_preview'    => array(
						'enabled' => true,
					),
				);
				continue;
			}
			if ( OptionKeys::SECTION_STEP_3 === $section_key ) {
				$tree[ $section_key ] = array(
					'min_lead_time_days' => array(
						'pickup'               => 1,
						'krasnoyarsk_delivery' => 1,
						'other_city_delivery'  => 2,
					),
					'max_days_ahead' => array(
						'pickup'               => 14,
						'krasnoyarsk_delivery' => 21,
						'other_city_delivery'  => 30,
					),
					'copy' => array(
						'title' => 'Выберите дату получения',
						'helper_by_scenario' => array(
							'pickup'               => 'Выберите удобную дату самовывоза.',
							'krasnoyarsk_delivery' => 'Выберите дату доставки по Красноярску.',
							'other_city_delivery'  => 'Выберите дату отправки в другой город.',
						),
						'errors' => array(
							'invalid_date' => 'Выбранная дата недоступна. Обновите шаг и выберите другую дату.',
							'empty_date'   => 'Выберите дату, чтобы продолжить.',
							'conditions_unconfirmed' => 'Подтвердите ознакомление с условиями, чтобы продолжить.',
						),
						'admin_preview' => array(
							'enabled' => true,
						),
					),
					'calendar_style' => array(
						'density'          => 'comfortable',
						'day_shape'        => 'rounded',
						'highlight_style'  => 'accent',
						'show_weekend_tint'=> true,
					),
					'weekday_rules' => array(
						'pickup'                => array( 1, 2, 3, 4, 5, 6 ),
						'krasnoyarsk_delivery'  => array( 1, 2, 3, 4, 5, 6 ),
						'other_city_delivery'   => array( 1, 3, 5 ),
					),
					'holiday_dates' => array(),
					'closed_dates'  => array(),
					'conditions_copy' => array(
						'intro_by_scenario' => array(
							'pickup'               => '',
							'krasnoyarsk_delivery' => '',
							'other_city_delivery'  => '',
						),
						'secondary_notes' => array(
							'',
							'',
							'',
						),
						'krasnoyarsk_delivery' => array(
							'title'               => '',
							'body'                => '',
							'delivery_within_day' => 'Доставка в течение дня в выбранную дату. Интервал уточняется у курьера.',
						),
						'other_city_delivery' => array(
							'title'          => '',
							'body'           => '',
							'logistics_note' => 'Отправка выполняется через логистическую компанию после комплектации и согласования реквизитов.',
						),
						'pickup' => array(
							'title'                => '',
							'body'                 => '',
							'office_block_title'   => 'Офис и график работы',
							'office_address'       => '',
							'office_description'   => 'Выдача заказа в офисе самовывоза после уведомления о готовности.',
							'office_hours_plain'   => "Пн–Пт 10:00–20:00\nСб–Вс 11:00–18:00",
							'office_hours'         => array( '10:00–13:00', '13:00–17:00', '17:00–20:00' ),
							'convenience_helper'   => 'Можно приехать в удобное время в рамках указанного расписания — уточните готовность заказа по уведомлению.',
							'critical_notice'      => '',
							'show_multi_office_slot' => true,
						),
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
