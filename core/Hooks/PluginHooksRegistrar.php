<?php
/**
 * Регистрация основных хуков жизненного цикла плагина (фаза 1.5).
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout\Hooks;

use MP\CustomCheckout\DependencyFailureGuard;
use MP\CustomCheckout\Checkout\Routing\CheckoutEntryService;
use MP\CustomCheckout\Checkout\Routing\CheckoutPermalinkCompatibility;
use MP\CustomCheckout\Checkout\Routing\CheckoutRouteController;
use MP\CustomCheckout\Checkout\Routing\CheckoutSessionService;
use MP\CustomCheckout\Checkout\Routing\CheckoutSuccessController;
use MP\CustomCheckout\Checkout\Routing\CheckoutSuccessOrderReceivedRedirect;
use MP\CustomCheckout\Checkout\Routing\CheckoutSuccessRouteHooks;
use MP\CustomCheckout\Admin\Hooks\AdminAssetsHooks;
use MP\CustomCheckout\Admin\Hooks\AdminMenuHooks;
use MP\CustomCheckout\Frontend\Hooks\CheckoutEntryFrontendHooks;
use MP\CustomCheckout\Frontend\Hooks\CheckoutNoJsFallbackHooks;
use MP\CustomCheckout\Frontend\Hooks\CheckoutSuccessFrontendHooks;
use MP\CustomCheckout\Frontend\Hooks\FrontendAssetsHooks;
use MP\CustomCheckout\Checkout\Hooks\CheckoutAjaxHooks;
use MP\CustomCheckout\Checkout\Hooks\CheckoutEntryAjaxHooks;
use MP\CustomCheckout\Checkout\Hooks\EmailHooks;
use MP\CustomCheckout\Checkout\Hooks\OrderMetaHooks;

defined( 'ABSPATH' ) || exit;

/**
 * Class PluginHooksRegistrar
 */
final class PluginHooksRegistrar {

	/**
	 * Подключение всех регистраторов хуков.
	 */
	public static function register(): void {
		ActivationRewriteHooks::register();
		FrontendAssetsHooks::register();
		AdminAssetsHooks::register();
		AdminMenuHooks::register();
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
		CheckoutLogsHooks::register();

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
