<?php
/**
 * Контракт сервиса рендера фронтенд-компонентов checkout.
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout\Contracts;

defined( 'ABSPATH' ) || exit;

/**
 * Interface FrontendRenderingInterface
 */
interface FrontendRenderingInterface {

	/**
	 * Возвращает HTML (или фрагмент) компонента checkout.
	 *
	 * @param string               $component_id Идентификатор компонента (шаг, блок, partial).
	 * @param array<string, mixed> $context      Данные для шаблона.
	 */
	public function render( string $component_id, array $context = array() ): string;

	/**
	 * Подключает скрипты и стили, необходимые для указанного контекста отображения.
	 *
	 * @param string $context Например `checkout`, `admin_preview`.
	 */
	public function enqueue_assets( string $context = 'checkout' ): void;
}
