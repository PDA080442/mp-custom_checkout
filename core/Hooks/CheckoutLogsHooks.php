<?php
/**
 * Централизованное логирование checkout-событий.
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout\Hooks;

use MP\CustomCheckout\Logging\CheckoutLogStore;

defined( 'ABSPATH' ) || exit;

final class CheckoutLogsHooks {
	public static function register(): void {
		add_action( 'mp_custom_checkout_log_record', array( __CLASS__, 'record' ), 10, 3 );
		add_action( 'mp_custom_checkout_diagnostics_shutdown', array( __CLASS__, 'capture_php_fatal' ), 20 );
		add_action( 'init', array( __CLASS__, 'maybe_bind_php_error_handler' ), 1 );
	}

	public static function maybe_bind_php_error_handler(): void {
		if ( ! self::is_checkout_request() ) {
			return;
		}
		set_error_handler( array( __CLASS__, 'on_php_error' ) );
	}

	/**
	 * @param array<string, mixed> $context
	 */
	public static function record( string $level, string $message, array $context = array() ): void {
		$source    = isset( $context['source'] ) ? sanitize_key( (string) $context['source'] ) : self::guess_source( $message );
		$event     = isset( $context['event_type'] ) ? sanitize_key( (string) $context['event_type'] ) : self::guess_event_type( $message );
		$channel   = isset( $context['channel'] ) ? sanitize_key( (string) $context['channel'] ) : self::guess_channel( $level, $message, $source );
		$context_s = self::sanitize_context( $context );

		CheckoutLogStore::append(
			array(
				'timestamp'  => gmdate( 'c' ),
				'level'      => sanitize_key( $level ),
				'message'    => sanitize_text_field( $message ),
				'source'     => $source ?: 'checkout',
				'event_type' => $event ?: 'general',
				'channel'    => $channel ?: 'general',
				'context'    => $context_s,
			)
		);
	}

	public static function capture_php_fatal(): void {
		$last = error_get_last();
		if ( ! is_array( $last ) || empty( $last['message'] ) ) {
			return;
		}
		$is_fatal_type = in_array( (int) ( $last['type'] ?? 0 ), array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR ), true );
		if ( ! $is_fatal_type || ! self::is_checkout_request() ) {
			return;
		}
		self::record(
			'error',
			'[php] fatal_error',
			array(
				'source'     => 'php',
				'event_type' => 'php_fatal',
				'channel'    => 'critical',
				'php_type'   => (int) ( $last['type'] ?? 0 ),
				'file'       => isset( $last['file'] ) ? (string) $last['file'] : '',
				'line'       => isset( $last['line'] ) ? (int) $last['line'] : 0,
				'message'    => (string) $last['message'],
			)
		);
	}

	/**
	 * @param int    $errno
	 * @param string $errstr
	 * @param string $errfile
	 * @param int    $errline
	 */
	public static function on_php_error( $errno, $errstr, $errfile, $errline ): bool {
		if ( ! self::is_checkout_request() ) {
			return false;
		}
		$level = in_array( (int) $errno, array( E_USER_WARNING, E_WARNING, E_NOTICE, E_USER_NOTICE, E_DEPRECATED, E_USER_DEPRECATED ), true ) ? 'warning' : 'error';
		self::record(
			$level,
			'[php] runtime_error',
			array(
				'source'     => 'php',
				'event_type' => 'php_runtime',
				'channel'    => 'error' === $level ? 'critical' : 'general',
				'php_type'   => (int) $errno,
				'file'       => (string) $errfile,
				'line'       => (int) $errline,
				'message'    => (string) $errstr,
			)
		);
		return false;
	}

	private static function is_checkout_request(): bool {
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( (string) $_REQUEST['action'] ) ) : '';
			if ( 'mp_cc_checkout' === $action ) {
				return true;
			}
		}
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( (string) $_SERVER['REQUEST_URI'] ) : '';
		return false !== strpos( $uri, 'mp-checkout' );
	}

	private static function guess_source( string $message ): string {
		$m = strtolower( $message );
		if ( false !== strpos( $m, 'validation' ) ) {
			return 'validation';
		}
		if ( false !== strpos( $m, 'payment' ) ) {
			return 'payment';
		}
		if ( false !== strpos( $m, 'gateway' ) ) {
			return 'gateway';
		}
		return 'checkout';
	}

	private static function guess_event_type( string $message ): string {
		$m = strtolower( $message );
		if ( false !== strpos( $m, 'validation' ) ) {
			return 'validation';
		}
		if ( false !== strpos( $m, 'ajax' ) ) {
			return 'ajax';
		}
		if ( false !== strpos( $m, 'php' ) ) {
			return 'php';
		}
		return 'general';
	}

	private static function guess_channel( string $level, string $message, string $source ): string {
		$is_critical = in_array( sanitize_key( $level ), array( 'error', 'critical' ), true )
			&& ( in_array( $source, array( 'php', 'payment', 'ajax', 'checkout' ), true ) || false !== strpos( strtolower( $message ), 'fatal' ) );
		return $is_critical ? 'critical' : 'general';
	}

	/**
	 * @param mixed $value
	 * @return mixed
	 */
	private static function sanitize_context( $value ) {
		if ( is_array( $value ) ) {
			$san = array();
			foreach ( $value as $k => $v ) {
				$key = is_string( $k ) ? sanitize_key( $k ) : (string) $k;
				$san[ $key ] = self::is_sensitive_key( $key )
					? self::mask_sensitive_value( $v )
					: self::sanitize_context( $v );
			}
			return $san;
		}
		if ( is_bool( $value ) || is_int( $value ) || is_float( $value ) || null === $value ) {
			return $value;
		}
		if ( is_object( $value ) ) {
			return sanitize_text_field( wp_json_encode( $value ) ?: '' );
		}
		return sanitize_text_field( (string) $value );
	}

	private static function is_sensitive_key( string $key ): bool {
		$k = strtolower( $key );
		return false !== strpos( $k, 'email' )
			|| false !== strpos( $k, 'phone' )
			|| false !== strpos( $k, 'password' )
			|| false !== strpos( $k, 'token' )
			|| false !== strpos( $k, 'nonce' )
			|| false !== strpos( $k, 'coupon' )
			|| false !== strpos( $k, 'gift_card' )
			|| false !== strpos( $k, 'code' )
			|| false !== strpos( $k, 'address' )
			|| false !== strpos( $k, 'name' );
	}

	/**
	 * @param mixed $value
	 * @return string
	 */
	private static function mask_sensitive_value( $value ): string {
		$raw = is_scalar( $value ) ? (string) $value : ( wp_json_encode( $value ) ?: '' );
		$raw = trim( $raw );
		if ( '' === $raw ) {
			return '';
		}
		if ( strlen( $raw ) <= 4 ) {
			return '***';
		}
		return substr( $raw, 0, 2 ) . str_repeat( '*', max( 3, strlen( $raw ) - 4 ) ) . substr( $raw, -2 );
	}
}

