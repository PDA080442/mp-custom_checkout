<?php
/**
 * Дефолтные дизайн-токены (CSS custom properties / переменные темы checkout).
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Class DefaultDesignTokensRegistry
 */
final class DefaultDesignTokensRegistry {

	/**
	 * Имена токенов без префикса -- (префикс добавляется при выводе в CSS).
	 *
	 * @return array<string, string>
	 */
	public static function all(): array {
		return array(
			'color_text'           => '#1a1a1a',
			'color_text_muted'     => '#666666',
			'color_background'     => '#ffffff',
			'color_border'         => '#e5e5e5',
			'color_accent'         => '#111111',
			'color_error'          => '#b91c1c',
			'color_success'        => '#15803d',
			'font_family_base'     => 'system-ui, -apple-system, Segoe UI, Roboto, sans-serif',
			'font_size_base'       => '16px',
			'radius_sm'            => '6px',
			'radius_md'            => '10px',
			'shadow_card'          => '0 1px 3px rgba(0, 0, 0, 0.08)',
			'space_step_gap'       => '24px',
			'transition_duration'  => '0.2s',
		);
	}
}
