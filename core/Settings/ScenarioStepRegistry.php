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

	public const STEP_ADDRESS_DELIVERY = 'address_delivery';

	public const STEP_RECIPIENT = 'recipient';

	public const STEP_PAYMENT = 'payment';

	public const STEP_CONFIRM = 'confirm';

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
			self::STEP_ADDRESS_DELIVERY,
			self::STEP_RECIPIENT,
			self::STEP_PAYMENT,
			self::STEP_CONFIRM,
		);
	}

	/**
	 * Метаданные шагов для хранения и админки.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function step_definitions(): array {
		return array(
			self::STEP_ADDRESS_DELIVERY => array(
				'id'                 => self::STEP_ADDRESS_DELIVERY,
				'label'              => 'Адрес и способ доставки',
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
			self::STEP_RECIPIENT => array(
				'id'                 => self::STEP_RECIPIENT,
				'label'              => 'Получатель',
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
			self::STEP_PAYMENT => array(
				'id'                 => self::STEP_PAYMENT,
				'label'              => 'Способ оплаты',
				'order'              => 30,
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
			self::STEP_CONFIRM => array(
				'id'                 => self::STEP_CONFIRM,
				'label'              => 'Подтвердить',
				'order'              => 40,
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
