<?php
/**
 * Вывод данных checkout в письмах WooCommerce.
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout\Hooks;

use MP\CustomCheckout\DependencyFailureGuard;
use MP\CustomCheckout\Routing\CheckoutConditionsSummaryBuilder;
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
		add_action( 'mp_custom_checkout_email_order_meta', array( __CLASS__, 'render_selected_date_meta' ), 11, 4 );
		add_action( 'mp_custom_checkout_email_order_meta', array( __CLASS__, 'render_pickup_point_meta' ), 12, 4 );
		add_action( 'mp_custom_checkout_email_order_meta', array( __CLASS__, 'render_conditions_summary_meta' ), 13, 4 );
		add_action( 'mp_custom_checkout_email_order_meta', array( __CLASS__, 'render_contact_meta' ), 14, 4 );
		add_action( 'woocommerce_admin_order_data_after_billing_address', array( __CLASS__, 'render_scenario_admin' ), 12, 1 );
		add_action( 'woocommerce_admin_order_data_after_billing_address', array( __CLASS__, 'render_pickup_point_admin' ), 15, 1 );
		add_action( 'woocommerce_admin_order_data_after_billing_address', array( __CLASS__, 'render_conditions_summary_admin' ), 18, 1 );
		add_action( 'woocommerce_admin_order_data_after_billing_address', array( __CLASS__, 'render_contact_admin' ), 20, 1 );
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
	 * Вывод выбранной даты в email заказа.
	 *
	 * @param \WC_Order $order Заказ.
	 * @param bool      $sent_to_admin Админу.
	 * @param bool      $plain_text Текстовый формат.
	 * @param \WC_Email|false $email Письмо.
	 */
	public static function render_selected_date_meta( $order, $sent_to_admin, $plain_text, $email = null ): void {
		unset( $sent_to_admin, $email );
		if ( ! $order instanceof \WC_Order ) {
			return;
		}
		$date_label = self::get_order_date_label( $order );
		if ( '' === $date_label ) {
			return;
		}
		$title = __( 'Дата получения', 'mp-custom-checkout' );
		if ( $plain_text ) {
			echo "\n" . sanitize_text_field( $title ) . ': ' . sanitize_text_field( $date_label ) . "\n";
			return;
		}
		echo '<p><strong>' . esc_html( $title ) . ':</strong> ' . esc_html( $date_label ) . '</p>';
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
			$scenario_label = '';
		}
		$date_label = self::get_order_date_label( $order );
		if ( '' !== $scenario_label ) {
			echo '<p><strong>' . esc_html__( 'Сценарий получения', 'mp-custom-checkout' ) . ':</strong> ' . esc_html( $scenario_label ) . '</p>';
		}
		if ( '' !== $date_label ) {
			echo '<p><strong>' . esc_html__( 'Дата получения', 'mp-custom-checkout' ) . ':</strong> ' . esc_html( $date_label ) . '</p>';
		}
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
	 * Вывод сохранённого текста условий получения в email (клиент и админ).
	 *
	 * @param \WC_Order       $order Заказ.
	 * @param bool            $sent_to_admin Админу.
	 * @param bool            $plain_text Текстовый формат.
	 * @param \WC_Email|false $email Письмо.
	 */
	public static function render_conditions_summary_meta( $order, $sent_to_admin, $plain_text, $email = null ): void {
		unset( $email );
		if ( ! $order instanceof \WC_Order ) {
			return;
		}

		$text = self::get_order_conditions_summary( $order );
		if ( '' === $text ) {
			return;
		}

		$title = __( 'Условия получения', 'mp-custom-checkout' );
		if ( $plain_text ) {
			$lead = $sent_to_admin
				? '[' . __( 'Администратору', 'mp-custom-checkout' ) . '] '
				: '';
			echo "\n" . $lead . sanitize_text_field( $title ) . ":\n" . self::normalize_plain_block( $text ) . "\n";
			return;
		}

		$wrap_class = 'mp-cc-email-conditions-summary';
		if ( $sent_to_admin ) {
			$wrap_class .= ' mp-cc-email-conditions-summary--to-admin';
		}

		echo '<div class="' . esc_attr( $wrap_class ) . '">';
		echo '<p><strong>' . esc_html( $title ) . '</strong></p>';
		echo '<div class="mp-cc-email-conditions-summary__body">' . self::format_conditions_html( $text ) . '</div>';
		echo '</div>';
	}

	/**
	 * Карточка заказа: полный текст условий (meta или восстановление из сценария/точки/даты).
	 *
	 * @param \WC_Order $order Заказ.
	 */
	public static function render_conditions_summary_admin( $order ): void {
		if ( ! $order instanceof \WC_Order ) {
			return;
		}
		$text = self::get_order_conditions_summary( $order );
		if ( '' === $text ) {
			return;
		}

		echo '<div class="mp-cc-order-conditions-meta"><p><strong>' . esc_html__( 'Условия получения', 'mp-custom-checkout' ) . '</strong></p>';
		echo '<div class="mp-cc-order-conditions-meta__body">' . self::format_conditions_html( $text ) . '</div></div>';
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
	 * Контактные данные в email (админ и клиент).
	 *
	 * @param \WC_Order       $order Заказ.
	 * @param bool            $sent_to_admin Админу.
	 * @param bool            $plain_text Текстовый формат.
	 * @param \WC_Email|false $email Письмо.
	 */
	public static function render_contact_meta( $order, $sent_to_admin, $plain_text, $email = null ): void {
		unset( $sent_to_admin, $email );
		if ( ! $order instanceof \WC_Order ) {
			return;
		}

		$lines = self::build_contact_lines_for_output( $order );
		if ( empty( $lines ) ) {
			return;
		}

		$title = __( 'Контактные данные', 'mp-custom-checkout' );
		if ( $plain_text ) {
			echo "\n" . sanitize_text_field( $title ) . ":\n";
			foreach ( $lines as $line ) {
				echo '- ' . sanitize_text_field( $line ) . "\n";
			}
			return;
		}

		echo '<div class="mp-cc-email-contact-meta"><p><strong>' . esc_html( $title ) . '</strong></p><ul>';
		foreach ( $lines as $line ) {
			echo '<li>' . esc_html( $line ) . '</li>';
		}
		echo '</ul></div>';
	}

	/**
	 * Контактные данные в админке заказа.
	 *
	 * @param \WC_Order $order Заказ.
	 */
	public static function render_contact_admin( $order ): void {
		if ( ! $order instanceof \WC_Order ) {
			return;
		}
		$lines = self::build_contact_lines_for_output( $order );
		$gender = trim( (string) $order->get_meta( '_mp_cc_gender', true ) );
		if ( 'male' === $gender ) {
			$lines[] = __( 'Пол', 'mp-custom-checkout' ) . ': ' . __( 'Мужчина', 'mp-custom-checkout' );
		} elseif ( 'female' === $gender ) {
			$lines[] = __( 'Пол', 'mp-custom-checkout' ) . ': ' . __( 'Женщина', 'mp-custom-checkout' );
		}
		$order_note = trim( (string) $order->get_meta( '_mp_cc_order_notes', true ) );
		if ( empty( $lines ) ) {
			if ( '' === $order_note ) {
				return;
			}
		}

		echo '<div class="mp-cc-order-contact-meta"><p><strong>' . esc_html__( 'Контактные данные', 'mp-custom-checkout' ) . '</strong></p><ul>';
		foreach ( $lines as $line ) {
			echo '<li>' . esc_html( $line ) . '</li>';
		}
		echo '</ul></div>';
		if ( '' !== $order_note ) {
			echo '<div class="mp-cc-order-contact-meta"><p><strong>' . esc_html__( 'Примечание к заказу', 'mp-custom-checkout' ) . '</strong></p><p>' . nl2br( esc_html( $order_note ), false ) . '</p></div>';
		}
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
				$result['mp_cc_selected_date'] = __( 'Дата получения', 'mp-custom-checkout' );
				$result['mp_cc_conditions'] = __( 'Условия', 'mp-custom-checkout' );
			}
		}
		if ( ! isset( $result['mp_cc_scenario'] ) ) {
			$result['mp_cc_scenario'] = __( 'Сценарий', 'mp-custom-checkout' );
		}
		if ( ! isset( $result['mp_cc_selected_date'] ) ) {
			$result['mp_cc_selected_date'] = __( 'Дата получения', 'mp-custom-checkout' );
		}
		if ( ! isset( $result['mp_cc_conditions'] ) ) {
			$result['mp_cc_conditions'] = __( 'Условия', 'mp-custom-checkout' );
		}
		return $result;
	}

	/**
	 * Рендер сценария в колонке списка заказов (legacy table).
	 */
	public static function render_scenario_order_list_column( string $column, int $post_id ): void {
		if ( ! in_array( $column, array( 'mp_cc_scenario', 'mp_cc_selected_date', 'mp_cc_conditions' ), true ) ) {
			return;
		}
		$order = wc_get_order( $post_id );
		if ( ! $order instanceof \WC_Order ) {
			echo '&mdash;';
			return;
		}
		if ( 'mp_cc_conditions' === $column ) {
			$full  = self::get_order_conditions_summary_for_list_tooltip( $order );
			$short = self::get_order_conditions_summary_short( $order );
			if ( '' === $short ) {
				echo '&mdash;';
				return;
			}
			echo '<span class="mp-cc-order-list-conditions" title="' . esc_attr( $full ) . '">' . esc_html( $short ) . '</span>';
			return;
		}
		$label = 'mp_cc_scenario' === $column ? self::get_order_scenario_label( $order ) : self::get_order_date_label( $order );
		echo '' !== $label ? esc_html( $label ) : '&mdash;';
	}

	/**
	 * Рендер сценария в колонке списка заказов (HPOS table).
	 *
	 * @param string             $column Колонка.
	 * @param int|\WC_Order|null $order_or_id Заказ или ID.
	 */
	public static function render_scenario_order_list_column_hpos( string $column, $order_or_id ): void {
		if ( ! in_array( $column, array( 'mp_cc_scenario', 'mp_cc_selected_date', 'mp_cc_conditions' ), true ) ) {
			return;
		}
		$order = $order_or_id instanceof \WC_Order ? $order_or_id : wc_get_order( (int) $order_or_id );
		if ( ! $order instanceof \WC_Order ) {
			echo '&mdash;';
			return;
		}
		if ( 'mp_cc_conditions' === $column ) {
			$full  = self::get_order_conditions_summary_for_list_tooltip( $order );
			$short = self::get_order_conditions_summary_short( $order );
			if ( '' === $short ) {
				echo '&mdash;';
				return;
			}
			echo '<span class="mp-cc-order-list-conditions" title="' . esc_attr( $full ) . '">' . esc_html( $short ) . '</span>';
			return;
		}
		$label = 'mp_cc_scenario' === $column ? self::get_order_scenario_label( $order ) : self::get_order_date_label( $order );
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

	private static function get_order_date_label( \WC_Order $order ): string {
		$date_label = (string) $order->get_meta( '_mp_cc_selected_date_label', true );
		$date_iso   = (string) $order->get_meta( '_mp_cc_selected_date', true );
		if ( '' !== trim( $date_label ) ) {
			return sanitize_text_field( $date_label );
		}
		if ( '' === trim( $date_iso ) ) {
			return '';
		}
		$dt = \DateTimeImmutable::createFromFormat( 'Y-m-d', $date_iso, wp_timezone() );
		if ( $dt instanceof \DateTimeImmutable ) {
			return $dt->format( 'd.m.Y' );
		}
		return sanitize_text_field( $date_iso );
	}

	private static function get_order_conditions_summary( \WC_Order $order ): string {
		return trim( CheckoutConditionsSummaryBuilder::get_summary_for_order( $order ) );
	}

	private static function get_order_conditions_summary_short( \WC_Order $order ): string {
		$text = self::get_order_conditions_summary( $order );
		return self::truncate_conditions_one_line( $text );
	}

	/**
	 * @return string Краткая строка для списка заказов (обрезка по длине).
	 */
	private static function truncate_conditions_one_line( string $text ): string {
		if ( '' === $text ) {
			return '';
		}
		$one_line = trim( preg_replace( '/\s+/', ' ', $text ) ?? '' );
		$max      = 140;
		if ( function_exists( 'mb_strlen' ) && function_exists( 'mb_substr' ) ) {
			if ( mb_strlen( $one_line ) > $max ) {
				return mb_substr( $one_line, 0, $max - 1 ) . '…';
			}
			return $one_line;
		}
		if ( strlen( $one_line ) > $max ) {
			return substr( $one_line, 0, $max - 1 ) . '…';
		}
		return $one_line;
	}

	/**
	 * Полный текст для подсказки title в списке заказов.
	 */
	private static function get_order_conditions_summary_for_list_tooltip( \WC_Order $order ): string {
		return self::get_order_conditions_summary( $order );
	}

	private static function normalize_plain_block( string $text ): string {
		$text = str_replace( array( "\r\n", "\r" ), "\n", $text );
		return trim( $text );
	}

	private static function format_conditions_html( string $text ): string {
		$text = self::normalize_plain_block( $text );
		$paras  = explode( "\n\n", $text );
		$out    = '';
		foreach ( $paras as $para ) {
			$para = trim( $para );
			if ( '' === $para ) {
				continue;
			}
			$inner = nl2br( esc_html( $para ), false );
			$out .= '<p class="mp-cc-conditions-para">' . $inner . '</p>';
		}
		return $out ? $out : '<p class="mp-cc-conditions-para">' . esc_html( $text ) . '</p>';
	}

	/**
	 * @return array<int, string>
	 */
	private static function build_contact_lines_for_output( \WC_Order $order ): array {
		$full_name = trim( $order->get_billing_last_name() . ' ' . $order->get_billing_first_name() );
		$patronymic = trim( (string) $order->get_meta( '_mp_cc_billing_patronymic', true ) );
		if ( '' !== $patronymic ) {
			$full_name = trim( $full_name . ' ' . $patronymic );
		}

		$email = trim( (string) $order->get_billing_email() );
		$phone = trim( (string) $order->get_billing_phone() );
		$birthdate_raw = trim( (string) $order->get_meta( '_mp_cc_billing_birthdate', true ) );
		$birthdate = $birthdate_raw;
		$birth_dt = \DateTimeImmutable::createFromFormat( 'Y-m-d', $birthdate_raw, wp_timezone() );
		if ( $birth_dt instanceof \DateTimeImmutable ) {
			$birthdate = $birth_dt->format( 'd.m.Y' );
		}

		$address_parts = array_filter(
			array(
				trim( (string) $order->get_billing_country() ),
				trim( (string) $order->get_billing_state() ),
				trim( (string) $order->get_billing_city() ),
				trim( (string) $order->get_billing_address_1() ),
				trim( (string) $order->get_billing_address_2() ),
				trim( (string) $order->get_billing_postcode() ),
			),
			static function ( $v ) {
				return '' !== $v;
			}
		);
		$address = implode( ', ', $address_parts );

		$lines = array();
		if ( '' !== $full_name ) {
			$lines[] = __( 'Получатель', 'mp-custom-checkout' ) . ': ' . $full_name;
		}
		if ( '' !== $email ) {
			$lines[] = __( 'Email', 'mp-custom-checkout' ) . ': ' . $email;
		}
		if ( '' !== $phone ) {
			$lines[] = __( 'Телефон', 'mp-custom-checkout' ) . ': ' . $phone;
		}
		if ( '' !== $birthdate ) {
			$lines[] = __( 'Дата рождения', 'mp-custom-checkout' ) . ': ' . $birthdate;
		}
		if ( '' !== $address ) {
			$lines[] = __( 'Адрес', 'mp-custom-checkout' ) . ': ' . $address;
		}
		return $lines;
	}
}
