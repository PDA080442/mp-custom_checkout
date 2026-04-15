<?php
/**
 * Контракт провайдера feature flags для функций checkout.
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout\Contracts;

defined( 'ABSPATH' ) || exit;

/**
 * Interface CheckoutFeatureFlagInterface
 */
interface CheckoutFeatureFlagInterface {

	/**
	 * Включён ли флаг (булев сценарий).
	 */
	public function is_enabled( string $flag ): bool;

	/**
	 * Значение флага произвольного типа (строка, массив настроек и т.д.).
	 *
	 * @param mixed $default Значение по умолчанию, если флаг не задан.
	 * @return mixed
	 */
	public function get( string $flag, $default = null );

	/**
	 * Все флаги и их значения для текущей конфигурации.
	 *
	 * @return array<string, mixed>
	 */
	public function all(): array;
}
