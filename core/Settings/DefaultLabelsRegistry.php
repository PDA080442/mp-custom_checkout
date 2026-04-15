<?php
/**
 * Дефолтные UI-тексты и лейблы (базовая карта для перевода и настроек).
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Class DefaultLabelsRegistry
 */
final class DefaultLabelsRegistry {

	/**
	 * Карта лейблов по умолчанию (вложенные ключи — для resolver по точкам).
	 *
	 * @return array<string, mixed>
	 */
	public static function all(): array {
		return array(
			'common' => array(
				'back'          => 'Назад',
				'next'          => 'Далее',
				'pay'           => 'Перейти к оплате',
				'confirm'       => 'Подтвердить',
				'loading'       => 'Загрузка…',
				'error_generic' => 'Произошла ошибка. Попробуйте ещё раз.',
			),
			'step_1' => array(
				'title'            => 'Корзина',
				'empty_cart'       => 'Корзина пуста',
				'return_to_shop'   => 'Вернуться в магазин',
				'continue'         => 'Продолжить оформление',
				'subtotal'         => 'Подытог',
				'positions_count'  => 'Позиций',
			),
			'step_2' => array(
				'title' => 'Дата доставки или получения',
			),
			'step_3' => array(
				'title'            => 'Условия получения',
				'confirm_checkbox' => 'Я ознакомился с условиями',
			),
			'step_4' => array(
				'title' => 'Контакты и оплата',
			),
			'coupon' => array(
				'apply'   => 'Применить',
				'success' => 'Промокод применён',
				'error'   => 'Не удалось применить промокод',
			),
			'gift_card' => array(
				'apply'   => 'Применить',
				'success' => 'Подарочная карта применена',
				'error'   => 'Не удалось применить подарочную карту',
			),
			'order_review' => array(
				'title'            => 'К оплате',
				'shipping'         => 'Доставка',
				'subtotal'         => 'Подытог',
				'total'            => 'Итого',
				'discount'         => 'Скидка',
				'tax'              => 'Налог',
				'positions_count'  => 'Позиций',
			),
			'success' => array(
				'title'   => 'Заказ оформлен',
				'message' => 'Спасибо за покупку. Мы свяжемся с вами при необходимости.',
			),
		);
	}
}
