<?php
/**
 * Единый текст «условий получения» для заказа, писем и админки (консистентность каналов).
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout\Routing;

use MP\CustomCheckout\Settings\SafeSettingsResolver;
use MP\CustomCheckout\Settings\ScenarioStepRegistry;

defined( 'ABSPATH' ) || exit;

/**
 * Class CheckoutConditionsSummaryBuilder
 */
final class CheckoutConditionsSummaryBuilder {

	public const ORDER_META_KEY = '_mp_cc_conditions_summary';

	/**
	 * Собирает текст по сессии checkout (перед сохранением заказа).
	 *
	 * @param array<string, mixed> $flow Полный flow из {@see CheckoutSessionService::get_flow()}.
	 */
	public static function build_for_flow( string $scenario, array $flow ): string {
		$scenario = CheckoutScenarioRules::sanitize_scenario( $scenario );
		$answers  = isset( $flow['answers'] ) && is_array( $flow['answers'] ) ? $flow['answers'] : array();

		$step3 = SafeSettingsResolver::get_section( 'step_3' );
		$cc    = isset( $step3['conditions_copy'] ) && is_array( $step3['conditions_copy'] ) ? $step3['conditions_copy'] : array();

		$lines = array();

		$intro_map = isset( $cc['intro_by_scenario'] ) && is_array( $cc['intro_by_scenario'] ) ? $cc['intro_by_scenario'] : array();
		$intro     = isset( $intro_map[ $scenario ] ) ? trim( (string) $intro_map[ $scenario ] ) : '';
		if ( '' === $intro ) {
			$intro = self::label( 'conditions_intro', 'Перед продолжением проверьте правила для выбранного способа получения.' );
		}
		if ( '' !== $intro ) {
			$lines[] = $intro;
		}

		if ( ScenarioStepRegistry::SCENARIO_PICKUP === $scenario ) {
			$lines = array_merge( $lines, self::lines_pickup( $cc, $answers ) );
		} elseif ( ScenarioStepRegistry::SCENARIO_KRASNOYARSK_DELIVERY === $scenario ) {
			$lines = array_merge( $lines, self::lines_krasnoyarsk( $cc ) );
		} else {
			$lines = array_merge( $lines, self::lines_other_city( $cc ) );
		}

		$text = implode( "\n\n", array_filter( array_map( 'trim', $lines ), 'strlen' ) );
		$text = trim( preg_replace( "/\n{3,}/", "\n\n", $text ) ?? '' );

		/**
		 * Текст условий получения для заказа и уведомлений.
		 *
		 * @param string               $text     Текст.
		 * @param string               $scenario Сценарий.
		 * @param array<string, mixed> $flow     Flow.
		 */
		return (string) apply_filters( 'mp_custom_checkout_conditions_summary_text', $text, $scenario, $flow );
	}

	/**
	 * @param array<string, mixed> $cc      conditions_copy.
	 * @param array<string, mixed> $answers Ответы flow.
	 * @return array<int, string>
	 */
	private static function lines_pickup( array $cc, array $answers ): array {
		$block = isset( $cc['pickup'] ) && is_array( $cc['pickup'] ) ? $cc['pickup'] : array();

		$point = array();
		if ( isset( $answers['scenario']['pickup_point'] ) && is_array( $answers['scenario']['pickup_point'] ) ) {
			$point = $answers['scenario']['pickup_point'];
		}

		$lines = array();

		$body = isset( $block['body'] ) ? trim( (string) $block['body'] ) : '';
		if ( '' === $body ) {
			$body = self::label( 'pickup_conditions', 'Заказ выдается в точке самовывоза после подтверждения готовности. Пожалуйста, дождитесь уведомления перед визитом.' );
		}
		$lines[] = $body;

		$office_title = isset( $block['office_block_title'] ) ? trim( (string) $block['office_block_title'] ) : '';
		if ( '' === $office_title ) {
			$office_title = self::label( 'pickup_office_block_title', 'Офис и график работы' );
		}
		$lines[] = $office_title;

		$pt_title = isset( $point['title'] ) ? trim( (string) $point['title'] ) : '';
		if ( '' !== $pt_title ) {
			$lines[] = $pt_title;
		}

		$addr = isset( $block['office_address'] ) ? trim( (string) $block['office_address'] ) : '';
		if ( '' === $addr && isset( $point['address'] ) ) {
			$addr = trim( (string) $point['address'] );
		}
		if ( '' !== $addr ) {
			$lines[] = $addr;
		}

		$desc = isset( $block['office_description'] ) ? trim( (string) $block['office_description'] ) : '';
		if ( '' === $desc && isset( $point['description'] ) ) {
			$desc = trim( (string) $point['description'] );
		}
		if ( '' === $desc ) {
			$desc = self::label( 'pickup_office_default', 'Выдача заказа в офисе самовывоза после уведомления о готовности.' );
		}
		$lines[] = $desc;

		$plain = isset( $block['office_hours_plain'] ) ? trim( (string) $block['office_hours_plain'] ) : '';
		if ( '' !== $plain ) {
			$lines[] = self::label( 'pickup_hours_label', 'Часы выдачи' ) . ":\n" . self::normalize_plain_schedule( $plain );
		} else {
			$hours = isset( $block['office_hours'] ) && is_array( $block['office_hours'] ) ? $block['office_hours'] : array();
			$slots = array();
			foreach ( $hours as $h ) {
				$t = trim( (string) $h );
				if ( '' !== $t ) {
					$slots[] = $t;
				}
			}
			if ( empty( $slots ) ) {
				$slots = array( '10:00–13:00', '13:00–17:00', '17:00–20:00' );
			}
			$lines[] = self::label( 'pickup_hours_label', 'Часы выдачи' ) . ': ' . implode( ', ', $slots );
		}

		$critical = isset( $block['critical_notice'] ) ? trim( (string) $block['critical_notice'] ) : '';
		if ( '' !== $critical ) {
			$lines[] = self::label( 'critical_notice_prefix', 'Важно:' ) . ' ' . $critical;
		}

		$helper = isset( $block['convenience_helper'] ) ? trim( (string) $block['convenience_helper'] ) : '';
		if ( '' === $helper ) {
			$helper = self::label( 'pickup_convenience_helper', 'Можно приехать в удобное время в рамках указанного расписания — уточните готовность заказа по уведомлению.' );
		}
		$lines[] = $helper;

		return $lines;
	}

	/**
	 * @param array<string, mixed> $cc conditions_copy.
	 * @return array<int, string>
	 */
	private static function lines_krasnoyarsk( array $cc ): array {
		$block = isset( $cc['krasnoyarsk_delivery'] ) && is_array( $cc['krasnoyarsk_delivery'] ) ? $cc['krasnoyarsk_delivery'] : array();
		$lines = array();
		$body  = isset( $block['body'] ) ? trim( (string) $block['body'] ) : '';
		if ( '' === $body ) {
			$body = self::label( 'krasnoyarsk_conditions', 'Доставка выполняется в пределах города в выбранную дату. Курьер связывается заранее для подтверждения интервала.' );
		}
		$lines[] = $body;
		$day     = isset( $block['delivery_within_day'] ) ? trim( (string) $block['delivery_within_day'] ) : '';
		if ( '' === $day ) {
			$day = self::label( 'krasnoyarsk_delivery_day', 'Доставка в течение дня в выбранную дату. Интервал уточняется у курьера.' );
		}
		$lines[] = $day;
		return $lines;
	}

	/**
	 * @param array<string, mixed> $cc conditions_copy.
	 * @return array<int, string>
	 */
	private static function lines_other_city( array $cc ): array {
		$block = isset( $cc['other_city_delivery'] ) && is_array( $cc['other_city_delivery'] ) ? $cc['other_city_delivery'] : array();
		$lines = array();
		$body  = isset( $block['body'] ) ? trim( (string) $block['body'] ) : '';
		if ( '' === $body ) {
			$body = self::label( 'other_city_conditions', 'Срок и стоимость уточняются после подтверждения заказа. Отправка выполняется через транспортного партнера по согласованным данным.' );
		}
		$lines[] = $body;
		$log     = isset( $block['logistics_note'] ) ? trim( (string) $block['logistics_note'] ) : '';
		if ( '' === $log ) {
			$log = self::label( 'other_city_logistics', 'Отправка выполняется через логистическую компанию после комплектации и согласования реквизитов.' );
		}
		$lines[] = $log;
		return $lines;
	}

	/**
	 * Текст для заказа: сохранённый meta или сборка из мета заказа (старые заказы без meta, просмотр в админке).
	 */
	public static function get_summary_for_order( \WC_Order $order ): string {
		$text = trim( (string) $order->get_meta( self::ORDER_META_KEY, true ) );
		if ( '' !== $text ) {
			return $text;
		}

		$scenario = CheckoutScenarioRules::sanitize_scenario( (string) $order->get_meta( '_mp_cc_scenario_id', true ) );
		if ( '' === $scenario ) {
			return '';
		}

		$flow = self::synthetic_flow_from_order( $order );
		$text = self::build_for_flow( $scenario, $flow );

		/**
		 * Текст условий при отображении (если в заказе не было сохранено поле meta).
		 *
		 * @param string               $text     Текст.
		 * @param \WC_Order            $order    Заказ.
		 * @param string               $scenario Сценарий.
		 * @param array<string, mixed> $flow     Синтетический flow из мета заказа.
		 */
		return (string) apply_filters( 'mp_custom_checkout_order_conditions_summary', $text, $order, $scenario, $flow );
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function synthetic_flow_from_order( \WC_Order $order ): array {
		$pickup_point = array();
		$payload_raw  = (string) $order->get_meta( '_mp_cc_pickup_point_payload', true );
		if ( '' !== $payload_raw ) {
			$decoded = json_decode( $payload_raw, true );
			if ( is_array( $decoded ) ) {
				$pickup_point = $decoded;
			}
		}
		$id = (string) $order->get_meta( '_mp_cc_pickup_point_id', true );
		if ( empty( $pickup_point ) && '' !== $id ) {
			$pickup_point = array(
				'id'            => $id,
				'title'         => (string) $order->get_meta( '_mp_cc_pickup_point_title', true ),
				'address'       => (string) $order->get_meta( '_mp_cc_pickup_point_address', true ),
				'description'   => (string) $order->get_meta( '_mp_cc_pickup_point_description', true ),
			);
		}

		$selected_date = (string) $order->get_meta( '_mp_cc_selected_date', true );

		return array(
			'scenario' => (string) $order->get_meta( '_mp_cc_scenario_id', true ),
			'answers'  => array(
				'scenario'        => array(
					'pickup_point' => $pickup_point,
				),
				'date_conditions' => array(
					'selected_date' => $selected_date,
				),
			),
		);
	}

	private static function normalize_plain_schedule( string $plain ): string {
		$parts = preg_split( "/\r\n|\n|\r/", $plain ) ?: array();
		$out   = array();
		foreach ( $parts as $line ) {
			$t = trim( (string) $line );
			if ( '' !== $t ) {
				$out[] = $t;
			}
		}
		return implode( "\n", $out );
	}

	private static function label( string $key, string $fallback ): string {
		$v = SafeSettingsResolver::get( 'labels.step_3.' . $key, $fallback );
		return is_string( $v ) && '' !== trim( $v ) ? trim( $v ) : $fallback;
	}
}
