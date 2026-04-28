<?php
/**
 * Ключи опций WordPress для настроек checkout-плагина.
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Class OptionKeys
 */
final class OptionKeys {

	/**
	 * Версия схемы настроек в коде (миграции).
	 */
	public const SETTINGS_SCHEMA_VERSION = '10';

	/**
	 * Основной массив настроек (все вкладки и служебные данные).
	 */
	public const MAIN = 'mp_custom_checkout_settings';

	/**
	 * Версия сохранённой схемы настроек в БД (отдельная опция для миграций).
	 */
	public const DB_VERSION = 'mp_custom_checkout_db_version';

	/** Вкладка «Общие». */
	public const SECTION_GENERAL = 'general';

	/** Вкладка «Шаг 1». */
	public const SECTION_STEP_1 = 'step_1';

	/** Вкладка «Шаг 2». */
	public const SECTION_STEP_2 = 'step_2';

	/** Вкладка «Шаг 3». */
	public const SECTION_STEP_3 = 'step_3';

	/** Вкладка «Шаг 4». */
	public const SECTION_STEP_4 = 'step_4';

	/** Вкладка «Оплата». */
	public const SECTION_PAYMENT = 'payment';

	/** Вкладка «Стили». */
	public const SECTION_STYLES = 'styles';

	/** Вкладка «Служебное». */
	public const SECTION_SERVICE = 'service';

	/** Сущность «Поля». */
	public const SECTION_FIELDS = 'fields';

	/** Сущность «Доставка». */
	public const SECTION_DELIVERY = 'delivery';

	/** Сущность «Самовывоз». */
	public const SECTION_PICKUP = 'pickup';

	/** Сущность «Подарочная карта». */
	public const SECTION_GIFT_CARD = 'gift_card';

	/** Сущность «Купоны». */
	public const SECTION_COUPONS = 'coupons';

	/** Сущность «Превью». */
	public const SECTION_PREVIEW = 'preview';

	/** Сущность «Логи». */
	public const SECTION_LOGS = 'logs';

	/** Motion / анимации checkout. */
	public const SECTION_MOTION = 'motion';

	/**
	 * Глобальные UI-тексты (реестр лейблов).
	 */
	public const KEY_LABELS = 'labels';

	/**
	 * Feature flags.
	 */
	public const KEY_FEATURE_FLAGS = 'feature_flags';

	/**
	 * Дизайн-токены / CSS-переменные.
	 */
	public const KEY_DESIGN_TOKENS = 'design_tokens';

	/**
	 * Служебный реестр фич / шагов / сценариев (снимок конфигурации).
	 */
	public const KEY_REGISTRY = 'registry';

	/**
	 * Все ключи вкладок и сущностей для хранения в MAIN option.
	 *
	 * @return array<int, string>
	 */
	public static function all_section_keys(): array {
		return array(
			self::SECTION_GENERAL,
			self::SECTION_STEP_1,
			self::SECTION_STEP_2,
			self::SECTION_STEP_3,
			self::SECTION_STEP_4,
			self::SECTION_PAYMENT,
			self::SECTION_STYLES,
			self::SECTION_SERVICE,
			self::SECTION_FIELDS,
			self::SECTION_DELIVERY,
			self::SECTION_PICKUP,
			self::SECTION_GIFT_CARD,
			self::SECTION_COUPONS,
			self::SECTION_PREVIEW,
			self::SECTION_LOGS,
			self::SECTION_MOTION,
			self::KEY_LABELS,
			self::KEY_FEATURE_FLAGS,
			self::KEY_DESIGN_TOKENS,
			self::KEY_REGISTRY,
		);
	}
}
