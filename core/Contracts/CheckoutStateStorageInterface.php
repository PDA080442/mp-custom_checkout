<?php
/**
 * Контракт хранилища промежуточного состояния checkout (сессия и синхронизация с фронтендом).
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout\Contracts;

defined( 'ABSPATH' ) || exit;

/**
 * Interface CheckoutStateStorageInterface
 */
interface CheckoutStateStorageInterface {

	/**
	 * Получить значение по ключу.
	 *
	 * @param mixed $default Значение по умолчанию.
	 * @return mixed
	 */
	public function get( string $key, $default = null );

	/**
	 * Установить значение по ключу.
	 *
	 * @param mixed $value Сериализуемое значение.
	 */
	public function set( string $key, $value ): void;

	/**
	 * Удалить ключ.
	 */
	public function delete( string $key ): void;

	/**
	 * Все сохранённые пары ключ — значение для текущего checkout-потока.
	 *
	 * @return array<string, mixed>
	 */
	public function all(): array;

	/**
	 * Явно сохранить состояние в персистентное хранилище (например WooCommerce session).
	 */
	public function persist(): void;

	/**
	 * Сбросить состояние checkout (после успешного заказа или отмены сценария).
	 */
	public function clear(): void;
}
