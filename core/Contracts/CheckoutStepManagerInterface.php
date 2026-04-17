<?php
/**
 * Контракт менеджера шагов многошагового checkout.
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout\Contracts;

defined( 'ABSPATH' ) || exit;

/**
 * Interface CheckoutStepManagerInterface
 */
interface CheckoutStepManagerInterface {

	/**
	 * Возвращает зарегистрированные шаги и их метаданные (id, label, порядок, видимость).
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function get_registered_steps(): array;

	/**
	 * Возвращает шаги, видимые в текущем сценарии (доставка / самовывоз и т.д.).
	 *
	 * @return array<int, string> Список идентификаторов шагов по порядку.
	 */
	public function get_visible_step_ids(): array;

	/**
	 * Текущий активный шаг или null, если не задан.
	 */
	public function get_current_step_id(): ?string;

	/**
	 * Устанавливает текущий шаг (после валидации навигации — на стороне реализации).
	 */
	public function set_current_step_id( string $step_id ): void;

	/**
	 * Можно ли перейти к указанному шагу (назад или к уже пройденному).
	 */
	public function can_navigate_to( string $step_id ): bool;

	/**
	 * Порядковый номер шага среди видимых (1-based), или 0 если шаг не найден.
	 */
	public function get_visible_step_index( string $step_id ): int;

	/**
	 * Общее число видимых шагов в текущем сценарии.
	 */
	public function get_visible_step_count(): int;
}
