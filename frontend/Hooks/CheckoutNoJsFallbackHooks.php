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
		echo '<div id="mp-cc-checkout-app" class="mp-cc-checkout__app" aria-live="polite"></div>';
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
		if ( ! function_exists( 'WC' ) || ! function_exists( 'wc_get_template' ) ) {
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
		$checkout = WC()->checkout();
		if ( ! $checkout instanceof \WC_Checkout ) {
			echo '<p>' . esc_html__( 'Не удалось подготовить форму checkout.', 'mp-custom-checkout' ) . '</p>';
			return;
		}
		wc_get_template( 'checkout/form-checkout.php', array( 'checkout' => $checkout ) );
	}
}
