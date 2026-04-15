<?php
/**
 * Реестр сценариев получения и шагов checkout (дефолтная конфигурация).
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Class ScenarioStepRegistry
 */
final class ScenarioStepRegistry {

	public const SCENARIO_PICKUP = 'pickup';

	public const SCENARIO_KRASNOYARSK_DELIVERY = 'krasnoyarsk_delivery';

	public const SCENARIO_OTHER_CITY_DELIVERY = 'other_city_delivery';

	public const STEP_CART = 'cart';

	public const STEP_DATE = 'date';

	public const STEP_CONDITIONS = 'conditions';

	public const STEP_CONTACT_PAYMENT = 'contact_payment';

	/**
	 * Сценарии получения заказа: id => человекочитаемый ключ для настроек.
	 *
	 * @return array<string, string>
	 */
	public static function scenarios(): array {
		return array(
			self::SCENARIO_PICKUP               => 'Самовывоз',
			self::SCENARIO_KRASNOYARSK_DELIVERY => 'Доставка по Красноярску',
			self::SCENARIO_OTHER_CITY_DELIVERY => 'Доставка в другой город',
		);
	}

	/**
	 * Базовые шаги (порядок по умолчанию).
	 *
	 * @return array<int, string>
	 */
	public static function default_step_order(): array {
		return array(
			self::STEP_CART,
			self::STEP_DATE,
			self::STEP_CONDITIONS,
			self::STEP_CONTACT_PAYMENT,
		);
	}

	/**
	 * Метаданные шагов для хранения и админки.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function step_definitions(): array {
		return array(
			self::STEP_CART => array(
				'label'    => 'Товары и способ получения',
				'enabled'  => true,
				'sort'     => 10,
			),
			self::STEP_DATE => array(
				'label'    => 'Дата',
				'enabled'  => true,
				'sort'     => 20,
			),
			self::STEP_CONDITIONS => array(
				'label'    => 'Условия получения',
				'enabled'  => true,
				'sort'     => 30,
			),
			self::STEP_CONTACT_PAYMENT => array(
				'label'    => 'Контакты и оплата',
				'enabled'  => true,
				'sort'     => 40,
			),
		);
	}

	/**
	 * Снимок реестра для сохранения в опции {@see OptionKeys::KEY_REGISTRY}.
	 *
	 * @return array<string, mixed>
	 */
	public static function default_storage_snapshot(): array {
		return array(
			'scenarios'          => self::scenarios(),
			'default_scenario'   => self::SCENARIO_PICKUP,
			'step_order'         => self::default_step_order(),
			'step_definitions'   => self::step_definitions(),
			'features'           => array(
				'checkout' => true,
			),
		);
	}
}
