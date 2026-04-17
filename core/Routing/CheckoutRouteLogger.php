<?php
/**
 * Логирование ошибок маршрутизации checkout.
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout\Routing;

defined( 'ABSPATH' ) || exit;

/**
 * Class CheckoutRouteLogger
 */
final class CheckoutRouteLogger {

	/**
	 * Лог сбоя маршрута (прокидывается в mp_custom_checkout_log).
	 *
	 * @param array<string, mixed> $context Дополнительный контекст.
	 */
	public static function log_failure( string $code, array $context = array() ): void {
		$message = sprintf( '[checkout_route] %s', $code );
		$payload   = array_merge(
			array( 'code' => $code ),
			$context
		);

		do_action( 'mp_custom_checkout_log', 'error', $message, $payload );
	}
}
