<?php
/**
 * Контракт маппинга данных checkout в order meta WooCommerce.
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout\Contracts;

defined( 'ABSPATH' ) || exit;

/**
 * Interface OrderMetaMappingInterface
 */
interface OrderMetaMappingInterface {

	/**
	 * Преобразует нормализованные данные checkout в пары meta_key => meta_value для заказа.
	 *
	 * @param array<string, mixed> $checkout_data Данные после прохождения checkout.
	 * @return array<string, mixed> Значения должны быть пригодны для сохранения через order meta API.
	 */
	public function map_checkout_to_order_meta( array $checkout_data ): array;

	/**
	 * Список зарегистрированных ключей order meta, которыми управляет плагин.
	 *
	 * @return array<int, string>
	 */
	public function get_meta_keys(): array;

	/**
	 * Человекочитаемые подписи для вывода в админке и письмах.
	 *
	 * @return array<string, string> meta_key => label
	 */
	public function get_display_labels(): array;
}
