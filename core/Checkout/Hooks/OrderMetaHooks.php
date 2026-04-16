<?php
/**
 * Order meta hooks facade in checkout domain.
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout\Checkout\Hooks;

defined( 'ABSPATH' ) || exit;

final class OrderMetaHooks {

	public static function register(): void {
		\MP\CustomCheckout\Hooks\OrderMetaHooks::register();
	}
}
