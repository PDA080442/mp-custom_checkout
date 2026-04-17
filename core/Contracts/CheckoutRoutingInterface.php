<?php
/**
 * Контракт сервиса маршрутизации кастомного checkout URL.
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout\Contracts;

defined( 'ABSPATH' ) || exit;

/**
 * Interface CheckoutRoutingInterface
 */
interface CheckoutRoutingInterface {

	/**
	 * Регистрирует правила маршрутизации (rewrite rules, query vars и т.д.).
	 */
	public function register(): void;

	/**
	 * Возвращает базовый slug/путь маршрута checkout внутри сайта.
	 */
	public function get_route_slug(): string;

	/**
	 * Возвращает полный URL страницы checkout с опциональными query-аргументами.
	 *
	 * @param array<string, scalar|null> $query_args Дополнительные GET-параметры.
	 */
	public function get_checkout_url( array $query_args = array() ): string;

	/**
	 * Определяет, относится ли текущий запрос к кастомному checkout.
	 */
	public function is_checkout_request(): bool;

	/**
	 * Разбирает текущий запрос и при необходимости помечает главный query как checkout.
	 *
	 * @param \WP $wp Главный объект WordPress (query).
	 */
	public function parse_request( \WP $wp ): void;
}
