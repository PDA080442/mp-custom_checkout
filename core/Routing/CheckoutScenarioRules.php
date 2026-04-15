<?php
/**
 * Единый объект правил сценария fulfillment (шаги, поля, даты, тексты).
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout\Routing;

use MP\CustomCheckout\Settings\ScenarioStepRegistry;

defined( 'ABSPATH' ) || exit;

/**
 * Class CheckoutScenarioRules
 */
final class CheckoutScenarioRules {

	/**
	 * @return array<string, mixed>
	 */
	public static function build( string $scenario ): array {
		$scenario = self::sanitize_scenario( $scenario );

		$rules = array(
			'id'                  => $scenario,
			'label'               => self::scenario_label( $scenario ),
			'default_is_pickup'   => ScenarioStepRegistry::SCENARIO_PICKUP === $scenario,
			'field_rules'         => self::field_rules( $scenario ),
			'step_rules'          => self::step_rules( $scenario ),
			'date_rules'          => self::date_rules( $scenario ),
			'copy_rules'          => self::copy_rules( $scenario ),
			'serialize'           => self::serialize_payload( $scenario ),
		);

		return (array) apply_filters( 'mp_custom_checkout_scenario_rules', $rules, $scenario );
	}

	public static function sanitize_scenario( string $scenario ): string {
		$scenario = sanitize_key( $scenario );
		$known    = array_keys( ScenarioStepRegistry::scenarios() );
		if ( '' === $scenario || ! in_array( $scenario, $known, true ) ) {
			return ScenarioStepRegistry::SCENARIO_PICKUP;
		}

		return $scenario;
	}

	public static function scenario_label( string $scenario ): string {
		$scenario = self::sanitize_scenario( $scenario );
		$map      = ScenarioStepRegistry::scenarios();

		$label = isset( $map[ $scenario ] ) ? (string) $map[ $scenario ] : 'Самовывоз';
		return self::normalize_label_for_output( $label, $scenario );
	}

	/**
	 * Нормализация сценарного label для storage/email/admin/list/review.
	 */
	public static function normalize_label_for_output( string $label, string $scenario = '' ): string {
		$label = sanitize_text_field( wp_strip_all_tags( $label ) );
		$label = trim( preg_replace( '/\s+/', ' ', $label ) ?? '' );
		if ( '' !== $label ) {
			return $label;
		}

		$scenario = self::sanitize_scenario( $scenario );
		if ( ScenarioStepRegistry::SCENARIO_KRASNOYARSK_DELIVERY === $scenario ) {
			return 'Доставка по Красноярску';
		}
		if ( ScenarioStepRegistry::SCENARIO_OTHER_CITY_DELIVERY === $scenario ) {
			return 'Доставка в другой город';
		}

		return 'Самовывоз';
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function field_rules( string $scenario ): array {
		$is_pickup = ScenarioStepRegistry::SCENARIO_PICKUP === $scenario;

		return array(
			'hide_address_fields'    => $is_pickup,
			'required_address_fields'=> ! $is_pickup,
			'visible_groups'         => $is_pickup ? array( 'contact', 'pickup_point' ) : array( 'contact', 'shipping_address' ),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function step_rules( string $scenario ): array {
		unset( $scenario );

		return array(
			'show_conditions_step' => true,
			'conditions_step_id'   => ScenarioStepRegistry::STEP_CONDITIONS,
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function date_rules( string $scenario ): array {
		return CheckoutDateAvailabilityEngine::build_rules( $scenario );
	}

	/**
	 * @return array<string, string>
	 */
	private static function copy_rules( string $scenario ): array {
		if ( ScenarioStepRegistry::SCENARIO_PICKUP === $scenario ) {
			return array(
				'title'            => 'Самовывоз',
				'hint'             => 'Выберите удобную дату и получите заказ в точке самовывоза.',
				'conditions_title' => 'Условия самовывоза',
			);
		}

		if ( ScenarioStepRegistry::SCENARIO_KRASNOYARSK_DELIVERY === $scenario ) {
			return array(
				'title'            => 'Доставка по Красноярску',
				'hint'             => 'Укажите адрес в Красноярске и выберите доступное окно доставки.',
				'conditions_title' => 'Условия доставки по Красноярску',
			);
		}

		return array(
			'title'            => 'Доставка в другой город',
			'hint'             => 'Укажите адрес и контактные данные для межгородской доставки.',
			'conditions_title' => 'Условия доставки в другой город',
		);
	}

	/**
	 * @return array<string, string>
	 */
	private static function serialize_payload( string $scenario ): array {
		return array(
			'id'    => $scenario,
			'label' => self::scenario_label( $scenario ),
		);
	}
}
