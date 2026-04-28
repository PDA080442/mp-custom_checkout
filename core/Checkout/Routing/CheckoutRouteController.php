<?php
/**
 * Контроллер рендера страницы кастомного checkout.
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout\Checkout\Routing;

use MP\CustomCheckout\Hooks\CheckoutRouteHooks;
use MP\CustomCheckout\Routing\CheckoutEntryGuard;
use MP\CustomCheckout\Routing\CheckoutRouteConfig;
use MP\CustomCheckout\Routing\CheckoutRouteContext;
use MP\CustomCheckout\Routing\CheckoutRouteFallback;
use MP\CustomCheckout\Routing\CheckoutRouteLogger;
use MP\CustomCheckout\Routing\CheckoutStepManager;

defined( 'ABSPATH' ) || exit;

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

		$order_pay_id = self::resolve_order_pay_order_id();
		if ( $order_pay_id > 0 ) {
			self::render_order_pay_and_exit( $order_pay_id );
		}

		$step_manager   = new CheckoutStepManager();
		$requested_step = isset( $_GET['step'] ) ? sanitize_key( wp_unslash( $_GET['step'] ) ) : '';
		if ( '' !== $requested_step ) {
			$resolved_step = $step_manager->resolve_requested_step( $requested_step );
			if ( null === $resolved_step ) {
				CheckoutRouteLogger::log_failure(
					'invalid_step_navigation',
					array(
						'requested_step' => $requested_step,
						'current_step'   => $step_manager->get_current_step_id(),
					)
				);

				$fallback_step = $step_manager->get_current_step_id();
				$query_args    = is_string( $fallback_step ) && '' !== $fallback_step ? array( 'step' => $fallback_step ) : array();
				wp_safe_redirect( CheckoutRouteConfig::get_checkout_url( $query_args ) );
				exit;
			}

			$step_manager->set_current_step_id( $resolved_step );
		}

		if ( function_exists( 'wc_load_cart' ) ) {
			wc_load_cart();
		}

		$context = CheckoutRouteContext::collect();

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

		do_action( 'mp_custom_checkout_route_before_render', $context );

		status_header( 200 );
		nocache_headers();

		$GLOBALS['mp_cc_checkout_context'] = $context;
		$mp_cc_checkout_context            = $context;

		include $template;

		exit;
	}

	/**
	 * ID заказа из endpoint order-pay (ЧПУ или plain ?mpcc_checkout=1&mpcc_order_pay=…).
	 */
	private static function resolve_order_pay_order_id(): int {
		$id = (int) get_query_var( CheckoutRouteHooks::QUERY_VAR_ORDER_PAY, 0 );
		if ( $id <= 0 && isset( $_GET[ CheckoutRouteHooks::QUERY_VAR_ORDER_PAY ], $_GET[ CheckoutRouteHooks::QUERY_VAR ] ) && '1' === (string) wp_unslash( (string) $_GET[ CheckoutRouteHooks::QUERY_VAR ] ) ) {
			$id = absint( wp_unslash( (string) $_GET[ CheckoutRouteHooks::QUERY_VAR_ORDER_PAY ] ) );
		}
		return max( 0, $id );
	}

	/**
	 * Нативная страница оплаты заказа WooCommerce (Robokassa и др. редиректят сюда после process_payment).
	 *
	 * Нельзя вызывать form-pay.php напрямую: шаблон ожидает $order и $available_gateways (см. WC_Shortcode_Checkout::order_pay).
	 * Делегируем в тот же обработчик, что и [woocommerce_checkout] на странице checkout.
	 */
	private static function render_order_pay_and_exit( int $order_id ): void {
		if ( ! function_exists( 'WC' ) || ! class_exists( '\WC_Shortcode_Checkout' ) ) {
			CheckoutRouteFallback::redirect_with_notice( 'woocommerce_not_ready', CheckoutRouteContext::collect() );
		}

		global $wp;
		if ( $wp instanceof \WP ) {
			$wp->query_vars['order-pay'] = (string) $order_id;
		}
		set_query_var( 'order-pay', $order_id );

		if ( function_exists( 'wc_maybe_define_constant' ) ) {
			wc_maybe_define_constant( 'WOOCOMMERCE_CHECKOUT', true );
		}

		if ( function_exists( 'wc_load_cart' ) ) {
			wc_load_cart();
		}

		do_action( 'mp_custom_checkout_route_before_order_pay', $order_id );

		status_header( 200 );
		nocache_headers();

		get_header();
		\WC_Shortcode_Checkout::output( array() );
		get_footer();
		exit;
	}
}
