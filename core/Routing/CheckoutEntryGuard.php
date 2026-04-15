<?php
/**
 * Ограничение входа на checkout (пустая корзина, сценарий входа из корзины).
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout\Routing;

use MP\CustomCheckout\DependencyFailureGuard;

defined( 'ABSPATH' ) || exit;

/**
 * Class CheckoutEntryGuard
 */
final class CheckoutEntryGuard {

	/**
	 * @var string
	 */
	private static $last_failure_code = '';

	/**
	 * Последняя причина отказа (код для логов и fallback).
	 */
	public static function get_last_failure_code(): string {
		return self::$last_failure_code;
	}

	/**
	 * Можно ли показывать checkout в текущем запросе.
	 */
	public static function can_enter(): bool {
		self::$last_failure_code = '';

		if ( ! DependencyFailureGuard::is_woocommerce_integration_ready() ) {
			self::$last_failure_code = 'woocommerce_not_ready';
			return false;
		}

		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			self::$last_failure_code = 'woocommerce_not_ready';
			return false;
		}

		$cart = WC()->cart;
		if ( $cart->is_empty() ) {
			self::$last_failure_code = 'empty_cart';
			return false;
		}

		if ( self::requires_sticky_cart_entry() && ! self::has_entry_session_flag() ) {
			self::$last_failure_code = 'entry_not_allowed';
			return false;
		}

		return true;
	}

	/**
	 * Требуется ли явный вход из кастомной корзины (сессионный флаг).
	 */
	private static function requires_sticky_cart_entry(): bool {
		return (bool) apply_filters( 'mp_custom_checkout_require_entry_gate', false );
	}

	/**
	 * Флаг допустимого входа в checkout (устанавливается сценарием корзины).
	 */
	private static function has_entry_session_flag(): bool {
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			return false;
		}

		return (bool) WC()->session->get( 'mp_cc_checkout_entry', false );
	}
}
