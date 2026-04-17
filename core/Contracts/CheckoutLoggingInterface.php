<?php
/**
 * Контракт логгера ошибок и событий checkout.
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout\Contracts;

defined( 'ABSPATH' ) || exit;

/**
 * Interface CheckoutLoggingInterface
 */
interface CheckoutLoggingInterface {

	/**
	 * Универсальная запись в лог с уровнем и контекстом.
	 *
	 * @param string               $level   Например `error`, `warning`, `info`, `debug`.
	 * @param string               $message Сообщение.
	 * @param array<string, mixed> $context Дополнительные данные (не логировать секреты).
	 */
	public function log( string $level, string $message, array $context = array() ): void;

	/**
	 * Запись критической/ошибочной ситуации checkout.
	 *
	 * @param array<string, mixed> $context Дополнительные данные.
	 */
	public function error( string $message, array $context = array() ): void;

	/**
	 * Предупреждение (деградация, повтор запроса и т.д.).
	 *
	 * @param array<string, mixed> $context Дополнительные данные.
	 */
	public function warning( string $message, array $context = array() ): void;
}
