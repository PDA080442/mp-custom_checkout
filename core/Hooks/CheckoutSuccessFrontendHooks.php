<?php
/**
 * CSS/JS экрана успеха (маршрут после оплаты).
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout\Hooks;

use MP\CustomCheckout\DependencyFailureGuard;
use MP\CustomCheckout\Routing\CheckoutSuccessRouteHooks;

defined( 'ABSPATH' ) || exit;

/**
 * Class CheckoutSuccessFrontendHooks
 */
final class CheckoutSuccessFrontendHooks {

	public const HANDLE_STYLE  = 'mp-cc-checkout-success';
	public const HANDLE_SCRIPT = 'mp-cc-checkout-success';

	/**
	 * Регистрация хуков (до template_redirect, чтобы очередь попала в wp_head).
	 */
	public static function register(): void {
		add_action( 'wp', array( __CLASS__, 'maybe_enqueue' ), 10 );
		add_filter( 'body_class', array( __CLASS__, 'body_class' ), 10, 1 );
	}

	/**
	 * Подключение ассетов только на маршруте успеха.
	 */
	public static function maybe_enqueue(): void {
		if ( ! CheckoutSuccessRouteHooks::is_success_route() ) {
			return;
		}

		if ( ! DependencyFailureGuard::is_woocommerce_integration_ready() ) {
			return;
		}

		wp_enqueue_style(
			self::HANDLE_STYLE,
			MP_CUSTOM_CHECKOUT_URL . 'assets/css/checkout-success.css',
			array(),
			MP_CUSTOM_CHECKOUT_VERSION
		);

		wp_enqueue_script(
			self::HANDLE_SCRIPT,
			MP_CUSTOM_CHECKOUT_URL . 'assets/js/checkout-success.js',
			array(),
			MP_CUSTOM_CHECKOUT_VERSION,
			true
		);
	}

	/**
	 * Класс body для темизации.
	 *
	 * @param array<int, string> $classes Классы.
	 * @return array<int, string>
	 */
	public static function body_class( array $classes ): array {
		if ( ! CheckoutSuccessRouteHooks::is_success_route() ) {
			return $classes;
		}

		$classes[] = 'mp-cc-success-page';

		return $classes;
	}
}
