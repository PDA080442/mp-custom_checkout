<?php
/**
 * Логирование и диагностика (точки расширения).
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout\Hooks;

defined( 'ABSPATH' ) || exit;

/**
 * Class DiagnosticsHooks
 */
final class DiagnosticsHooks {

	/**
	 * Регистрация хуков диагностики.
	 */
	public static function register(): void {
		add_action( 'shutdown', array( __CLASS__, 'on_shutdown' ), 999 );
		add_action( 'mp_custom_checkout_log', array( __CLASS__, 'on_log' ), 10, 3 );
	}

	/**
	 * Конец запроса — сбор метрик/ошибок (расширяется позже).
	 */
	public static function on_shutdown(): void {
		/**
		 * Точка диагностики после выполнения запроса.
		 */
		do_action( 'mp_custom_checkout_diagnostics_shutdown' );
	}

	/**
	 * @param string               $level   Уровень (error, warning, info…).
	 * @param string               $message Сообщение.
	 * @param array<string, mixed> $context Контекст.
	 */
	public static function on_log( string $level, string $message, array $context = array() ): void {
		/**
		 * Запись события лога checkout.
		 *
		 * @param string               $level   Уровень.
		 * @param string               $message Сообщение.
		 * @param array<string, mixed> $context Контекст.
		 */
		do_action( 'mp_custom_checkout_log_record', $level, $message, $context );
	}
}
