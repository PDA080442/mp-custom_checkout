<?php
/**
 * Конфигурируемая карта вкладок админ-экрана.
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout\Admin\Config;

use MP\CustomCheckout\Settings\AdminSectionsRegistry;

defined( 'ABSPATH' ) || exit;

final class AdminTabRegistry {
	/**
	 * @return array<string, array<string, mixed>>
	 */
	public static function tabs(): array {
		$sections = AdminSectionsRegistry::sections();
		$tabs = array();

		foreach ( $sections as $id => $meta ) {
			$type = isset( $meta['type'] ) ? (string) $meta['type'] : '';
			if ( ! in_array( $type, array( 'tab', 'entity' ), true ) ) {
				continue;
			}
			$tabs[ $id ] = array(
				'id'          => (string) $id,
				'label'       => isset( $meta['label'] ) ? (string) $meta['label'] : (string) $id,
				'type'        => $type,
				'sort'        => isset( $meta['sort'] ) ? (int) $meta['sort'] : 999,
				'description' => self::description_for_tab( (string) $id, $type ),
				'onboarding'  => self::onboarding_for_tab( (string) $id, $type ),
			);
		}

		uasort(
			$tabs,
			static function ( array $a, array $b ): int {
				return (int) ( $a['sort'] ?? 999 ) <=> (int) ( $b['sort'] ?? 999 );
			}
		);

		/**
		 * Позволяет расширять/переопределять карту вкладок.
		 *
		 * @param array<string, array<string, mixed>> $tabs
		 */
		return (array) apply_filters( 'mp_custom_checkout_admin_tabs_registry', $tabs );
	}

	private static function description_for_tab( string $tab_id, string $type ): string {
		if ( 'entity' === $type ) {
			return __( 'Сущностные настройки, влияющие на отдельный блок checkout и его бизнес-правила.', 'mp-custom-checkout' );
		}
		switch ( $tab_id ) {
			case 'general':
				return __( 'Базовые параметры маршрутов checkout, общая интеграция и ядро поведения.', 'mp-custom-checkout' );
			case 'step_1':
			case 'step_2':
			case 'step_3':
			case 'step_4':
				return __( 'Настройки конкретного шага: копирайт, логика, UX и валидация.', 'mp-custom-checkout' );
			case 'payment':
				return __( 'Управление платежными методами, состояниями и текстами оплаты.', 'mp-custom-checkout' );
			case 'styles':
				return __( 'Визуальные токены, плотность интерфейса и адаптивное поведение.', 'mp-custom-checkout' );
			case 'service':
				return __( 'Служебные переключатели, диагностика и режимы поддержки.', 'mp-custom-checkout' );
			default:
				return __( 'Параметры раздела checkout.', 'mp-custom-checkout' );
		}
	}

	private static function onboarding_for_tab( string $tab_id, string $type ): string {
		if ( 'entity' === $type ) {
			return __( 'Измените значения и сохраните. Проверяйте превью/checkout после каждого изменения.', 'mp-custom-checkout' );
		}
		switch ( $tab_id ) {
			case 'general':
				return __( 'Начните с базовой маршрутизации и общих ограничений, затем переходите к шагам.', 'mp-custom-checkout' );
			case 'step_4':
				return __( 'Здесь сосредоточены контактные данные, скидки и оплата — проверяйте сценарии особенно внимательно.', 'mp-custom-checkout' );
			case 'styles':
				return __( 'Стили лучше менять после финализации логики шагов, чтобы не дублировать работу.', 'mp-custom-checkout' );
			default:
				return __( 'Сначала обновите ключевые тексты, затем логику и только после этого визуальные параметры.', 'mp-custom-checkout' );
		}
	}
}

