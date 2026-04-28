<?php
/**
 * Серверный fallback checkout для сценария без JavaScript.
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout\Frontend\Hooks;

use MP\CustomCheckout\DependencyFailureGuard;
use MP\CustomCheckout\Routing\CheckoutReturnPaths;

defined( 'ABSPATH' ) || exit;

final class CheckoutNoJsFallbackHooks {
	public static function register(): void {
		add_action( 'mp_custom_checkout_render_content', array( __CLASS__, 'render' ), 10, 1 );
	}

	public static function render( array $context ): void {
		unset( $context );
		echo '<div id="mp-cc-checkout-app" class="mp-cc-checkout__app" aria-live="off"></div>';
		self::render_nojs_fallback();
	}

	private static function render_nojs_fallback(): void {
		?>
		<noscript>
			<section class="mp-cc-nojs" aria-labelledby="mp-cc-nojs-title">
				<h2 id="mp-cc-nojs-title"><?php esc_html_e( 'Оформление без JavaScript', 'mp-custom-checkout' ); ?></h2>
				<p><?php esc_html_e( 'Вы используете упрощенный режим checkout. Валидация и сохранение данных формы выполняются на сервере.', 'mp-custom-checkout' ); ?></p>
				<p><?php esc_html_e( 'Купоны и подарочные карты без AJAX могут обновляться не мгновенно. При необходимости примените их в корзине перед оплатой.', 'mp-custom-checkout' ); ?></p>
				<ol class="mp-cc-nojs__steps">
					<li><?php esc_html_e( 'Заполните контактные и адресные данные.', 'mp-custom-checkout' ); ?></li>
					<li><?php esc_html_e( 'Проверьте состав заказа, доставку и итоговую сумму.', 'mp-custom-checkout' ); ?></li>
					<li><?php esc_html_e( 'Выберите способ оплаты и подтвердите заказ.', 'mp-custom-checkout' ); ?></li>
				</ol>
				<?php self::render_nojs_checkout_form(); ?>
			</section>
		</noscript>
		<?php
	}

	private static function render_nojs_checkout_form(): void {
		if ( ! DependencyFailureGuard::is_woocommerce_integration_ready() ) {
			echo '<p>' . esc_html__( 'WooCommerce недоступен.', 'mp-custom-checkout' ) . '</p>';
			return;
		}
		if ( ! function_exists( 'WC' ) ) {
			echo '<p>' . esc_html__( 'Checkout временно недоступен.', 'mp-custom-checkout' ) . '</p>';
			return;
		}
		$cart = WC()->cart;
		if ( ! $cart || $cart->is_empty() ) {
			$shop_url = CheckoutReturnPaths::get_shop_url();
			echo '<p>' . esc_html__( 'Корзина пуста. Добавьте товары и повторите оформление.', 'mp-custom-checkout' ) . '</p>';
			echo '<p><a href="' . esc_url( $shop_url ) . '">' . esc_html__( 'Перейти в магазин', 'mp-custom-checkout' ) . '</a></p>';
			return;
		}

		/*
		 * Не выводим wc_get_template( 'checkout/form-checkout.php' ) здесь: даже при включённом JS
		 * полный нативный чекаут оказывается в DOM внутри <noscript> и даёт «все поля сразу» / лишний шум для a11y.
		 * Классическое оформление без JS — на стандартной странице checkout WC (URL из БД, без фильтра mp-checkout).
		 */
		$native_url = self::get_native_woocommerce_checkout_permalink();
		echo '<p>' . esc_html__( 'Для оформления без JavaScript откройте стандартную страницу оформления заказа WooCommerce.', 'mp-custom-checkout' ) . '</p>';
		echo '<p><a class="mp-cc-nojs__wc-link" href="' . esc_url( $native_url ) . '">' . esc_html__( 'Перейти к классическому checkout', 'mp-custom-checkout' ) . '</a></p>';
	}

	/**
	 * Прямой permalink страницы checkout из настроек WC (обходит filter_woocommerce_checkout_url → /mp-checkout/).
	 */
	private static function get_native_woocommerce_checkout_permalink(): string {
		if ( ! function_exists( 'wc_get_page_id' ) ) {
			return home_url( '/' );
		}
		$page_id = (int) wc_get_page_id( 'checkout' );
		if ( $page_id <= 0 ) {
			return function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/' );
		}
		$permalink = get_permalink( $page_id );
		return is_string( $permalink ) && '' !== $permalink ? $permalink : home_url( '/' );
	}
}
