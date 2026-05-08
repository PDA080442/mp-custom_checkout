<?php
/**
 * Единый безопасный resolver feature flags (frontend/backend).
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Class FeatureFlagResolver
 */
final class FeatureFlagResolver {

	/**
	 * @return array<string, bool>
	 */
	public static function all(): array {
		$defaults = DefaultFeatureFlagsRegistry::all();
		$raw      = SafeSettingsResolver::get_section( OptionKeys::KEY_FEATURE_FLAGS );
		$merged   = array_replace( $defaults, is_array( $raw ) ? $raw : array() );

		$result = array();
		foreach ( self::known_keys() as $key ) {
			$value = array_key_exists( $key, $merged ) ? $merged[ $key ] : false;
			$result[ $key ] = self::normalize_to_bool( $value );
		}

		return (array) apply_filters( 'mp_custom_checkout_feature_flags', $result );
	}

	public static function is_enabled( string $flag, bool $default = false ): bool {
		$flag = sanitize_key( $flag );
		$all  = self::all();
		if ( array_key_exists( $flag, $all ) ) {
			return (bool) $all[ $flag ];
		}

		return $default;
	}

	/**
	 * @return array<string, bool>
	 */
	public static function frontend_payload(): array {
		$all = self::all();
		return array(
			DefaultFeatureFlagsRegistry::FLAG_CUSTOM_CHECKOUT_ROUTE    => $all[ DefaultFeatureFlagsRegistry::FLAG_CUSTOM_CHECKOUT_ROUTE ],
			DefaultFeatureFlagsRegistry::FLAG_CHECKOUT_UI_V2           => $all[ DefaultFeatureFlagsRegistry::FLAG_CHECKOUT_UI_V2 ],
			DefaultFeatureFlagsRegistry::FLAG_MULTI_STEP_FLOW          => $all[ DefaultFeatureFlagsRegistry::FLAG_MULTI_STEP_FLOW ],
			DefaultFeatureFlagsRegistry::FLAG_MULTI_PICKUP_POINTS      => $all[ DefaultFeatureFlagsRegistry::FLAG_MULTI_PICKUP_POINTS ],
			DefaultFeatureFlagsRegistry::FLAG_PVZ_OFFICE_REQUIRED      => $all[ DefaultFeatureFlagsRegistry::FLAG_PVZ_OFFICE_REQUIRED ],
			DefaultFeatureFlagsRegistry::FLAG_CONDITIONS_STEP          => $all[ DefaultFeatureFlagsRegistry::FLAG_CONDITIONS_STEP ],
			DefaultFeatureFlagsRegistry::FLAG_DISCOUNT_BLOCK_PLACEMENT => $all[ DefaultFeatureFlagsRegistry::FLAG_DISCOUNT_BLOCK_PLACEMENT ],
			DefaultFeatureFlagsRegistry::FLAG_CHECKOUT_TESTING_MODE    => $all[ DefaultFeatureFlagsRegistry::FLAG_CHECKOUT_TESTING_MODE ],
			DefaultFeatureFlagsRegistry::FLAG_ADMIN_LIVE_PREVIEW       => $all[ DefaultFeatureFlagsRegistry::FLAG_ADMIN_LIVE_PREVIEW ],
			DefaultFeatureFlagsRegistry::FLAG_CHECKOUT_PERF_V1         => $all[ DefaultFeatureFlagsRegistry::FLAG_CHECKOUT_PERF_V1 ],
		);
	}

	/**
	 * @return array<int, string>
	 */
	private static function known_keys(): array {
		return array_keys( DefaultFeatureFlagsRegistry::all() );
	}

	/**
	 * @param mixed $value
	 */
	private static function normalize_to_bool( $value ): bool {
		if ( is_bool( $value ) ) {
			return $value;
		}
		if ( is_numeric( $value ) ) {
			return (int) $value > 0;
		}
		if ( is_string( $value ) ) {
			$value = strtolower( trim( $value ) );
			return in_array( $value, array( '1', 'true', 'yes', 'on' ), true );
		}

		return false;
	}
}
