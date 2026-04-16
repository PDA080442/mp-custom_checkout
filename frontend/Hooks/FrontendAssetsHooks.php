<?php
/**
 * Frontend assets hook facade in the frontend domain.
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout\Frontend\Hooks;

defined( 'ABSPATH' ) || exit;

final class FrontendAssetsHooks {

	public static function register(): void {
		\MP\CustomCheckout\Hooks\FrontendAssetsHooks::register();
	}
}
