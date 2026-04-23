<?php
/**
 * Дефолтная конфигурация motion-слоя checkout (длительности, easing, throttle, reduced-motion).
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Class DefaultMotionSettingsRegistry
 */
final class DefaultMotionSettingsRegistry {

	/**
	 * Полный снимок раздела `motion` для merge с сохранёнными настройками.
	 *
	 * @return array<string, mixed>
	 */
	public static function all(): array {
		return array(
			'respect_prefers_reduced_motion' => true,
			'force_reduced_motion'           => false,
			'instrumentation_enabled'        => false,
			'ease_profile'                   => 'balanced',
			'throttle'                       => array(
				'enabled'           => true,
				'min_interval_ms' => 120,
			),
			'durations_ms'                   => array(
				'step_transition' => 180,
				'rail'            => 420,
				'step_screen'     => 200,
				'field_state'     => 220,
				'summary_numbers' => 340,
				'skeleton_shimmer'=> 1100,
			),
			'mobile'                         => array(
				'use_desktop_durations' => true,
				'durations_ms'          => array(
					'step_transition' => 140,
					'rail'            => 320,
					'step_screen'     => 160,
					'field_state'     => 180,
					'summary_numbers' => 280,
					'skeleton_shimmer'=> 900,
				),
			),
			'ease'                           => array(
				'standard'   => 'cubic-bezier(0.22, 1, 0.36, 1)',
				'emphasized' => 'cubic-bezier(0.2, 0, 0, 1)',
			),
			'delay_ms'                       => array(
				'summary_stagger_base' => 0,
			),
			'toggles'                        => array(
				'rail'                    => true,
				'step_reveal'             => true,
				'field_state'             => true,
				'summary_numbers'         => true,
				'step_transition_overlay' => true,
			),
		);
	}
}
