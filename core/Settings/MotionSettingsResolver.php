<?php
/**
 * Нормализация и разрешение motion-настроек (десктоп / mobile, пресеты easing, clamp).
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Class MotionSettingsResolver
 */
final class MotionSettingsResolver {

	public const DURATION_MS_MAX = 4000;

	public const THROTTLE_MS_MAX = 2000;

	/**
	 * @return array<int, string>
	 */
	public static function duration_keys(): array {
		return array( 'step_transition', 'rail', 'step_screen', 'field_state', 'summary_numbers', 'skeleton_shimmer' );
	}

	/**
	 * Пресеты easing (standard + emphasized).
	 *
	 * @return array<string, array<string, string>>
	 */
	public static function ease_presets(): array {
		return array(
			'snappy'    => array(
				'standard'   => 'cubic-bezier(0.4, 0, 1, 1)',
				'emphasized' => 'cubic-bezier(0.55, 0, 1, 0.45)',
			),
			'balanced'  => array(
				'standard'   => 'cubic-bezier(0.22, 1, 0.36, 1)',
				'emphasized' => 'cubic-bezier(0.2, 0, 0, 1)',
			),
			'smooth'    => array(
				'standard'   => 'cubic-bezier(0.33, 1, 0.68, 1)',
				'emphasized' => 'cubic-bezier(0.16, 1, 0.3, 1)',
			),
		);
	}

	/**
	 * Числовые пресеты длительностей (мс) для кнопок в админке (десктоп).
	 *
	 * @return array<string, array<string, int>>
	 */
	public static function duration_presets_desktop_ms(): array {
		return array(
			'fast'      => array(
				'step_transition' => 110,
				'rail'            => 260,
				'step_screen'     => 120,
				'field_state'     => 140,
				'summary_numbers' => 220,
				'skeleton_shimmer'=> 800,
			),
			'balanced'  => array(
				'step_transition' => 180,
				'rail'            => 420,
				'step_screen'     => 200,
				'field_state'     => 220,
				'summary_numbers' => 340,
				'skeleton_shimmer'=> 1100,
			),
			'smooth'    => array(
				'step_transition' => 260,
				'rail'            => 620,
				'step_screen'     => 320,
				'field_state'     => 320,
				'summary_numbers' => 480,
				'skeleton_shimmer'=> 1400,
			),
		);
	}

	/**
	 * @param array<string, mixed> $merged motion после array_replace_recursive с дефолтами.
	 * @return array<string, mixed> Секция для сохранения в опциях (все ключи валидны).
	 */
	public static function sanitize_section( array $merged ): array {
		$defaults = DefaultMotionSettingsRegistry::all();
		$merged   = array_replace_recursive( $defaults, $merged );

		$merged['respect_prefers_reduced_motion'] = ! empty( $merged['respect_prefers_reduced_motion'] );
		$merged['force_reduced_motion']           = ! empty( $merged['force_reduced_motion'] );
		$merged['instrumentation_enabled']        = ! empty( $merged['instrumentation_enabled'] );

		$profile = isset( $merged['ease_profile'] ) ? sanitize_key( (string) $merged['ease_profile'] ) : 'balanced';
		if ( ! in_array( $profile, array( 'snappy', 'balanced', 'smooth', 'custom' ), true ) ) {
			$profile = 'balanced';
		}
		$merged['ease_profile'] = $profile;

		$merged['durations_ms'] = self::sanitize_duration_map( isset( $merged['durations_ms'] ) && is_array( $merged['durations_ms'] ) ? $merged['durations_ms'] : array() );

		$mobile = isset( $merged['mobile'] ) && is_array( $merged['mobile'] ) ? $merged['mobile'] : array();
		$mobile['use_desktop_durations'] = ! empty( $mobile['use_desktop_durations'] );
		$mobile['durations_ms']         = self::sanitize_duration_map( isset( $mobile['durations_ms'] ) && is_array( $mobile['durations_ms'] ) ? $mobile['durations_ms'] : array() );
		$merged['mobile']               = $mobile;

		$throttle = isset( $merged['throttle'] ) && is_array( $merged['throttle'] ) ? $merged['throttle'] : array();
		$merged['throttle']             = array(
			'enabled'           => ! isset( $throttle['enabled'] ) || ! empty( $throttle['enabled'] ),
			'min_interval_ms' => self::clamp_int( isset( $throttle['min_interval_ms'] ) ? (int) $throttle['min_interval_ms'] : 120, 0, self::THROTTLE_MS_MAX ),
		);

		$ease = isset( $merged['ease'] ) && is_array( $merged['ease'] ) ? $merged['ease'] : array();
		$merged['ease']                 = array(
			'standard'   => self::sanitize_easing_string( isset( $ease['standard'] ) ? (string) $ease['standard'] : '' ),
			'emphasized' => self::sanitize_easing_string( isset( $ease['emphasized'] ) ? (string) $ease['emphasized'] : '' ),
		);

		$delay = isset( $merged['delay_ms'] ) && is_array( $merged['delay_ms'] ) ? $merged['delay_ms'] : array();
		$merged['delay_ms']             = array(
			'summary_stagger_base' => self::clamp_int( isset( $delay['summary_stagger_base'] ) ? (int) $delay['summary_stagger_base'] : 0, 0, 2000 ),
		);

		$toggles = isset( $merged['toggles'] ) && is_array( $merged['toggles'] ) ? $merged['toggles'] : array();
		$merged['toggles']              = array(
			'rail'                    => ! isset( $toggles['rail'] ) || ! empty( $toggles['rail'] ),
			'step_reveal'             => ! isset( $toggles['step_reveal'] ) || ! empty( $toggles['step_reveal'] ),
			'field_state'             => ! isset( $toggles['field_state'] ) || ! empty( $toggles['field_state'] ),
			'summary_numbers'         => ! isset( $toggles['summary_numbers'] ) || ! empty( $toggles['summary_numbers'] ),
			'step_transition_overlay' => ! isset( $toggles['step_transition_overlay'] ) || ! empty( $toggles['step_transition_overlay'] ),
		);

		return $merged;
	}

	/**
	 * @param array<string, mixed> $stored Секция motion из get_section (уже merge с дефолтами на чтение).
	 * @return array<string, mixed> Плоский payload для checkout SPA + вспомогательные поля.
	 */
	public static function runtime_payload( array $stored ): array {
		$clean = self::sanitize_section( $stored );
		$desk    = $clean['durations_ms'];
		$mob_eff = self::effective_mobile_durations( $desk, $clean['mobile'] );
		$ease    = self::resolve_ease_strings( (string) $clean['ease_profile'], $clean['ease'] );

		return array(
			'respect_prefers_reduced_motion' => $clean['respect_prefers_reduced_motion'],
			'force_reduced_motion'           => $clean['force_reduced_motion'],
			'instrumentation_enabled'        => $clean['instrumentation_enabled'],
			'ease_profile'                   => (string) $clean['ease_profile'],
			'throttle'                       => $clean['throttle'],
			'delay_ms'                       => $clean['delay_ms'],
			'toggles'                        => $clean['toggles'],
			'durations_ms'                   => $desk,
			'durations_ms_mobile_effective'  => $mob_eff,
			'mobile'                         => $clean['mobile'],
			'ease'                           => $ease,
		);
	}

	/**
	 * @param array<string, int> $desktop
	 * @param array<string, mixed> $mobile_section
	 * @return array<string, int>
	 */
	public static function effective_mobile_durations( array $desktop, array $mobile_section ): array {
		if ( ! empty( $mobile_section['use_desktop_durations'] ) ) {
			return $desktop;
		}
		$over = isset( $mobile_section['durations_ms'] ) && is_array( $mobile_section['durations_ms'] ) ? $mobile_section['durations_ms'] : array();
		$out  = $desktop;
		foreach ( self::duration_keys() as $key ) {
			if ( array_key_exists( $key, $over ) ) {
				$out[ $key ] = self::clamp_int( (int) $over[ $key ], 0, self::DURATION_MS_MAX );
			}
		}
		return $out;
	}

	/**
	 * @param array<string, string> $custom ease.standard / ease.emphasized при profile=custom.
	 * @return array<string, string>
	 */
	public static function resolve_ease_strings( string $profile, array $custom ): array {
		$presets = self::ease_presets();
		if ( 'custom' !== $profile && isset( $presets[ $profile ] ) ) {
			return array(
				'standard'   => self::sanitize_easing_string( $presets[ $profile ]['standard'] ),
				'emphasized' => self::sanitize_easing_string( $presets[ $profile ]['emphasized'] ),
			);
		}
		return array(
			'standard'   => self::sanitize_easing_string( isset( $custom['standard'] ) ? (string) $custom['standard'] : '' ),
			'emphasized' => self::sanitize_easing_string( isset( $custom['emphasized'] ) ? (string) $custom['emphasized'] : '' ),
		);
	}

	/**
	 * @param array<string, mixed> $durations
	 * @return array<string, int>
	 */
	public static function sanitize_duration_map( array $durations ): array {
		$out = array();
		foreach ( self::duration_keys() as $key ) {
			$out[ $key ] = self::clamp_int( isset( $durations[ $key ] ) ? (int) $durations[ $key ] : 0, 0, self::DURATION_MS_MAX );
		}
		return $out;
	}

	public static function sanitize_easing_string( string $value ): string {
		$value = trim( wp_strip_all_tags( $value ) );
		$value = str_replace( array( ';', '{', '}', '"', "'", "\n", "\r", "\t" ), '', $value );
		if ( '' === $value ) {
			return 'ease';
		}
		if ( ! preg_match( '/^[a-z0-9%,.\\s()\\-]+$/i', $value ) ) {
			return 'ease';
		}
		return $value;
	}

	/**
	 * CSS для #mp-cc-checkout: базовые переменные (desktop) + @media max-width 767px (mobile).
	 *
	 * @param array<string, mixed> $runtime {@see self::runtime_payload()}.
	 */
	public static function build_inline_css( array $runtime ): string {
		$desk = isset( $runtime['durations_ms'] ) && is_array( $runtime['durations_ms'] ) ? $runtime['durations_ms'] : array();
		$mob  = isset( $runtime['durations_ms_mobile_effective'] ) && is_array( $runtime['durations_ms_mobile_effective'] ) ? $runtime['durations_ms_mobile_effective'] : $desk;
		$ease = isset( $runtime['ease'] ) && is_array( $runtime['ease'] ) ? $runtime['ease'] : array();

		$ms_to_s = static function ( int $ms ): string {
			return (string) round( max( 0, $ms ) / 1000, 4 ) . 's';
		};

		$block = static function ( array $dur, array $ease_arr ) use ( $ms_to_s ): string {
			$rules   = array();
			$rules[] = '--mp-cc-motion-duration-step_transition:' . $ms_to_s( (int) ( $dur['step_transition'] ?? 180 ) ) . ';';
			$rules[] = '--mp-cc-motion-duration-rail:' . $ms_to_s( (int) ( $dur['rail'] ?? 420 ) ) . ';';
			$rules[] = '--mp-cc-motion-duration-step_screen:' . $ms_to_s( (int) ( $dur['step_screen'] ?? 200 ) ) . ';';
			$rules[] = '--mp-cc-motion-duration-field:' . $ms_to_s( (int) ( $dur['field_state'] ?? 220 ) ) . ';';
			$rules[] = '--mp-cc-motion-duration-summary:' . $ms_to_s( (int) ( $dur['summary_numbers'] ?? 340 ) ) . ';';
			$rules[] = '--mp-cc-mobile-motion-duration:' . $ms_to_s( (int) ( $dur['step_screen'] ?? 200 ) ) . ';';
			$rules[] = '--mp-cc-motion-ease-standard:' . self::sanitize_easing_string( (string) ( $ease_arr['standard'] ?? '' ) ) . ';';
			$rules[] = '--mp-cc-motion-ease-emphasized:' . self::sanitize_easing_string( (string) ( $ease_arr['emphasized'] ?? '' ) ) . ';';
			$rules[] = '--mp-cc-skeleton-shimmer-duration:' . $ms_to_s( (int) ( $dur['skeleton_shimmer'] ?? 1100 ) ) . ';';
			return implode( '', $rules );
		};

		$base      = '#mp-cc-checkout{' . $block( $desk, $ease ) . '}';
		$mob_block = $block( $mob, $ease );
		if ( wp_json_encode( $desk, JSON_UNESCAPED_UNICODE ) === wp_json_encode( $mob, JSON_UNESCAPED_UNICODE ) ) {
			return $base;
		}
		return $base . "\n@media (max-width:767px){#mp-cc-checkout{" . $mob_block . '}}';
	}

	private static function clamp_int( int $value, int $min, int $max ): int {
		if ( $value < $min ) {
			return $min;
		}
		if ( $value > $max ) {
			return $max;
		}
		return $value;
	}
}
