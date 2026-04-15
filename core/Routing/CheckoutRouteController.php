<?php
/**
 * Контроллер рендера страницы кастомного checkout.
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout\Routing;

use MP\CustomCheckout\Hooks\CheckoutRouteHooks;

defined( 'ABSPATH' ) || exit;

/**
 * Class CheckoutRouteController
 */
final class CheckoutRouteController {

	/**
	 * Подписка на dispatch маршрута.
	 */
	public static function register(): void {
		add_action( 'mp_custom_checkout_route_dispatch', array( __CLASS__, 'dispatch' ), 10 );
	}

	/**
	 * Обработка кастомного маршрута checkout.
	 */
	public static function dispatch(): void {
		if ( ! CheckoutRouteHooks::is_checkout_route() ) {
			return;
		}

		$context = CheckoutRouteContext::collect();

		/**
		 * Перед проверками и рендером checkout.
		 *
		 * @param array<string, mixed> $context Контекст маршрута.
		 */
		do_action( 'mp_custom_checkout_route_before_guard', $context );

		if ( ! CheckoutEntryGuard::can_enter() ) {
			$code = CheckoutEntryGuard::get_last_failure_code();
			CheckoutRouteLogger::log_failure(
				$code,
				array( 'context' => $context )
			);
			CheckoutRouteFallback::redirect_with_notice( $code, $context );
		}

		$template = MP_CUSTOM_CHECKOUT_PATH . 'templates/checkout.php';
		if ( ! is_readable( $template ) ) {
			CheckoutRouteLogger::log_failure(
				'template_missing',
				array( 'path' => $template )
			);
			CheckoutRouteFallback::redirect_with_notice( 'template_missing', $context );
		}

		/**
		 * Перед выводом шаблона checkout.
		 *
		 * @param array<string, mixed> $context Контекст маршрута.
		 */
		do_action( 'mp_custom_checkout_route_before_render', $context );

		status_header( 200 );
		nocache_headers();

		$mp_cc_checkout_context = $context;

		include $template;

		exit;
	}
}
