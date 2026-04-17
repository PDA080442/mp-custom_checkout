<?php
/**
 * Ограничение входа на checkout (пустая корзина, сценарий входа из корзины).
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout\Routing;

use MP\CustomCheckout\Checkout\Routing\CheckoutEntryService;
use MP\CustomCheckout\DependencyFailureGuard;
use MP\CustomCheckout\Settings\SafeSettingsResolver;

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
			// Кнопка WooCommerce «Оформить заказ» и прямой URL не проходят через AJAX sticky-корзины.
			if ( function_exists( 'WC' ) && WC()->session ) {
				CheckoutEntryService::grant_entry_eligibility();
			}
			if ( apply_filters( 'mp_custom_checkout_bypass_entry_gate', false ) ) {
				return true;
			}
			if ( ! self::has_entry_session_flag() ) {
				self::$last_failure_code = 'entry_not_allowed';
				return false;
			}
		}

		return true;
	}

	/**
	 * Требуется ли явный вход из кастомной корзины (сессионный флаг).
	 */
	private static function requires_sticky_cart_entry(): bool {
		$default = (bool) SafeSettingsResolver::get( 'general.require_entry_gate', true );

		return (bool) apply_filters( 'mp_custom_checkout_require_entry_gate', $default );
	}

	/**
	 * Флаг допустимого входа в checkout (сессия WooCommerce).
	 */
	private static function has_entry_session_flag(): bool {
		return CheckoutEntryService::has_entry_eligibility();
	}
}
