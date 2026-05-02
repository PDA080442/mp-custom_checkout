<?php
/**
 * Дефолтные feature flags checkout.
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Class DefaultFeatureFlagsRegistry
 */
final class DefaultFeatureFlagsRegistry {

	public const FLAG_CUSTOM_CHECKOUT_ROUTE = 'custom_checkout_route';

	public const FLAG_CHECKOUT_UI_V2 = 'checkout_ui_v2';

	public const FLAG_MULTI_STEP_FLOW = 'multi_step_flow';

	public const FLAG_MULTI_PICKUP_POINTS = 'multi_pickup_points';

	/** Серверная проверка: при методе `pvz` требуется выбранный офис (§29.7). Выключение — аварийный soft-режим. */
	public const FLAG_PVZ_OFFICE_REQUIRED = 'pvz_office_required';

	public const FLAG_CONDITIONS_STEP = 'conditions_step';

	public const FLAG_DISCOUNT_BLOCK_PLACEMENT = 'discount_block_placement';

	public const FLAG_CHECKOUT_TESTING_MODE = 'checkout_testing_mode';

	public const FLAG_ADMIN_LIVE_PREVIEW = 'admin_live_preview';

	/**
	 * Значения флагов по умолчанию (bool или скаляр).
	 *
	 * @return array<string, mixed>
	 */
	public static function all(): array {
		return array(
			self::FLAG_CUSTOM_CHECKOUT_ROUTE     => true,
			self::FLAG_CHECKOUT_UI_V2            => false,
			self::FLAG_MULTI_STEP_FLOW           => true,
			self::FLAG_MULTI_PICKUP_POINTS       => false,
			self::FLAG_PVZ_OFFICE_REQUIRED       => true,
			self::FLAG_CONDITIONS_STEP           => true,
			self::FLAG_DISCOUNT_BLOCK_PLACEMENT  => true,
			self::FLAG_CHECKOUT_TESTING_MODE     => false,
			self::FLAG_ADMIN_LIVE_PREVIEW        => true,
		);
	}
}
