<?php
/**
 * Доступ к корзине, сессии, checkout и платёжным шлюзам WooCommerce.
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout\Integrations\WooCommerce;

defined( 'ABSPATH' ) || exit;

/**
 * Class WooAccessService
 */
final class WooAccessService {

	/**
	 * Экземпляр корзины или null, если API ещё недоступно.
	 */
	public function get_cart(): ?\WC_Cart {
		if ( ! function_exists( 'WC' ) || ! WC() ) {
			return null;
		}

		$cart = WC()->cart;
		return $cart instanceof \WC_Cart ? $cart : null;
	}

	/**
	 * Сессия WooCommerce.
	 */
	public function get_session(): ?\WC_Session {
		if ( ! function_exists( 'WC' ) || ! WC() ) {
			return null;
		}

		$session = WC()->session;
		return $session instanceof \WC_Session ? $session : null;
	}

	/**
	 * Объект оформления заказа WooCommerce.
	 */
	public function get_checkout(): ?\WC_Checkout {
		if ( ! function_exists( 'WC' ) || ! WC() ) {
			return null;
		}

		$checkout = WC()->checkout();
		return $checkout instanceof \WC_Checkout ? $checkout : null;
	}

	/**
	 * Менеджер платёжных шлюзов.
	 */
	public function get_payment_gateways(): ?\WC_Payment_Gateways {
		if ( ! function_exists( 'WC' ) || ! WC() ) {
			return null;
		}

		$gateways = WC()->payment_gateways();
		return $gateways instanceof \WC_Payment_Gateways ? $gateways : null;
	}

	/**
	 * Список доступных (настроенных) гейтов для зоны checkout.
	 *
	 * @return array<string, \WC_Payment_Gateway>
	 */
	public function get_available_payment_gateways(): array {
		$pm = $this->get_payment_gateways();
		if ( ! $pm ) {
			return array();
		}

		return $pm->get_available_payment_gateways();
	}
}
