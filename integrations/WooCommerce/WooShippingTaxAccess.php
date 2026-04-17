<?php
/**
 * Чтение способов доставки и налоговых данных через WooCommerce.
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout\Integrations\WooCommerce;

defined( 'ABSPATH' ) || exit;

/**
 * Class WooShippingTaxAccess
 */
final class WooShippingTaxAccess {

	/**
	 * @var WooAccessService
	 */
	private $woo_access;

	/**
	 * @param WooAccessService $woo_access Сервис доступа к WC.
	 */
	public function __construct( WooAccessService $woo_access ) {
		$this->woo_access = $woo_access;
	}

	/**
	 * Пакеты доставки в корзине (как у WC для расчёта доставки).
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_shipping_packages(): array {
		$cart = $this->woo_access->get_cart();
		if ( ! $cart ) {
			return array();
		}

		return $cart->get_shipping_packages();
	}

	/**
	 * Итог по доставке (как строка с валютой) из корзины.
	 */
	public function get_cart_shipping_total_formatted(): string {
		$cart = $this->woo_access->get_cart();
		if ( ! $cart ) {
			return '';
		}

		return wc_price( $cart->get_shipping_total() );
	}

	/**
	 * Сумма налогов в корзине (число).
	 */
	public function get_cart_tax_total(): float {
		$cart = $this->woo_access->get_cart();
		if ( ! $cart ) {
			return 0.0;
		}

		return (float) $cart->get_total_tax();
	}

	/**
	 * Разбивка налогов по ставкам (если включено отображение налогов).
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_cart_tax_totals(): array {
		$cart = $this->woo_access->get_cart();
		if ( ! $cart ) {
			return array();
		}

		return $cart->get_tax_totals();
	}

	/**
	 * Пакеты доставки с рассчитанными ставками (после расчёта доставки в WooCommerce).
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_shipping_packages_with_rates(): array {
		if ( ! function_exists( 'WC' ) || ! WC() ) {
			return array();
		}

		$shipping = WC()->shipping;
		if ( ! $shipping ) {
			return array();
		}

		return $shipping->get_packages();
	}
}
