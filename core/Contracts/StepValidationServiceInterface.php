<?php
/**
 * Контракт сервиса валидации шагов checkout.
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout\Contracts;

defined( 'ABSPATH' ) || exit;

/**
 * Interface StepValidationServiceInterface
 */
interface StepValidationServiceInterface {

	/**
	 * Валидирует данные шага.
	 *
	 * @param string               $step_id Идентификатор шага.
	 * @param array<string, mixed> $payload Данные для проверки (поля формы, выборы и т.д.).
	 * @return true|\WP_Error Возвращает true при успехе или WP_Error с кодами/сообщениями полей.
	 */
	public function validate( string $step_id, array $payload );

	/**
	 * Описание правил валидации для шага (для клиента или отладки).
	 *
	 * @param string $step_id Идентификатор шага.
	 * @return array<string, mixed>
	 */
	public function get_rules_for_step( string $step_id ): array;
}
