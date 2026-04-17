<?php
/**
 * Данные для шаблона экрана успеха (заказ, статус, оплата, лейблы).
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout\Routing;

use MP\CustomCheckout\Checkout\Order\OrderMetaKeys;
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
		$scenario_label = self::get_scenario_label( $order );
		$date_label = self::get_fulfillment_date_label( $order );
		$pickup_value = self::get_pickup_point_label( $order );
		$contact_summary = self::build_contact_summary( $order );
		$financial_summary = self::build_financial_summary( $order );

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
			'fulfillment'           => array(
				'scenario_label' => $scenario_label,
				'date_label'     => $date_label,
				'pickup_point'   => $pickup_value,
			),
			'contact_summary'       => $contact_summary,
			'financial_summary'     => $financial_summary,
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
			'fulfillment_title'   => __( 'Получение', 'mp-custom-checkout' ),
			'fulfillment_type'    => __( 'Способ получения', 'mp-custom-checkout' ),
			'fulfillment_date'    => __( 'Дата получения/доставки', 'mp-custom-checkout' ),
			'pickup_point'        => __( 'Точка самовывоза', 'mp-custom-checkout' ),
			'contact_title'       => __( 'Контактные данные', 'mp-custom-checkout' ),
			'recipient'           => __( 'Получатель', 'mp-custom-checkout' ),
			'email_masked'        => __( 'Email', 'mp-custom-checkout' ),
			'phone_masked'        => __( 'Телефон', 'mp-custom-checkout' ),
			'financial_title'     => __( 'Финансовый итог', 'mp-custom-checkout' ),
			'subtotal'            => __( 'Подытог', 'mp-custom-checkout' ),
			'shipping'            => __( 'Доставка', 'mp-custom-checkout' ),
			'discount'            => __( 'Скидка', 'mp-custom-checkout' ),
			'tax'                 => __( 'Налог', 'mp-custom-checkout' ),
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

	private static function get_scenario_label( \WC_Order $order ): string {
		$raw = trim( (string) $order->get_meta( OrderMetaKeys::SCENARIO_LABEL, true ) );
		if ( '' !== $raw ) {
			return $raw;
		}
		$id = trim( (string) $order->get_meta( OrderMetaKeys::SCENARIO_ID, true ) );
		if ( '' === $id ) {
			return '';
		}
		return CheckoutScenarioRules::normalize_label_for_output( $id, $id );
	}

	private static function get_fulfillment_date_label( \WC_Order $order ): string {
		$label = trim( (string) $order->get_meta( OrderMetaKeys::SELECTED_DATE_LABEL, true ) );
		if ( '' !== $label ) {
			return $label;
		}
		$iso = trim( (string) $order->get_meta( OrderMetaKeys::SELECTED_DATE, true ) );
		if ( '' === $iso ) {
			return '';
		}
		$dt = \DateTimeImmutable::createFromFormat( 'Y-m-d', $iso, wp_timezone() );
		return $dt instanceof \DateTimeImmutable ? $dt->format( 'd.m.Y' ) : $iso;
	}

	private static function get_pickup_point_label( \WC_Order $order ): string {
		$title = trim( (string) $order->get_meta( OrderMetaKeys::PICKUP_POINT_TITLE, true ) );
		$address = trim( (string) $order->get_meta( OrderMetaKeys::PICKUP_POINT_ADDRESS, true ) );
		return trim( $title . ( '' !== $address ? ' — ' . $address : '' ) );
	}

	/**
	 * Возвращаем только безопасную сводку контакта (без полного адреса и без полного email/телефона).
	 *
	 * @return array<string, string>
	 */
	private static function build_contact_summary( \WC_Order $order ): array {
		$name = trim( $order->get_billing_last_name() . ' ' . $order->get_billing_first_name() );
		$patronymic = trim( (string) $order->get_meta( OrderMetaKeys::BILLING_PATRONYMIC, true ) );
		if ( '' !== $patronymic ) {
			$name = trim( $name . ' ' . $patronymic );
		}
		return array(
			'recipient' => $name,
			'email'     => self::mask_email( (string) $order->get_billing_email() ),
			'phone'     => self::mask_phone( (string) $order->get_billing_phone() ),
		);
	}

	/**
	 * @return array<string, string>
	 */
	private static function build_financial_summary( \WC_Order $order ): array {
		$currency = $order->get_currency();
		$format_args = array( 'currency' => $currency );
		$discount_total = (float) $order->get_discount_total();
		$shipping_total = (float) $order->get_shipping_total();
		$tax_total = (float) $order->get_total_tax();
		return array(
			'subtotal' => wp_strip_all_tags( wc_price( (float) $order->get_subtotal(), $format_args ) ),
			'shipping' => $shipping_total > 0 ? wp_strip_all_tags( wc_price( $shipping_total, $format_args ) ) : '',
			'discount' => $discount_total > 0 ? wp_strip_all_tags( wc_price( $discount_total, $format_args ) ) : '',
			'tax'      => $tax_total > 0 ? wp_strip_all_tags( wc_price( $tax_total, $format_args ) ) : '',
			'total'    => wp_strip_all_tags( html_entity_decode( (string) $order->get_formatted_order_total() ) ),
		);
	}

	private static function mask_email( string $email ): string {
		$email = trim( $email );
		if ( '' === $email || false === strpos( $email, '@' ) ) {
			return '';
		}
		list( $local, $domain ) = explode( '@', $email, 2 );
		$local = trim( $local );
		if ( '' === $local ) {
			return '*@' . $domain;
		}
		$first = function_exists( 'mb_substr' ) ? mb_substr( $local, 0, 1 ) : substr( $local, 0, 1 );
		return $first . '***@' . $domain;
	}

	private static function mask_phone( string $phone ): string {
		$phone = trim( $phone );
		if ( '' === $phone ) {
			return '';
		}
		$digits = preg_replace( '/\D+/', '', $phone );
		if ( ! is_string( $digits ) || strlen( $digits ) < 4 ) {
			return '***';
		}
		return '***' . substr( $digits, -4 );
	}
}
