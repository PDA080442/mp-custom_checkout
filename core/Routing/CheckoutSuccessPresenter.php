<?php
/**
 * Данные для шаблона экрана успеха (заказ, статус, оплата, лейблы).
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout\Routing;

use MP\CustomCheckout\Settings\SafeSettingsResolver;

defined( 'ABSPATH' ) || exit;

/**
 * Class CheckoutSuccessPresenter
 */
final class CheckoutSuccessPresenter {

	/**
	 * @return array<string, mixed>
	 */
	public static function build( \WC_Order $order ): array {
		$created = $order->get_date_created();

		return array(
			'order'                 => $order,
			'order_number'          => $order->get_order_number(),
			'status'                => $order->get_status(),
			'status_label'          => wc_get_order_status_name( $order->get_status() ),
			'payment_method'        => $order->get_payment_method_title(),
			'payment_method_id'     => $order->get_payment_method(),
			'total_formatted'       => $order->get_formatted_order_total(),
			'date_formatted'        => $created ? $created->date_i18n( wc_date_format() ) : '',
			'items_count'           => $order->get_item_count(),
			'is_paid'               => $order->is_paid(),
			'needs_payment'         => $order->needs_payment(),
			'labels'                => self::labels(),
			'primary_cta_url'       => self::get_primary_cta_url(),
			'secondary_cta_url'     => self::get_secondary_cta_url(),
		);
	}

	/**
	 * Тексты из настроек (labels.success.*).
	 *
	 * @return array<string, string>
	 */
	private static function labels(): array {
		$defaults = array(
			'title'               => __( 'Заказ оформлен', 'mp-custom-checkout' ),
			'message'             => __( 'Спасибо за покупку.', 'mp-custom-checkout' ),
			'order_summary_title' => __( 'Ваш заказ', 'mp-custom-checkout' ),
			'order_number'        => __( 'Номер заказа', 'mp-custom-checkout' ),
			'status'              => __( 'Статус', 'mp-custom-checkout' ),
			'payment'             => __( 'Способ оплаты', 'mp-custom-checkout' ),
			'total'               => __( 'Итого', 'mp-custom-checkout' ),
			'date'                => __( 'Дата', 'mp-custom-checkout' ),
			'cta_primary_label'   => __( 'В магазин', 'mp-custom-checkout' ),
			'cta_primary_url'     => '',
			'cta_secondary_label' => '',
			'cta_secondary_url'   => '',
		);

		$from_settings = SafeSettingsResolver::get_section( 'labels' );
		$success       = isset( $from_settings['success'] ) && is_array( $from_settings['success'] )
			? $from_settings['success']
			: array();

		$merged = array_merge( $defaults, $success );

		return (array) apply_filters( 'mp_custom_checkout_success_labels', $merged, $defaults );
	}

	/**
	 * URL для основной CTA (магазин).
	 */
	public static function get_primary_cta_url(): string {
		$url = SafeSettingsResolver::get( 'labels.success.cta_primary_url', '' );
		if ( is_string( $url ) && '' !== $url ) {
			return esc_url( $url );
		}

		return CheckoutReturnPaths::get_shop_url();
	}

	/**
	 * URL вторичной CTA (опционально).
	 */
	public static function get_secondary_cta_url(): string {
		$url = SafeSettingsResolver::get( 'labels.success.cta_secondary_url', '' );
		if ( is_string( $url ) && '' !== $url ) {
			return esc_url( $url );
		}

		return '';
	}
}
