<?php
/**
 * Регистрация основных хуков жизненного цикла плагина (фаза 1.5).
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout\Hooks;

use MP\CustomCheckout\DependencyFailureGuard;
use MP\CustomCheckout\Routing\CheckoutEntryService;
use MP\CustomCheckout\Routing\CheckoutPermalinkCompatibility;
use MP\CustomCheckout\Routing\CheckoutRouteController;
use MP\CustomCheckout\Routing\CheckoutSessionService;
use MP\CustomCheckout\Routing\CheckoutSuccessController;
use MP\CustomCheckout\Routing\CheckoutSuccessOrderReceivedRedirect;
use MP\CustomCheckout\Routing\CheckoutSuccessRouteHooks;

defined( 'ABSPATH' ) || exit;

/**
 * Class PluginHooksRegistrar
 */
final class PluginHooksRegistrar {

	/**
	 * Подключение всех регистраторов хуков.
	 */
	public static function register(): void {
		FrontendAssetsHooks::register();
		AdminAssetsHooks::register();
		CheckoutNoJsFallbackHooks::register();
		CheckoutPermalinkCompatibility::register();
		CheckoutRouteHooks::register();
		CheckoutSuccessRouteHooks::register();
		CheckoutRouteController::register();
		CheckoutSuccessOrderReceivedRedirect::register();
		CheckoutSuccessController::register();
		CheckoutSuccessFrontendHooks::register();
		CheckoutAjaxHooks::register();
		CheckoutEntryAjaxHooks::register();
		CheckoutEntryFrontendHooks::register();
		DiagnosticsHooks::register();

		add_action( 'woocommerce_init', array( __CLASS__, 'register_woocommerce_dependent_hooks' ), 30 );
	}

	/**
	 * Хуки WooCommerce после готовности интеграции.
	 */
	public static function register_woocommerce_dependent_hooks(): void {
		if ( ! DependencyFailureGuard::is_woocommerce_integration_ready() ) {
			return;
		}

		CheckoutEntryService::register();
		CheckoutSessionService::register();
		OrderMetaHooks::register();
		EmailHooks::register();
	}
}
