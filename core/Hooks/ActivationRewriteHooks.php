<?php
/**
 * Отложенный flush rewrite после активации (без тяжёлых вызовов в callback активации).
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout\Hooks;

use MP\CustomCheckout\Checkout\Routing\CheckoutSuccessRouteHooks;
use MP\CustomCheckout\Activator;

defined( 'ABSPATH' ) || exit;

final class ActivationRewriteHooks {

	public static function register(): void {
		add_action( 'init', array( __CLASS__, 'maybe_flush_after_activation' ), 5 );
	}

	public static function maybe_flush_after_activation(): void {
		if ( ! get_option( Activator::OPTION_NEEDS_REWRITE_FLUSH, false ) ) {
			return;
		}
		delete_option( Activator::OPTION_NEEDS_REWRITE_FLUSH );
		CheckoutRouteHooks::add_rewrite_rules();
		CheckoutSuccessRouteHooks::add_rewrite_rules();
		flush_rewrite_rules( false );
	}
}
