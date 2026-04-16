<?php
/**
 * Checkout AJAX hooks facade in checkout domain.
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout\Checkout\Hooks;

defined( 'ABSPATH' ) || exit;

final class CheckoutAjaxHooks {

	public const ACTION = \MP\CustomCheckout\Hooks\CheckoutAjaxHooks::ACTION;

	public static function register(): void {
		\MP\CustomCheckout\Hooks\CheckoutAjaxHooks::register();
	}
}
