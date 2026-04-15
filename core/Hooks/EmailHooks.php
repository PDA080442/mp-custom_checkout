<?php
/**
 * Вывод данных checkout в письмах WooCommerce.
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout\Hooks;

use MP\CustomCheckout\DependencyFailureGuard;
use MP\CustomCheckout\Routing\CheckoutScenarioRules;

defined( 'ABSPATH' ) || exit;

/**
 * Class EmailHooks
 */
final class EmailHooks {

	/**
	 * Регистрация хуков шаблонов email.
	 */
	public static function register(): void {
		if ( ! DependencyFailureGuard::is_woocommerce_integration_ready() ) {
			return;
		}

		add_action( 'woocommerce_email_order_meta', array( __CLASS__, 'render_email_order_meta' ), 10, 4 );
		add_action( 'mp_custom_checkout_email_order_meta', array( __CLASS__, 'render_scenario_meta' ), 10, 4 );
		add_action( 'mp_custom_checkout_email_order_meta', array( __CLASS__, 'render_pickup_point_meta' ), 12, 4 );
		add_action( 'woocommerce_admin_order_data_after_billing_address', array( __CLASS__, 'render_scenario_admin' ), 12, 1 );
		add_action( 'woocommerce_admin_order_data_after_billing_address', array( __CLASS__, 'render_pickup_point_admin' ), 15, 1 );
		add_filter( 'manage_edit-shop_order_columns', array( __CLASS__, 'add_scenario_order_list_column' ), 25 );
		add_action( 'manage_shop_order_posts_custom_column', array( __CLASS__, 'render_scenario_order_list_column' ), 25, 2 );
		add_filter( 'manage_woocommerce_page_wc-orders_columns', array( __CLASS__, 'add_scenario_order_list_column' ), 25 );
		add_action( 'manage_woocommerce_page_wc-orders_custom_column', array( __CLASS__, 'render_scenario_order_list_column_hpos' ), 25, 2 );
	}

	/**
	 * @param \WC_Order|false $order           Заказ.
	 * @param bool              $sent_to_admin Админу.
	 * @param bool              $plain_text      Текстовое письмо.
	 * @param \WC_Email|false   $email           Объект письма.
	 */
	public static function render_email_order_meta( $order, $sent_to_admin, $plain_text, $email = null ): void {
		if ( ! $order instanceof \WC_Order ) {
			return;
		}

		/**
		 * Вывод дополнительных полей checkout в письме.
		 *
		 * @param \WC_Order  $order           Заказ.
		 * @param bool       $sent_to_admin Админу.
		 * @param bool       $plain_text      Текстовое письмо.
		 * @param \WC_Email  $email           Письмо.
		 */
		do_action( 'mp_custom_checkout_email_order_meta', $order, $sent_to_admin, $plain_text, $email );
	}

	/**
	 * Вывод выбранного сценария в email заказа.
	 *
	 * @param \WC_Order $order Заказ.
	 * @param bool      $sent_to_admin Админу.
	 * @param bool      $plain_text Текстовый формат.
	 * @param \WC_Email|false $email Письмо.
	 */
	public static function render_scenario_meta( $order, $sent_to_admin, $plain_text, $email = null ): void {
		unset( $sent_to_admin, $email );
		if ( ! $order instanceof \WC_Order ) {
			return;
		}

		$scenario_label = self::get_order_scenario_label( $order );

		if ( '' === $scenario_label ) {
			return;
		}

		$title = __( 'Сценарий получения', 'mp-custom-checkout' );

		if ( $plain_text ) {
			echo "\n" . sanitize_text_field( $title ) . ': ' . sanitize_text_field( $scenario_label ) . "\n";
			return;
		}

		echo '<p><strong>' . esc_html( $title ) . ':</strong> ' . esc_html( $scenario_label ) . '</p>';
	}

	/**
	 * Вывод сценария в карточке заказа WooCommerce (админка).
	 *
	 * @param \WC_Order $order Заказ.
	 */
	public static function render_scenario_admin( $order ): void {
		if ( ! $order instanceof \WC_Order ) {
			return;
		}
		$scenario_label = self::get_order_scenario_label( $order );
		if ( '' === $scenario_label ) {
			return;
		}
		echo '<p><strong>' . esc_html__( 'Сценарий получения', 'mp-custom-checkout' ) . ':</strong> ' . esc_html( $scenario_label ) . '</p>';
	}

	/**
	 * Вывод точки самовывоза в email.
	 *
	 * @param \WC_Order $order Заказ.
	 * @param bool      $sent_to_admin Админу.
	 * @param bool      $plain_text Текстовый формат.
	 * @param \WC_Email|false $email Письмо.
	 */
	public static function render_pickup_point_meta( $order, $sent_to_admin, $plain_text, $email = null ): void {
		unset( $sent_to_admin, $email );
		if ( ! $order instanceof \WC_Order ) {
			return;
		}

		$title   = (string) $order->get_meta( '_mp_cc_pickup_point_title', true );
		$address = (string) $order->get_meta( '_mp_cc_pickup_point_address', true );
		if ( '' === $title && '' === $address ) {
			return;
		}

		$label = __( 'Точка самовывоза', 'mp-custom-checkout' );
		if ( $plain_text ) {
			echo "\n" . sanitize_text_field( $label ) . ': ' . sanitize_text_field( trim( $title . ( '' !== $address ? ' — ' . $address : '' ) ) ) . "\n";
			return;
		}

		echo '<p><strong>' . esc_html( $label ) . ':</strong> ' . esc_html( $title );
		if ( '' !== $address ) {
			echo '<br />' . esc_html( $address );
		}
		echo '</p>';
	}

	/**
	 * Вывод точки самовывоза в админке заказа.
	 *
	 * @param \WC_Order $order Заказ.
	 */
	public static function render_pickup_point_admin( $order ): void {
		if ( ! $order instanceof \WC_Order ) {
			return;
		}
		$title   = (string) $order->get_meta( '_mp_cc_pickup_point_title', true );
		$address = (string) $order->get_meta( '_mp_cc_pickup_point_address', true );
		$desc    = (string) $order->get_meta( '_mp_cc_pickup_point_description', true );
		if ( '' === $title && '' === $address && '' === $desc ) {
			return;
		}

		echo '<p><strong>' . esc_html__( 'Точка самовывоза', 'mp-custom-checkout' ) . ':</strong><br />';
		echo esc_html( $title );
		if ( '' !== $address ) {
			echo '<br />' . esc_html( $address );
		}
		if ( '' !== $desc ) {
			echo '<br /><em>' . esc_html( $desc ) . '</em>';
		}
		echo '</p>';
	}

	/**
	 * Добавляет колонку сценария в список заказов (legacy + HPOS).
	 *
	 * @param array<string, string> $columns Колонки.
	 * @return array<string, string>
	 */
	public static function add_scenario_order_list_column( array $columns ): array {
		$result = array();
		foreach ( $columns as $key => $label ) {
			$result[ $key ] = $label;
			if ( 'order_status' === $key || 'order_total' === $key ) {
				$result['mp_cc_scenario'] = __( 'Сценарий', 'mp-custom-checkout' );
			}
		}
		if ( ! isset( $result['mp_cc_scenario'] ) ) {
			$result['mp_cc_scenario'] = __( 'Сценарий', 'mp-custom-checkout' );
		}
		return $result;
	}

	/**
	 * Рендер сценария в колонке списка заказов (legacy table).
	 */
	public static function render_scenario_order_list_column( string $column, int $post_id ): void {
		if ( 'mp_cc_scenario' !== $column ) {
			return;
		}
		$order = wc_get_order( $post_id );
		if ( ! $order instanceof \WC_Order ) {
			echo '&mdash;';
			return;
		}
		$label = self::get_order_scenario_label( $order );
		echo '' !== $label ? esc_html( $label ) : '&mdash;';
	}

	/**
	 * Рендер сценария в колонке списка заказов (HPOS table).
	 *
	 * @param string             $column Колонка.
	 * @param int|\WC_Order|null $order_or_id Заказ или ID.
	 */
	public static function render_scenario_order_list_column_hpos( string $column, $order_or_id ): void {
		if ( 'mp_cc_scenario' !== $column ) {
			return;
		}
		$order = $order_or_id instanceof \WC_Order ? $order_or_id : wc_get_order( (int) $order_or_id );
		if ( ! $order instanceof \WC_Order ) {
			echo '&mdash;';
			return;
		}
		$label = self::get_order_scenario_label( $order );
		echo '' !== $label ? esc_html( $label ) : '&mdash;';
	}

	private static function get_order_scenario_label( \WC_Order $order ): string {
		$scenario_id    = (string) $order->get_meta( '_mp_cc_scenario_id', true );
		$scenario_label = (string) $order->get_meta( '_mp_cc_scenario_label', true );
		$payload_raw    = (string) $order->get_meta( '_mp_cc_scenario_payload', true );
		$payload_label  = '';
		if ( '' !== $payload_raw ) {
			$decoded = json_decode( $payload_raw, true );
			if ( is_array( $decoded ) && isset( $decoded['label'] ) ) {
				$payload_label = (string) $decoded['label'];
			}
		}

		$has_any_value = '' !== trim( $scenario_id ) || '' !== trim( $scenario_label ) || '' !== trim( $payload_label );
		if ( ! $has_any_value ) {
			return '';
		}

		$candidate = '' !== trim( $scenario_label ) ? $scenario_label : $payload_label;
		return CheckoutScenarioRules::normalize_label_for_output( $candidate, $scenario_id );
	}
}
