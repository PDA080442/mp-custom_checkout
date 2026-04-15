<?php
/**
 * Fallback при невозможности отобразить кастомный checkout.
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout\Routing;

defined( 'ABSPATH' ) || exit;

/**
 * Class CheckoutRouteFallback
 */
final class CheckoutRouteFallback {

	/**
	 * Редирект с уведомлением (WooCommerce notice при наличии).
	 *
	 * @param array<string, mixed> $context Доп. данные для фильтра.
	 */
	public static function redirect_with_notice( string $reason_code, array $context = array() ): void {
		$url = self::resolve_redirect_url( $reason_code, $context );

		$message = self::resolve_notice_message( $reason_code );
		if ( '' !== $message && function_exists( 'wc_add_notice' ) ) {
			wc_add_notice( $message, 'error' );
		}

		$url = (string) apply_filters( 'mp_custom_checkout_route_fallback_url', $url, $reason_code, $context );

		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * URL для редиректа: корзина WooCommerce или главная.
	 *
	 * @param array<string, mixed> $context Контекст.
	 */
	private static function resolve_redirect_url( string $reason_code, array $context ): string {
		return CheckoutReturnPaths::get_url_for_reason( $reason_code, $context );
	}

	/**
	 * Тексты уведомлений по коду причины.
	 */
	private static function resolve_notice_message( string $reason_code ): string {
		$messages = array(
			'woocommerce_not_ready' => __( 'Оформление заказа временно недоступно.', 'mp-custom-checkout' ),
			'empty_cart'              => __( 'Корзина пуста. Добавьте товары для оформления заказа.', 'mp-custom-checkout' ),
			'entry_not_allowed'       => __( 'Откройте оформление заказа из корзины.', 'mp-custom-checkout' ),
			'template_missing'        => __( 'Не удалось загрузить страницу оформления заказа.', 'mp-custom-checkout' ),
		);

		$text = isset( $messages[ $reason_code ] ) ? $messages[ $reason_code ] : __( 'Не удалось открыть checkout.', 'mp-custom-checkout' );

		return (string) apply_filters( 'mp_custom_checkout_route_fallback_message', $text, $reason_code );
	}
}
