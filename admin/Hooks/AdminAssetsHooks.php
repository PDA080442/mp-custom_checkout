<?php
/**
 * Admin assets hook facade in the admin domain.
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout\Admin\Hooks;

defined( 'ABSPATH' ) || exit;

final class AdminAssetsHooks {

	public static function register(): void {
		\MP\CustomCheckout\Hooks\AdminAssetsHooks::register();
	}
}
