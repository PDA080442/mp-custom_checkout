<?php
/**
 * Фронтенд: скрипт привязки перехода к оформлению из sticky-корзины.
 *
 * Ручная проверка сценариев: главная, каталог, товар, корзина (пустая/непустая),
 * прямой URL checkout без AJAX — редирект с уведомлением.
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout\Hooks;

use MP\CustomCheckout\DependencyFailureGuard;
use MP\CustomCheckout\Routing\CheckoutRouteConfig;

defined( 'ABSPATH' ) || exit;

/**
 * Class CheckoutEntryFrontendHooks
 */
final class CheckoutEntryFrontendHooks {

	public const HANDLE_SCRIPT = 'mp-cc-checkout-entry';

	/**
	 * Регистрация enqueue и хука привязки к корзине.
	 */
	public static function register(): void {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ), 25 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'bind_sticky_cart_hook' ), 99 );
	}

	/**
	 * Глобальный скрипт для темы / sticky-корзины: grant + редирект на checkout URL.
	 */
	public static function enqueue(): void {
		if ( is_admin() ) {
			return;
		}

		if ( ! apply_filters( 'mp_custom_checkout_enqueue_entry_script', true ) ) {
			return;
		}

		if ( ! DependencyFailureGuard::is_woocommerce_integration_ready() ) {
			return;
		}

		wp_register_script(
			self::HANDLE_SCRIPT,
			MP_CUSTOM_CHECKOUT_URL . 'assets/js/checkout-entry.js',
			array( 'jquery' ),
			MP_CUSTOM_CHECKOUT_VERSION,
			true
		);

		wp_enqueue_script( self::HANDLE_SCRIPT );

		wp_localize_script(
			self::HANDLE_SCRIPT,
			'mpCcCheckoutEntry',
			array(
				'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
				'action'       => CheckoutEntryAjaxHooks::ACTION,
				'nonce'        => wp_create_nonce( 'mp_cc_checkout_entry' ),
				'checkoutUrl'  => CheckoutRouteConfig::get_checkout_url(),
				'i18n'         => array(
					'emptyCart' => __( 'Корзина пуста. Добавьте товары.', 'mp-custom-checkout' ),
					'error'     => __( 'Не удалось подготовить оформление заказа.', 'mp-custom-checkout' ),
				),
			)
		);

		/**
		 * Точка расширения: дополнительные данные для sticky-корзины.
		 *
		 * @param string $handle Идентификатор скрипта.
		 */
		do_action( 'mp_custom_checkout_bind_sticky_cart', self::HANDLE_SCRIPT );
	}

	/**
	 * После регистрации ассетов — хук для темы (подписка на клик в корзине).
	 */
	public static function bind_sticky_cart_hook(): void {
		if ( ! DependencyFailureGuard::is_woocommerce_integration_ready() ) {
			return;
		}

		/**
		 * Привязка UI sticky-корзины к переходу на checkout (подписывается тема/плагин корзины).
		 */
		do_action( 'mp_custom_checkout_sticky_cart_ready' );
	}
}
