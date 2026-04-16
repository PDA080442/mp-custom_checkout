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
				'title'                 => 'Условия получения',
				'confirm_checkbox'      => 'Я ознакомился с условиями',
				'conditions_title'      => 'Условия получения',
				'conditions_intro'      => 'Перед продолжением проверьте правила для выбранного способа получения.',
				'krasnoyarsk_title'     => 'Доставка по Красноярску',
				'krasnoyarsk_conditions'=> 'Доставка выполняется в пределах города в выбранную дату. Курьер связывается заранее для подтверждения интервала.',
				'other_city_title'      => 'Доставка в другой город',
				'other_city_conditions' => 'Срок и стоимость уточняются после подтверждения заказа. Отправка выполняется через транспортного партнера по согласованным данным.',
				'pickup_title'          => 'Самовывоз',
				'pickup_conditions'     => 'Заказ выдается в точке самовывоза после подтверждения готовности. Пожалуйста, дождитесь уведомления перед визитом.',
				'notes_title'           => 'Важные замечания',
				'secondary_note_1'      => 'Проверяйте корректность телефона: статус заказа приходит в уведомления.',
				'secondary_note_2'      => 'При изменении сценария условия и доступность дат обновляются автоматически.',
				'secondary_note_3'      => 'Для вопросов по срокам и логистике используйте контакты поддержки магазина.',
				'unconfirmed_error'     => 'Подтвердите ознакомление с условиями, чтобы продолжить.',
				'krasnoyarsk_delivery_day' => 'Доставка в течение дня в выбранную дату. Интервал уточняется у курьера.',
				'other_city_logistics'  => 'Отправка выполняется через логистическую компанию после комплектации и согласования реквизитов.',
				'pickup_office_default' => 'Выдача заказа в офисе самовывоза после уведомления о готовности.',
				'pickup_hours_label'    => 'Часы выдачи',
				'pickup_office_block_title' => 'Офис и график работы',
				'pickup_convenience_helper' => 'Можно приехать в удобное время в рамках указанного расписания — уточните готовность заказа по уведомлению.',
				'pickup_multi_office_hint'  => 'Дополнительные точки самовывоза будут отображаться здесь при подключении.',
			),
			'step_4' => array(
				'title' => 'Контакты и оплата',
				'contact_last_name' => 'Фамилия',
				'contact_first_name' => 'Имя',
				'contact_patronymic' => 'Отчество',
				'contact_gender' => 'Пол',
				'contact_birthdate' => 'Дата рождения',
				'contact_order_notes' => 'Примечания к заказу',
				'contact_email' => 'Email',
				'contact_phone' => 'Телефон',
				'contact_country_code' => 'Код страны',
				'contact_hint_email' => 'На этот адрес отправим подтверждение заказа.',
				'contact_hint_phone' => 'Введите номер без кода страны — он выбран слева.',
				'contact_hint_patronymic' => 'Укажите при наличии.',
				'contact_hint_gender' => 'Необязательное поле.',
				'contact_hint_birthdate' => 'Используем для корректной обработки заказа и персонализации сервиса.',
				'contact_hint_order_notes' => 'Оставьте детали по доставке, упаковке или пожелания к заказу.',
				'contact_block_intro' => 'Укажите данные для связи и оформления заказа.',
				'contact_error_required' => 'Заполните это поле.',
				'contact_error_email' => 'Введите корректный email.',
				'contact_error_phone' => 'Введите номер полностью.',
				'contact_error_phone_required' => 'Укажите номер телефона.',
				'contact_error_step_blocked' => 'Заполните обязательные поля текущего шага.',
				'contact_error_birthdate_required' => 'Укажите дату рождения.',
				'contact_error_birthdate_invalid' => 'Введите корректную дату рождения.',
				'contact_error_birthdate_range' => 'Допустимый возраст: от 0 до 120 лет.',
				'contact_error_order_notes_length' => 'Превышена максимальная длина примечания.',
				'contact_ajax_draft_save_failed' => 'Не удалось сохранить данные.',
				'contact_ajax_step_sync_failed' => 'Не удалось синхронизировать шаг. Обновите страницу.',
				'contact_ajax_scenario_sync_failed' => 'Не удалось сохранить выбор сценария.',
				'contact_gender_placeholder' => 'Не указывать',
				'contact_gender_male' => 'Мужчина',
				'contact_gender_female' => 'Женщина',
				'contact_payment_next' => 'Продолжить к оплате',
				'address_country' => 'Страна',
				'address_region' => 'Регион',
				'address_city' => 'Населённый пункт',
				'address_line1' => 'Улица, дом',
				'address_line2' => 'Квартира, офис',
				'address_postcode' => 'Почтовый индекс',
				'address_block_title' => 'Адрес доставки',
				'address_block_intro' => 'Укажите адрес, чтобы мы могли доставить заказ.',
				'address_error_required' => 'Заполните это поле.',
				'address_error_region' => 'Выберите корректный регион.',
				'address_error_city' => 'Выберите населённый пункт из списка.',
				'address_error_postcode' => 'Слишком длинный индекс.',
				'address_region_placeholder' => 'Выберите регион',
				'address_city_placeholder' => 'Выберите населённый пункт',
				'coupon_title' => 'Промокод',
				'coupon_intro' => 'Введите код купона, если он у вас есть.',
				'coupon_input_label' => 'Код купона',
				'coupon_placeholder' => 'Например, SPRING10',
				'coupon_apply' => 'Применить',
				'coupon_empty' => 'Введите код купона.',
				'coupon_success' => 'Промокод применён.',
				'coupon_error' => 'Не удалось применить промокод.',
				'gift_card_title' => 'Подарочная карта',
				'gift_card_intro' => 'Введите код подарочной карты.',
				'gift_card_input_label' => 'Код подарочной карты',
				'gift_card_placeholder' => 'Например, GIFT-123',
				'gift_card_apply' => 'Применить',
				'gift_card_empty' => 'Введите код подарочной карты.',
				'gift_card_success' => 'Подарочная карта применена.',
				'gift_card_error' => 'Не удалось применить подарочную карту.',
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
				'title'               => 'Заказ оформлен',
				'message'             => 'Спасибо за покупку. Мы свяжемся с вами при необходимости.',
				'order_summary_title' => 'Ваш заказ',
				'order_number'        => 'Номер заказа',
				'status'              => 'Статус',
				'payment'             => 'Способ оплаты',
				'total'               => 'Итого',
				'date'                => 'Дата',
				'cta_primary_label'   => 'В магазин',
				'cta_primary_url'     => '',
				'cta_secondary_label' => '',
				'cta_secondary_url'   => '',
			),
		);
	}
}
