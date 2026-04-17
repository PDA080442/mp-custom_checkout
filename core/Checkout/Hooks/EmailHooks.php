<?php
/**
 * Вывод данных checkout в письмах WooCommerce.
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout\Checkout\Hooks;

use MP\CustomCheckout\DependencyFailureGuard;
use MP\CustomCheckout\Checkout\Order\OrderMetaKeys;
use MP\CustomCheckout\Routing\CheckoutConditionsSummaryBuilder;
use MP\CustomCheckout\Routing\CheckoutScenarioRules;

defined( 'ABSPATH' ) || exit;

final class EmailHooks {

	public static function register(): void {
		if ( ! DependencyFailureGuard::is_woocommerce_integration_ready() ) { return; }
		add_action( 'woocommerce_email_order_meta', array( __CLASS__, 'render_email_order_meta' ), 10, 4 );
		add_action( 'mp_custom_checkout_email_order_meta', array( __CLASS__, 'render_email_checkout_summary' ), 10, 4 );
		add_action( 'woocommerce_admin_order_data_after_order_details', array( __CLASS__, 'render_custom_data_panel_admin' ), 8, 1 );
		add_action( 'woocommerce_admin_order_data_after_billing_address', array( __CLASS__, 'render_scenario_admin' ), 12, 1 );
		add_action( 'woocommerce_admin_order_data_after_billing_address', array( __CLASS__, 'render_pickup_point_admin' ), 15, 1 );
		add_action( 'woocommerce_admin_order_data_after_billing_address', array( __CLASS__, 'render_conditions_summary_admin' ), 18, 1 );
		add_action( 'woocommerce_admin_order_data_after_billing_address', array( __CLASS__, 'render_contact_admin' ), 20, 1 );
		add_action( 'woocommerce_admin_order_data_after_billing_address', array( __CLASS__, 'render_discounts_admin' ), 22, 1 );
		add_filter( 'manage_edit-shop_order_columns', array( __CLASS__, 'add_scenario_order_list_column' ), 25 );
		add_action( 'manage_shop_order_posts_custom_column', array( __CLASS__, 'render_scenario_order_list_column' ), 25, 2 );
		add_filter( 'manage_woocommerce_page_wc-orders_columns', array( __CLASS__, 'add_scenario_order_list_column' ), 25 );
		add_action( 'manage_woocommerce_page_wc-orders_custom_column', array( __CLASS__, 'render_scenario_order_list_column_hpos' ), 25, 2 );
	}

	public static function render_email_checkout_summary( $order, $sent_to_admin, $plain_text, $email = null ): void {
		unset( $email );
		if ( ! $order instanceof \WC_Order ) { return; }

		$scenario_label = self::get_order_scenario_label( $order );
		$date_label = self::get_order_date_label( $order );
		$conditions = self::get_order_conditions_summary( $order );
		$address = self::build_structured_address_single_line( $order );
		$discounts = self::build_discounts_lines_for_output( $order );
		$pickup_title = trim( (string) $order->get_meta( OrderMetaKeys::PICKUP_POINT_TITLE, true ) );
		$pickup_address = trim( (string) $order->get_meta( OrderMetaKeys::PICKUP_POINT_ADDRESS, true ) );
		$pickup_value = trim( $pickup_title . ( '' !== $pickup_address ? ' — ' . $pickup_address : '' ) );

		$rows = array();
		if ( '' !== $scenario_label ) { $rows[] = array( 'Способ получения', $scenario_label ); }
		if ( '' !== $date_label ) { $rows[] = array( 'Дата получения/доставки', $date_label ); }
		if ( '' !== $pickup_value ) { $rows[] = array( 'Точка самовывоза', $pickup_value ); }
		if ( '' !== $address ) { $rows[] = array( 'Адрес', $address ); }
		if ( '' !== $conditions ) { $rows[] = array( 'Условия получения', self::truncate_conditions_one_line( $conditions ) ); }

		if ( $plain_text ) {
			if ( empty( $rows ) && empty( $discounts ) ) { return; }
			$title = $sent_to_admin ? 'Checkout данные (админ)' : 'Checkout данные';
			echo "\n" . sanitize_text_field( $title ) . ":\n";
			foreach ( $rows as $row ) {
				echo '- ' . sanitize_text_field( (string) $row[0] ) . ': ' . sanitize_text_field( (string) $row[1] ) . "\n";
			}
			foreach ( $discounts as $line ) {
				echo '- ' . sanitize_text_field( $line ) . "\n";
			}
			return;
		}

		if ( empty( $rows ) && empty( $discounts ) ) { return; }
		echo '<div style="margin:16px 0;padding:12px;border:1px solid #e5e7eb;border-radius:8px;background:#fafafa;">';
		echo '<p style="margin:0 0 10px 0;font-weight:600;font-size:14px;line-height:1.4;">' . esc_html( $sent_to_admin ? 'Checkout данные (админ)' : 'Checkout данные' ) . '</p>';
		echo '<table role="presentation" cellspacing="0" cellpadding="0" style="width:100%;border-collapse:collapse;font-size:13px;line-height:1.45;">';
		foreach ( $rows as $row ) {
			echo '<tr>';
			echo '<td style="vertical-align:top;padding:4px 8px 4px 0;color:#4b5563;white-space:nowrap;">' . esc_html( (string) $row[0] ) . ':</td>';
			echo '<td style="vertical-align:top;padding:4px 0;color:#111827;">' . esc_html( (string) $row[1] ) . '</td>';
			echo '</tr>';
		}
		foreach ( $discounts as $line ) {
			echo '<tr><td colspan="2" style="padding:4px 0;color:#111827;">' . esc_html( $line ) . '</td></tr>';
		}
		echo '</table>';
		if ( '' !== $conditions ) {
			echo '<div style="margin-top:10px;padding-top:10px;border-top:1px solid #e5e7eb;">';
			echo '<p style="margin:0 0 6px 0;font-weight:600;font-size:12px;">' . esc_html__( 'Условия получения', 'mp-custom-checkout' ) . '</p>';
			echo '<div style="font-size:12px;color:#4b5563;line-height:1.5;">' . self::format_conditions_html( $conditions ) . '</div>';
			echo '</div>';
		}
		echo '</div>';
	}

	public static function render_custom_data_panel_admin( $order ): void {
		if ( ! $order instanceof \WC_Order ) { return; }
		$scenario_label = self::get_order_scenario_label( $order );
		$date_label = self::get_order_date_label( $order );
		$conditions = self::get_order_conditions_summary( $order );
		$address = self::build_structured_address_single_line( $order );
		$gender = trim( (string) $order->get_meta( OrderMetaKeys::GENDER, true ) );
		$birthdate_raw = trim( (string) $order->get_meta( OrderMetaKeys::BILLING_BIRTHDATE, true ) );
		$birthdate = $birthdate_raw;
		$birth_dt = \DateTimeImmutable::createFromFormat( 'Y-m-d', $birthdate_raw, wp_timezone() );
		if ( $birth_dt instanceof \DateTimeImmutable ) { $birthdate = $birth_dt->format( 'd.m.Y' ); }
		$pickup_title = trim( (string) $order->get_meta( OrderMetaKeys::PICKUP_POINT_TITLE, true ) );
		$pickup_address = trim( (string) $order->get_meta( OrderMetaKeys::PICKUP_POINT_ADDRESS, true ) );
		$pickup_value = trim( $pickup_title . ( '' !== $pickup_address ? ' — ' . $pickup_address : '' ) );
		$discounts = self::build_discounts_lines_for_output( $order );
		$has_any = '' !== $scenario_label || '' !== $date_label || '' !== $conditions || '' !== $address || '' !== $gender || '' !== $birthdate || '' !== $pickup_value || ! empty( $discounts );
		if ( ! $has_any ) { return; }
		echo '<div class="order_data_column">';
		echo '<h3>' . esc_html__( 'Custom checkout data', 'mp-custom-checkout' ) . '</h3>';
		echo '<div class="address">';
		if ( '' !== $scenario_label ) { echo '<p><strong>' . esc_html__( 'Способ получения', 'mp-custom-checkout' ) . ':</strong> ' . esc_html( $scenario_label ) . '</p>'; }
		if ( '' !== $date_label ) { echo '<p><strong>' . esc_html__( 'Дата получения', 'mp-custom-checkout' ) . ':</strong> ' . esc_html( $date_label ) . '</p>'; }
		if ( '' !== $pickup_value ) { echo '<p><strong>' . esc_html__( 'Точка самовывоза', 'mp-custom-checkout' ) . ':</strong> ' . esc_html( $pickup_value ) . '</p>'; }
		if ( '' !== $address ) { echo '<p><strong>' . esc_html__( 'Составной адрес', 'mp-custom-checkout' ) . ':</strong> ' . esc_html( $address ) . '</p>'; }
		if ( '' !== $birthdate ) { echo '<p><strong>' . esc_html__( 'Дата рождения', 'mp-custom-checkout' ) . ':</strong> ' . esc_html( $birthdate ) . '</p>'; }
		if ( 'male' === $gender ) { echo '<p><strong>' . esc_html__( 'Пол', 'mp-custom-checkout' ) . ':</strong> ' . esc_html__( 'Мужчина', 'mp-custom-checkout' ) . '</p>'; } elseif ( 'female' === $gender ) { echo '<p><strong>' . esc_html__( 'Пол', 'mp-custom-checkout' ) . ':</strong> ' . esc_html__( 'Женщина', 'mp-custom-checkout' ) . '</p>'; }
		if ( '' !== $conditions ) { echo '<p><strong>' . esc_html__( 'Условия получения', 'mp-custom-checkout' ) . ':</strong></p><div class="mp-cc-order-conditions-meta__body">' . self::format_conditions_html( $conditions ) . '</div>'; }
		if ( ! empty( $discounts ) ) { echo '<p><strong>' . esc_html__( 'Скидки и сертификаты', 'mp-custom-checkout' ) . ':</strong></p><ul>'; foreach ( $discounts as $line ) { echo '<li>' . esc_html( $line ) . '</li>'; } echo '</ul>'; }
		echo '</div></div>';
	}

	public static function render_email_order_meta( $order, $sent_to_admin, $plain_text, $email = null ): void { if ( ! $order instanceof \WC_Order ) { return; } do_action( 'mp_custom_checkout_email_order_meta', $order, $sent_to_admin, $plain_text, $email ); }
	public static function render_scenario_meta( $order, $sent_to_admin, $plain_text, $email = null ): void { unset( $sent_to_admin, $email ); if ( ! $order instanceof \WC_Order ) { return; } $scenario_label = self::get_order_scenario_label( $order ); if ( '' === $scenario_label ) { return; } $title = __( 'Сценарий получения', 'mp-custom-checkout' ); if ( $plain_text ) { echo "\n" . sanitize_text_field( $title ) . ': ' . sanitize_text_field( $scenario_label ) . "\n"; return; } echo '<p><strong>' . esc_html( $title ) . ':</strong> ' . esc_html( $scenario_label ) . '</p>'; }
	public static function render_selected_date_meta( $order, $sent_to_admin, $plain_text, $email = null ): void { unset( $sent_to_admin, $email ); if ( ! $order instanceof \WC_Order ) { return; } $date_label = self::get_order_date_label( $order ); if ( '' === $date_label ) { return; } $title = __( 'Дата получения', 'mp-custom-checkout' ); if ( $plain_text ) { echo "\n" . sanitize_text_field( $title ) . ': ' . sanitize_text_field( $date_label ) . "\n"; return; } echo '<p><strong>' . esc_html( $title ) . ':</strong> ' . esc_html( $date_label ) . '</p>'; }
	public static function render_scenario_admin( $order ): void { if ( ! $order instanceof \WC_Order ) { return; } $scenario_label = self::get_order_scenario_label( $order ); if ( '' === $scenario_label ) { $scenario_label = ''; } $date_label = self::get_order_date_label( $order ); if ( '' !== $scenario_label ) { echo '<p><strong>' . esc_html__( 'Сценарий получения', 'mp-custom-checkout' ) . ':</strong> ' . esc_html( $scenario_label ) . '</p>'; } if ( '' !== $date_label ) { echo '<p><strong>' . esc_html__( 'Дата получения', 'mp-custom-checkout' ) . ':</strong> ' . esc_html( $date_label ) . '</p>'; } }
	public static function render_pickup_point_meta( $order, $sent_to_admin, $plain_text, $email = null ): void { unset( $sent_to_admin, $email ); if ( ! $order instanceof \WC_Order ) { return; } $title = (string) $order->get_meta( OrderMetaKeys::PICKUP_POINT_TITLE, true ); $address = (string) $order->get_meta( OrderMetaKeys::PICKUP_POINT_ADDRESS, true ); if ( '' === $title && '' === $address ) { return; } $label = __( 'Точка самовывоза', 'mp-custom-checkout' ); if ( $plain_text ) { echo "\n" . sanitize_text_field( $label ) . ': ' . sanitize_text_field( trim( $title . ( '' !== $address ? ' — ' . $address : '' ) ) ) . "\n"; return; } echo '<p><strong>' . esc_html( $label ) . ':</strong> ' . esc_html( $title ); if ( '' !== $address ) { echo '<br />' . esc_html( $address ); } echo '</p>'; }
	public static function render_conditions_summary_meta( $order, $sent_to_admin, $plain_text, $email = null ): void { unset( $email ); if ( ! $order instanceof \WC_Order ) { return; } $text = self::get_order_conditions_summary( $order ); if ( '' === $text ) { return; } $title = __( 'Условия получения', 'mp-custom-checkout' ); if ( $plain_text ) { $lead = $sent_to_admin ? '[' . __( 'Администратору', 'mp-custom-checkout' ) . '] ' : ''; echo "\n" . $lead . sanitize_text_field( $title ) . ":\n" . self::normalize_plain_block( $text ) . "\n"; return; } $wrap_class = 'mp-cc-email-conditions-summary'; if ( $sent_to_admin ) { $wrap_class .= ' mp-cc-email-conditions-summary--to-admin'; } echo '<div class="' . esc_attr( $wrap_class ) . '"><p><strong>' . esc_html( $title ) . '</strong></p><div class="mp-cc-email-conditions-summary__body">' . self::format_conditions_html( $text ) . '</div></div>'; }
	public static function render_conditions_summary_admin( $order ): void { if ( ! $order instanceof \WC_Order ) { return; } $text = self::get_order_conditions_summary( $order ); if ( '' === $text ) { return; } echo '<div class="mp-cc-order-conditions-meta"><p><strong>' . esc_html__( 'Условия получения', 'mp-custom-checkout' ) . '</strong></p><div class="mp-cc-order-conditions-meta__body">' . self::format_conditions_html( $text ) . '</div></div>'; }
	public static function render_pickup_point_admin( $order ): void { if ( ! $order instanceof \WC_Order ) { return; } $title = (string) $order->get_meta( OrderMetaKeys::PICKUP_POINT_TITLE, true ); $address = (string) $order->get_meta( OrderMetaKeys::PICKUP_POINT_ADDRESS, true ); $desc = (string) $order->get_meta( OrderMetaKeys::PICKUP_POINT_DESCRIPTION, true ); if ( '' === $title && '' === $address && '' === $desc ) { return; } echo '<p><strong>' . esc_html__( 'Точка самовывоза', 'mp-custom-checkout' ) . ':</strong><br />' . esc_html( $title ); if ( '' !== $address ) { echo '<br />' . esc_html( $address ); } if ( '' !== $desc ) { echo '<br /><em>' . esc_html( $desc ) . '</em>'; } echo '</p>'; }
	public static function render_contact_meta( $order, $sent_to_admin, $plain_text, $email = null ): void { unset( $sent_to_admin, $email ); if ( ! $order instanceof \WC_Order ) { return; } $lines = self::build_contact_lines_for_output( $order ); if ( empty( $lines ) ) { return; } $title = __( 'Контактные данные', 'mp-custom-checkout' ); if ( $plain_text ) { echo "\n" . sanitize_text_field( $title ) . ":\n"; foreach ( $lines as $line ) { echo '- ' . sanitize_text_field( $line ) . "\n"; } return; } echo '<div class="mp-cc-email-contact-meta"><p><strong>' . esc_html( $title ) . '</strong></p><ul>'; foreach ( $lines as $line ) { echo '<li>' . esc_html( $line ) . '</li>'; } echo '</ul></div>'; }
	public static function render_contact_admin( $order ): void { if ( ! $order instanceof \WC_Order ) { return; } $lines = self::build_contact_lines_for_output( $order ); $gender = trim( (string) $order->get_meta( OrderMetaKeys::GENDER, true ) ); if ( 'male' === $gender ) { $lines[] = __( 'Пол', 'mp-custom-checkout' ) . ': ' . __( 'Мужчина', 'mp-custom-checkout' ); } elseif ( 'female' === $gender ) { $lines[] = __( 'Пол', 'mp-custom-checkout' ) . ': ' . __( 'Женщина', 'mp-custom-checkout' ); } $order_note = trim( (string) $order->get_meta( OrderMetaKeys::ORDER_NOTES, true ) ); if ( empty( $lines ) && '' === $order_note ) { return; } echo '<div class="mp-cc-order-contact-meta"><p><strong>' . esc_html__( 'Контактные данные', 'mp-custom-checkout' ) . '</strong></p><ul>'; foreach ( $lines as $line ) { echo '<li>' . esc_html( $line ) . '</li>'; } echo '</ul></div>'; if ( '' !== $order_note ) { echo '<div class="mp-cc-order-contact-meta"><p><strong>' . esc_html__( 'Примечание к заказу', 'mp-custom-checkout' ) . '</strong></p><p>' . nl2br( esc_html( $order_note ), false ) . '</p></div>'; } }
	public static function render_discounts_meta( $order, $sent_to_admin, $plain_text, $email = null ): void { unset( $sent_to_admin, $email ); if ( ! $order instanceof \WC_Order ) { return; } $lines = self::build_discounts_lines_for_output( $order ); if ( empty( $lines ) ) { return; } $title = __( 'Скидки и сертификаты', 'mp-custom-checkout' ); if ( $plain_text ) { echo "\n" . sanitize_text_field( $title ) . ":\n"; foreach ( $lines as $line ) { echo '- ' . sanitize_text_field( $line ) . "\n"; } return; } echo '<div class="mp-cc-email-contact-meta"><p><strong>' . esc_html( $title ) . '</strong></p><ul>'; foreach ( $lines as $line ) { echo '<li>' . esc_html( $line ) . '</li>'; } echo '</ul></div>'; }
	public static function render_discounts_admin( $order ): void { if ( ! $order instanceof \WC_Order ) { return; } $lines = self::build_discounts_lines_for_output( $order ); if ( empty( $lines ) ) { return; } echo '<div class="mp-cc-order-contact-meta"><p><strong>' . esc_html__( 'Скидки и сертификаты', 'mp-custom-checkout' ) . '</strong></p><ul>'; foreach ( $lines as $line ) { echo '<li>' . esc_html( $line ) . '</li>'; } echo '</ul></div>'; }
	public static function add_scenario_order_list_column( array $columns ): array { $result = array(); foreach ( $columns as $key => $label ) { $result[ $key ] = $label; if ( 'order_status' === $key || 'order_total' === $key ) { $result['mp_cc_scenario'] = __( 'Сценарий', 'mp-custom-checkout' ); $result['mp_cc_selected_date'] = __( 'Дата получения', 'mp-custom-checkout' ); $result['mp_cc_conditions'] = __( 'Условия', 'mp-custom-checkout' ); $result['mp_cc_key_meta'] = __( 'Ключевые данные', 'mp-custom-checkout' ); } } if ( ! isset( $result['mp_cc_scenario'] ) ) { $result['mp_cc_scenario'] = __( 'Сценарий', 'mp-custom-checkout' ); } if ( ! isset( $result['mp_cc_selected_date'] ) ) { $result['mp_cc_selected_date'] = __( 'Дата получения', 'mp-custom-checkout' ); } if ( ! isset( $result['mp_cc_conditions'] ) ) { $result['mp_cc_conditions'] = __( 'Условия', 'mp-custom-checkout' ); } if ( ! isset( $result['mp_cc_key_meta'] ) ) { $result['mp_cc_key_meta'] = __( 'Ключевые данные', 'mp-custom-checkout' ); } return $result; }
	public static function render_scenario_order_list_column( string $column, int $post_id ): void { if ( ! in_array( $column, array( 'mp_cc_scenario', 'mp_cc_selected_date', 'mp_cc_conditions', 'mp_cc_key_meta' ), true ) ) { return; } $order = wc_get_order( $post_id ); if ( ! $order instanceof \WC_Order ) { echo '&mdash;'; return; } if ( 'mp_cc_conditions' === $column ) { $full = self::get_order_conditions_summary_for_list_tooltip( $order ); $short = self::get_order_conditions_summary_short( $order ); if ( '' === $short ) { echo '&mdash;'; return; } echo '<span class="mp-cc-order-list-conditions" title="' . esc_attr( $full ) . '">' . esc_html( $short ) . '</span>'; return; } if ( 'mp_cc_key_meta' === $column ) { $compact = self::build_key_meta_list_preview( $order ); echo '' !== $compact ? esc_html( $compact ) : '&mdash;'; return; } $label = 'mp_cc_scenario' === $column ? self::get_order_scenario_label( $order ) : self::get_order_date_label( $order ); echo '' !== $label ? esc_html( $label ) : '&mdash;'; }
	public static function render_scenario_order_list_column_hpos( string $column, $order_or_id ): void { if ( ! in_array( $column, array( 'mp_cc_scenario', 'mp_cc_selected_date', 'mp_cc_conditions', 'mp_cc_key_meta' ), true ) ) { return; } $order = $order_or_id instanceof \WC_Order ? $order_or_id : wc_get_order( (int) $order_or_id ); if ( ! $order instanceof \WC_Order ) { echo '&mdash;'; return; } if ( 'mp_cc_conditions' === $column ) { $full = self::get_order_conditions_summary_for_list_tooltip( $order ); $short = self::get_order_conditions_summary_short( $order ); if ( '' === $short ) { echo '&mdash;'; return; } echo '<span class="mp-cc-order-list-conditions" title="' . esc_attr( $full ) . '">' . esc_html( $short ) . '</span>'; return; } if ( 'mp_cc_key_meta' === $column ) { $compact = self::build_key_meta_list_preview( $order ); echo '' !== $compact ? esc_html( $compact ) : '&mdash;'; return; } $label = 'mp_cc_scenario' === $column ? self::get_order_scenario_label( $order ) : self::get_order_date_label( $order ); echo '' !== $label ? esc_html( $label ) : '&mdash;'; }
	private static function get_order_scenario_label( \WC_Order $order ): string { $scenario_id = (string) $order->get_meta( OrderMetaKeys::SCENARIO_ID, true ); $scenario_label = (string) $order->get_meta( OrderMetaKeys::SCENARIO_LABEL, true ); $payload_raw = (string) $order->get_meta( OrderMetaKeys::SCENARIO_PAYLOAD, true ); $payload_label = ''; if ( '' !== $payload_raw ) { $decoded = json_decode( $payload_raw, true ); if ( is_array( $decoded ) && isset( $decoded['label'] ) ) { $payload_label = (string) $decoded['label']; } } $has_any_value = '' !== trim( $scenario_id ) || '' !== trim( $scenario_label ) || '' !== trim( $payload_label ); if ( ! $has_any_value ) { return ''; } $candidate = '' !== trim( $scenario_label ) ? $scenario_label : $payload_label; return CheckoutScenarioRules::normalize_label_for_output( $candidate, $scenario_id ); }
	private static function get_order_date_label( \WC_Order $order ): string { $date_label = (string) $order->get_meta( OrderMetaKeys::SELECTED_DATE_LABEL, true ); $date_iso = (string) $order->get_meta( OrderMetaKeys::SELECTED_DATE, true ); if ( '' !== trim( $date_label ) ) { return sanitize_text_field( $date_label ); } if ( '' === trim( $date_iso ) ) { return ''; } $dt = \DateTimeImmutable::createFromFormat( 'Y-m-d', $date_iso, wp_timezone() ); if ( $dt instanceof \DateTimeImmutable ) { return $dt->format( 'd.m.Y' ); } return sanitize_text_field( $date_iso ); }
	private static function get_order_conditions_summary( \WC_Order $order ): string { return trim( CheckoutConditionsSummaryBuilder::get_summary_for_order( $order ) ); }
	private static function get_order_conditions_summary_short( \WC_Order $order ): string { return self::truncate_conditions_one_line( self::get_order_conditions_summary( $order ) ); }
	private static function truncate_conditions_one_line( string $text ): string { if ( '' === $text ) { return ''; } $one_line = trim( preg_replace( '/\s+/', ' ', $text ) ?? '' ); $max = 140; if ( function_exists( 'mb_strlen' ) && function_exists( 'mb_substr' ) ) { if ( mb_strlen( $one_line ) > $max ) { return mb_substr( $one_line, 0, $max - 1 ) . '…'; } return $one_line; } if ( strlen( $one_line ) > $max ) { return substr( $one_line, 0, $max - 1 ) . '…'; } return $one_line; }
	private static function get_order_conditions_summary_for_list_tooltip( \WC_Order $order ): string { return self::get_order_conditions_summary( $order ); }
	private static function normalize_plain_block( string $text ): string { return trim( str_replace( array( "\r\n", "\r" ), "\n", $text ) ); }
	private static function format_conditions_html( string $text ): string { $text = self::normalize_plain_block( $text ); $paras = explode( "\n\n", $text ); $out = ''; foreach ( $paras as $para ) { $para = trim( $para ); if ( '' === $para ) { continue; } $out .= '<p class="mp-cc-conditions-para">' . nl2br( esc_html( $para ), false ) . '</p>'; } return $out ? $out : '<p class="mp-cc-conditions-para">' . esc_html( $text ) . '</p>'; }
	private static function build_contact_lines_for_output( \WC_Order $order ): array { $full_name = trim( $order->get_billing_last_name() . ' ' . $order->get_billing_first_name() ); $patronymic = trim( (string) $order->get_meta( OrderMetaKeys::BILLING_PATRONYMIC, true ) ); if ( '' !== $patronymic ) { $full_name = trim( $full_name . ' ' . $patronymic ); } $email = trim( (string) $order->get_billing_email() ); $phone = trim( (string) $order->get_billing_phone() ); $birthdate_raw = trim( (string) $order->get_meta( OrderMetaKeys::BILLING_BIRTHDATE, true ) ); $birthdate = $birthdate_raw; $birth_dt = \DateTimeImmutable::createFromFormat( 'Y-m-d', $birthdate_raw, wp_timezone() ); if ( $birth_dt instanceof \DateTimeImmutable ) { $birthdate = $birth_dt->format( 'd.m.Y' ); } $address = self::build_structured_address_single_line( $order ); $lines = array(); if ( '' !== $full_name ) { $lines[] = __( 'Получатель', 'mp-custom-checkout' ) . ': ' . $full_name; } if ( '' !== $email ) { $lines[] = __( 'Email', 'mp-custom-checkout' ) . ': ' . $email; } if ( '' !== $phone ) { $lines[] = __( 'Телефон', 'mp-custom-checkout' ) . ': ' . $phone; } if ( '' !== $birthdate ) { $lines[] = __( 'Дата рождения', 'mp-custom-checkout' ) . ': ' . $birthdate; } if ( '' !== $address ) { $lines[] = __( 'Адрес', 'mp-custom-checkout' ) . ': ' . $address; } return $lines; }
	private static function build_discounts_lines_for_output( \WC_Order $order ): array { $lines = array(); $coupons_raw = (string) $order->get_meta( OrderMetaKeys::APPLIED_COUPONS, true ); $gift_raw = (string) $order->get_meta( OrderMetaKeys::APPLIED_GIFT_CARDS, true ); $coupon_total = (float) $order->get_meta( OrderMetaKeys::COUPON_DISCOUNT_TOTAL, true ); $gift_total = (float) $order->get_meta( OrderMetaKeys::GIFT_CARD_TOTAL, true ); $coupons = json_decode( $coupons_raw, true ); $gift = json_decode( $gift_raw, true ); $coupons = is_array( $coupons ) ? array_values( array_filter( array_map( 'strval', $coupons ) ) ) : array(); $gift = is_array( $gift ) ? array_values( array_filter( array_map( 'strval', $gift ) ) ) : array(); if ( ! empty( $coupons ) ) { $lines[] = __( 'Купоны', 'mp-custom-checkout' ) . ': ' . implode( ', ', $coupons ) . ' (' . wp_strip_all_tags( wc_price( $coupon_total ) ) . ')'; } if ( ! empty( $gift ) ) { $lines[] = __( 'Подарочная карта', 'mp-custom-checkout' ) . ': ' . implode( ', ', $gift ) . ' (' . wp_strip_all_tags( wc_price( $gift_total ) ) . ')'; } return $lines; }
	private static function build_structured_address_single_line( \WC_Order $order ): string { $parts = array_filter( array( trim( (string) $order->get_meta( OrderMetaKeys::ADDRESS_COUNTRY_CODE, true ) ), trim( (string) $order->get_meta( OrderMetaKeys::ADDRESS_REGION_CODE, true ) ), trim( (string) $order->get_meta( OrderMetaKeys::ADDRESS_CITY, true ) ), trim( (string) $order->get_meta( OrderMetaKeys::ADDRESS_LINE1, true ) ), trim( (string) $order->get_meta( OrderMetaKeys::ADDRESS_LINE2, true ) ), trim( (string) $order->get_meta( OrderMetaKeys::ADDRESS_POSTCODE, true ) ) ), static function ( $value ) { return '' !== $value; } ); if ( empty( $parts ) ) { $parts = array_filter( array( trim( (string) $order->get_billing_country() ), trim( (string) $order->get_billing_state() ), trim( (string) $order->get_billing_city() ), trim( (string) $order->get_billing_address_1() ), trim( (string) $order->get_billing_address_2() ), trim( (string) $order->get_billing_postcode() ) ), static function ( $value ) { return '' !== $value; } ); } return implode( ', ', $parts ); }
	private static function build_key_meta_list_preview( \WC_Order $order ): string { $pieces = array(); $scenario = self::get_order_scenario_label( $order ); $date = self::get_order_date_label( $order ); $pickup = trim( (string) $order->get_meta( OrderMetaKeys::PICKUP_POINT_TITLE, true ) ); if ( '' !== $scenario ) { $pieces[] = $scenario; } if ( '' !== $date ) { $pieces[] = $date; } if ( '' !== $pickup ) { $pieces[] = __( 'ПВЗ', 'mp-custom-checkout' ) . ': ' . $pickup; } $birth = trim( (string) $order->get_meta( OrderMetaKeys::BILLING_BIRTHDATE, true ) ); if ( '' !== $birth ) { $pieces[] = __( 'ДР', 'mp-custom-checkout' ) . ': ' . $birth; } return implode( ' | ', $pieces ); }
}
