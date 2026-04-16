<?php
/**
 * Checkout entry AJAX hooks facade in checkout domain.
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout\Checkout\Hooks;

defined( 'ABSPATH' ) || exit;

final class CheckoutEntryAjaxHooks {

	public const ACTION = \MP\CustomCheckout\Hooks\CheckoutEntryAjaxHooks::ACTION;

	public static function register(): void {
		\MP\CustomCheckout\Hooks\CheckoutEntryAjaxHooks::register();
	}
}
