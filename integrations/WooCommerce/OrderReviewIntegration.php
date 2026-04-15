<?php
/**
 * Интеграция с фрагментами и шаблоном order review WooCommerce.
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout\Integrations\WooCommerce;

defined( 'ABSPATH' ) || exit;

/**
 * Class OrderReviewIntegration
 */
final class OrderReviewIntegration {

	/**
	 * Применяет фильтр фрагментов обновления order review (AJAX checkout).
	 *
	 * @param array<string, string> $fragments Ключ => HTML фрагмента.
	 * @return array<string, string>
	 */
	public function apply_order_review_fragments( array $fragments ): array {
		return apply_filters( 'woocommerce_update_order_review_fragments', $fragments );
	}

	/**
	 * HTML блока review заказа через стандартный шаблон WooCommerce.
	 */
	public function get_order_review_html(): string {
		if ( ! function_exists( 'woocommerce_order_review' ) ) {
			return '';
		}

		ob_start();
		woocommerce_order_review();
		return (string) ob_get_clean();
	}

	/**
	 * HTML подытогов корзины (cart totals) в оформлении заказа.
	 */
	public function get_cart_totals_html(): string {
		if ( ! function_exists( 'woocommerce_cart_totals' ) ) {
			return '';
		}

		ob_start();
		woocommerce_cart_totals();
		return (string) ob_get_clean();
	}
}
