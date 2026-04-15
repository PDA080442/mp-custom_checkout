<?php
/**
 * Подключение публичных CSS/JS checkout.
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout\Hooks;

use MP\CustomCheckout\DependencyFailureGuard;
use MP\CustomCheckout\Settings\FeatureFlagResolver;

defined( 'ABSPATH' ) || exit;

/**
 * Class FrontendAssetsHooks
 */
final class FrontendAssetsHooks {

	public const HANDLE_STYLE  = 'mp-cc-checkout-frontend';
	public const HANDLE_SCRIPT = 'mp-cc-checkout-frontend';

	/**
	 * Регистрация wp_enqueue_scripts.
	 */
	public static function register(): void {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ), 20 );
	}

	/**
	 * Подключение ассетов только на маршруте checkout и при готовности WooCommerce.
	 */
	public static function enqueue(): void {
		if ( ! CheckoutRouteHooks::is_checkout_route() ) {
			return;
		}

		if ( ! DependencyFailureGuard::is_woocommerce_integration_ready() ) {
			return;
		}

		wp_enqueue_style(
			self::HANDLE_STYLE,
			MP_CUSTOM_CHECKOUT_URL . 'assets/css/checkout-frontend.css',
			array(),
			MP_CUSTOM_CHECKOUT_VERSION
		);

		wp_enqueue_script(
			self::HANDLE_SCRIPT,
			MP_CUSTOM_CHECKOUT_URL . 'assets/js/checkout-frontend.js',
			array( 'jquery' ),
			MP_CUSTOM_CHECKOUT_VERSION,
			true
		);

		wp_localize_script(
			self::HANDLE_SCRIPT,
			'mpCcCheckout',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'mp_cc_checkout' ),
				'flags'   => FeatureFlagResolver::frontend_payload(),
			)
		);

		do_action( 'mp_custom_checkout_enqueue_frontend_assets' );
	}
}
