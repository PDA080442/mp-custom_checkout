<?php
/**
 * Email/Admin order output hooks facade in checkout domain.
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout\Checkout\Hooks;

defined( 'ABSPATH' ) || exit;

final class EmailHooks {

	public static function register(): void {
		\MP\CustomCheckout\Hooks\EmailHooks::register();
	}
}
