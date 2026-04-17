<?php
/**
 * Интеграция с купонами WooCommerce (корзина).
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout\Integrations\WooCommerce;

defined( 'ABSPATH' ) || exit;

/**
 * Class CouponIntegration
 */
final class CouponIntegration {

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
	 * Применить купон по коду.
	 *
	 * @return true|\WP_Error
	 */
	public function apply_coupon( string $code ) {
		$cart = $this->woo_access->get_cart();
		if ( ! $cart ) {
			return new \WP_Error(
				'mp_cc_no_cart',
				__( 'Корзина WooCommerce недоступна.', 'mp-custom-checkout' )
			);
		}

		$code = wc_format_coupon_code( $code );
		$result = $cart->apply_coupon( $code );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return true;
	}

	/**
	 * Список применённых кодов купонов.
	 *
	 * @return array<int, string>
	 */
	public function get_applied_coupons(): array {
		$cart = $this->woo_access->get_cart();
		if ( ! $cart ) {
			return array();
		}

		return $cart->get_applied_coupons();
	}
}
