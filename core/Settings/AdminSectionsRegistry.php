<?php
/**
 * Структура вкладок и сущностей админки — привязка ключей хранения к разделам.
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Class AdminSectionsRegistry
 */
final class AdminSectionsRegistry {

	/**
	 * Описание разделов настроек: id вкладки => метаданные.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function sections(): array {
		return array(
			OptionKeys::SECTION_GENERAL => array(
				'label'       => 'Общие',
				'storage_key' => OptionKeys::SECTION_GENERAL,
				'type'        => 'tab',
				'sort'        => 10,
			),
			OptionKeys::SECTION_STEP_1 => array(
				'label'       => 'Шаг 1: Адрес и доставка',
				'storage_key' => OptionKeys::SECTION_STEP_1,
				'type'        => 'tab',
				'sort'        => 20,
			),
			OptionKeys::SECTION_STEP_2 => array(
				'label'       => 'Шаг 2: Получатель',
				'storage_key' => OptionKeys::SECTION_STEP_2,
				'type'        => 'tab',
				'sort'        => 30,
			),
			OptionKeys::SECTION_STEP_3 => array(
				'label'       => 'Шаг 3: Оплата',
				'storage_key' => OptionKeys::SECTION_STEP_3,
				'type'        => 'tab',
				'sort'        => 40,
			),
			OptionKeys::SECTION_STEP_4 => array(
				'label'       => 'Шаг 4: Подтвердить',
				'storage_key' => OptionKeys::SECTION_STEP_4,
				'type'        => 'tab',
				'sort'        => 50,
			),
			OptionKeys::SECTION_PAYMENT => array(
				'label'       => 'Оплата',
				'storage_key' => OptionKeys::SECTION_PAYMENT,
				'type'        => 'tab',
				'sort'        => 60,
			),
			OptionKeys::SECTION_STYLES => array(
				'label'       => 'Стили',
				'storage_key' => OptionKeys::SECTION_STYLES,
				'type'        => 'tab',
				'sort'        => 70,
			),
			OptionKeys::SECTION_MOTION => array(
				'label'       => 'Анимации',
				'storage_key' => OptionKeys::SECTION_MOTION,
				'type'        => 'tab',
				'sort'        => 75,
			),
			OptionKeys::SECTION_SERVICE => array(
				'label'       => 'Служебное',
				'storage_key' => OptionKeys::SECTION_SERVICE,
				'type'        => 'tab',
				'sort'        => 80,
			),
			OptionKeys::SECTION_FIELDS => array(
				'label'       => 'Поля',
				'storage_key' => OptionKeys::SECTION_FIELDS,
				'type'        => 'entity',
				'sort'        => 90,
			),
			OptionKeys::SECTION_DELIVERY => array(
				'label'       => 'Доставка',
				'storage_key' => OptionKeys::SECTION_DELIVERY,
				'type'        => 'entity',
				'sort'        => 100,
			),
			OptionKeys::SECTION_PICKUP => array(
				'label'       => 'Самовывоз',
				'storage_key' => OptionKeys::SECTION_PICKUP,
				'type'        => 'entity',
				'sort'        => 110,
			),
			OptionKeys::SECTION_GIFT_CARD => array(
				'label'       => 'Подарочная карта',
				'storage_key' => OptionKeys::SECTION_GIFT_CARD,
				'type'        => 'entity',
				'sort'        => 120,
			),
			OptionKeys::SECTION_COUPONS => array(
				'label'       => 'Купоны',
				'storage_key' => OptionKeys::SECTION_COUPONS,
				'type'        => 'entity',
				'sort'        => 130,
			),
			OptionKeys::SECTION_PREVIEW => array(
				'label'       => 'Превью',
				'storage_key' => OptionKeys::SECTION_PREVIEW,
				'type'        => 'entity',
				'sort'        => 140,
			),
			OptionKeys::SECTION_LOGS => array(
				'label'       => 'Логи',
				'storage_key' => OptionKeys::SECTION_LOGS,
				'type'        => 'entity',
				'sort'        => 150,
			),
			OptionKeys::KEY_LABELS => array(
				'label'       => 'Тексты (глобально)',
				'storage_key' => OptionKeys::KEY_LABELS,
				'type'        => 'virtual',
				'sort'        => 5,
			),
			OptionKeys::KEY_FEATURE_FLAGS => array(
				'label'       => 'Feature flags',
				'storage_key' => OptionKeys::KEY_FEATURE_FLAGS,
				'type'        => 'virtual',
				'sort'        => 6,
			),
			OptionKeys::KEY_DESIGN_TOKENS => array(
				'label'       => 'Дизайн-токены',
				'storage_key' => OptionKeys::KEY_DESIGN_TOKENS,
				'type'        => 'virtual',
				'sort'        => 7,
			),
			OptionKeys::KEY_REGISTRY => array(
				'label'       => 'Реестр шагов и сценариев',
				'storage_key' => OptionKeys::KEY_REGISTRY,
				'type'        => 'virtual',
				'sort'        => 8,
			),
		);
	}

	/**
	 * Ключ хранения по идентификатору раздела.
	 */
	public static function get_storage_key( string $section_id ): ?string {
		$all = self::sections();
		return isset( $all[ $section_id ]['storage_key'] ) ? (string) $all[ $section_id ]['storage_key'] : null;
	}
}
