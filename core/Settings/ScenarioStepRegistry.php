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
				'id'                 => self::STEP_CART,
				'label'              => 'Товары и способ получения',
				'order'              => 10,
				'enabled'            => true,
				'visibility_mode'    => 'scenario',
				'visible_in'         => array(
					self::SCENARIO_PICKUP,
					self::SCENARIO_KRASNOYARSK_DELIVERY,
					self::SCENARIO_OTHER_CITY_DELIVERY,
				),
				'validation_mode'    => 'server',
				'discount_step_ready' => true,
			),
			self::STEP_DATE => array(
				'id'                 => self::STEP_DATE,
				'label'              => 'Дата',
				'order'              => 20,
				'enabled'            => true,
				'visibility_mode'    => 'scenario',
				'visible_in'         => array(
					self::SCENARIO_PICKUP,
					self::SCENARIO_KRASNOYARSK_DELIVERY,
					self::SCENARIO_OTHER_CITY_DELIVERY,
				),
				'validation_mode'    => 'server',
				'discount_step_ready' => true,
			),
			self::STEP_CONDITIONS => array(
				'id'                 => self::STEP_CONDITIONS,
				'label'              => 'Условия получения',
				'order'              => 30,
				'enabled'            => true,
				'visibility_mode'    => 'scenario',
				'visible_in'         => array(
					self::SCENARIO_PICKUP,
					self::SCENARIO_KRASNOYARSK_DELIVERY,
					self::SCENARIO_OTHER_CITY_DELIVERY,
				),
				'validation_mode'    => 'strict',
				'discount_step_ready' => true,
			),
			self::STEP_CONTACT_PAYMENT => array(
				'id'                 => self::STEP_CONTACT_PAYMENT,
				'label'              => 'Контакты и оплата',
				'order'              => 40,
				'enabled'            => true,
				'visibility_mode'    => 'scenario',
				'visible_in'         => array(
					self::SCENARIO_PICKUP,
					self::SCENARIO_KRASNOYARSK_DELIVERY,
					self::SCENARIO_OTHER_CITY_DELIVERY,
				),
				'validation_mode'    => 'strict',
				'discount_step_ready' => true,
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
