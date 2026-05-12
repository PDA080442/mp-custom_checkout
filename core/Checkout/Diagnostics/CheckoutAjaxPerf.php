<?php
/**
 * Замеры длительности AJAX checkout (mp_cc_checkout) для диагностики «холодных» запросов.
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout\Checkout\Diagnostics;

defined( 'ABSPATH' ) || exit;

final class CheckoutAjaxPerf {

	/** @var string */
	private static $active_sub_action = '';

	/** @var float */
	private static $t0 = 0.0;

	/** @var array<string, float> */
	private static $segments_ms = array();

	/** @var bool */
	private static $shutdown_hooked = false;

	public static function register(): void {
		if ( self::$shutdown_hooked ) {
			return;
		}
		self::$shutdown_hooked = true;
		add_action( 'shutdown', array( __CLASS__, 'on_shutdown' ), 99999 );
	}

	/** Старт замера на входе {@see CheckoutAjaxHooks::handle()} (до wc_load_cart). */
	public static function mark_request_start(): void {
		if ( self::$t0 <= 0 ) {
			self::$t0          = microtime( true );
			self::$segments_ms = array();
		}
	}

	public static function begin_request( string $sub_action ): void {
		self::mark_request_start();
		self::$active_sub_action = sanitize_key( $sub_action );
	}

	/** @param float $duration_seconds Длительность сегмента в секундах. */
	public static function add_segment_ms( string $label, float $duration_seconds ): void {
		$label = sanitize_key( $label );
		if ( '' === $label ) {
			return;
		}
		self::$segments_ms[ $label ] = round( $duration_seconds * 1000, 2 );
	}

	public static function on_shutdown(): void {
		if ( '' === self::$active_sub_action || self::$t0 <= 0 ) {
			return;
		}
		$total_ms = ( microtime( true ) - self::$t0 ) * 1000;
		if ( $total_ms >= 1500 ) {
			do_action(
				'mp_custom_checkout_log',
				'warning',
				'[perf] slow_ajax',
				array(
					'sub_action'   => self::$active_sub_action,
					'total_ms'     => round( $total_ms, 2 ),
					'segments_ms'  => self::$segments_ms,
				)
			);
		}
		self::$active_sub_action = '';
		self::$t0                = 0.0;
		self::$segments_ms       = array();
	}
}
