<?php
/**
 * Routing compatibility facade in checkout domain.
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout\Checkout\Routing;

defined( 'ABSPATH' ) || exit;

final class CheckoutPermalinkCompatibility {

	public static function register(): void {
		\MP\CustomCheckout\Routing\CheckoutPermalinkCompatibility::register();
	}
}
