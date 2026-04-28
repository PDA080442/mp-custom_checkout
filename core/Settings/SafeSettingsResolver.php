<?php
/**
 * Безопасное получение настроек с подстановкой значений по умолчанию.
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Class SafeSettingsResolver
 */
final class SafeSettingsResolver {

	/**
	 * @var array<string, mixed>|null
	 */
	private static $merged_cache = null;

	/**
	 * Полное дерево значений по умолчанию (все разделы).
	 *
	 * @return array<string, mixed>
	 */
	public static function get_defaults_tree(): array {
		$tree = array();

		foreach ( OptionKeys::all_section_keys() as $section_key ) {
			if ( OptionKeys::KEY_LABELS === $section_key ) {
				$tree[ $section_key ] = DefaultLabelsRegistry::all();
				continue;
			}
			if ( OptionKeys::KEY_FEATURE_FLAGS === $section_key ) {
				$tree[ $section_key ] = DefaultFeatureFlagsRegistry::all();
				continue;
			}
			if ( OptionKeys::KEY_DESIGN_TOKENS === $section_key ) {
				$tree[ $section_key ] = DefaultDesignTokensRegistry::all();
				continue;
			}
			if ( OptionKeys::KEY_REGISTRY === $section_key ) {
				$tree[ $section_key ] = ScenarioStepRegistry::default_storage_snapshot();
				continue;
			}

			if ( OptionKeys::SECTION_GENERAL === $section_key ) {
				$tree[ $section_key ] = array(
					'route_slug'          => 'mp-checkout',
					'require_entry_gate'  => true,
					'success_route_slug'  => 'mp-checkout-success',
					'admin_branding'      => array(
						'title'         => 'MP Custom Checkout — Настройки',
						'description'   => 'Единый экран управления сценариями checkout, текстами, валидацией и визуальным поведением шагов.',
						'onboarding'    => 'Сначала проверьте вкладку Общие, затем настройте шаги и только после этого стили.',
						'icon'          => 'dashicons-cart',
						'accent_color'  => '#2271b1',
						'help_style'    => 'soft',
						'ui_tokens'     => array(
							'bg'            => '#ffffff',
							'surface'       => '#fcfcfc',
							'border'        => '#dcdcde',
							'text'          => '#1f2328',
							'muted'         => '#4b5563',
							'risk_bg'       => '#fff7f7',
							'risk_border'   => '#fca5a5',
						),
						'preview_enabled' => true,
					),
				);
				continue;
			}
			if ( OptionKeys::SECTION_STEP_1 === $section_key ) {
				$tree[ $section_key ] = array(
					'labels' => array(
						'title'          => 'Корзина',
						'summary_title'  => 'Сводка заказа',
						'subtotal_label' => 'Подытог',
						'shipping_label' => 'Доставка',
						'discount_label' => 'Скидка',
						'gift_card_label'=> 'Подарочная карта',
						'tax_label'      => 'Налоги',
						'total_label'    => 'Итого',
						'items_label'    => 'Позиций',
						'continue_label' => 'Продолжить оформление',
						'return_label'   => 'Вернуться в магазин',
						'empty_title'    => 'Корзина пуста',
						'address_form'   => array(
							'city_row'         => 'населённый пункт',
							'city_placeholder' => 'Укажите город',
							'city_empty_hint'  => 'Укажите населённый пункт',
							'change_button'    => 'другой',
							'method_row'       => 'способ доставки',
							'tariff_intro'     => 'Выбрать вариант:',
							'office_row'       => 'адрес офиса',
							'office_not_set'   => 'Не выбран',
						),
					),
					'product_meta_visibility' => array(
						'show_image'      => true,
						'show_sku'        => true,
						'show_variation'  => true,
						'show_price'      => true,
						'show_subtotal'   => true,
					),
					'quantity_controls' => array(
						'enabled'            => true,
						'allow_manual_input' => true,
						'show_increment'     => true,
						'show_decrement'     => true,
					),
					'empty_state' => array(
						'message'     => 'Добавьте товары, чтобы продолжить оформление.',
						'cta_label'   => 'Вернуться в магазин',
						'cta_enabled' => true,
					),
					'style_controls' => array(
						'card_compact'       => false,
						'card_emphasis'      => 'default',
						'summary_emphasis'   => 'default',
					),
					'layout_order' => array(
						'secondary_order' => array( 'price', 'sku', 'variation', 'quantity', 'subtotal', 'remove' ),
					),
					'address_form_style_preset' => 'concept_a',
					'address_form_styles' => array(
						'card_bg'             => '#ffffff',
						'card_border'         => '#e5e7eb',
						'card_radius'         => '14px',
						'row_divider'         => '#e5e7eb',
						'label_color'         => '#111111',
						'label_size'          => '1.05rem',
						'value_color'         => '#1f2937',
						'value_size'          => '1.03rem',
						'placeholder_color'   => '#80868f',
						'option_title_color'  => '#111111',
						'option_title_size'   => '1.05rem',
						'option_hint_color'   => '#6b7280',
						'option_hint_size'    => '0.96rem',
						'radio_border_color'  => '#8b919a',
						'radio_checked_color' => '#111111',
						'edit_btn_bg'         => '#f3f4f6',
						'edit_btn_border'     => '#d9dce1',
						'edit_btn_color'      => '#1f2937',
						'edit_btn_radius'     => '6px',
					),
					'responsive' => array(
						'desktop_mode'       => 'comfortable',
						'tablet_mode'        => 'comfortable',
						'mobile_mode'        => 'compact',
						'hide_media_mobile'  => false,
					),
					'admin_preview' => array(
						'enabled' => true,
					),
				);
				continue;
			}
			if ( OptionKeys::SECTION_STEP_2 === $section_key ) {
				$tree[ $section_key ] = array(
					'default_scenario' => ScenarioStepRegistry::SCENARIO_PICKUP,
					'card_order'       => array( 'pickup', 'delivery' ),
					'cards'            => array(
						'pickup'   => array(
							'title'            => 'Самовывоз',
							'description'      => 'Заберите заказ в удобное время в точке самовывоза.',
							'helper'           => 'Обычно готово к выдаче в день заказа.',
							'icon_variant'     => 'pickup',
							'icon_style'       => 'soft',
						),
						'delivery' => array(
							'title'            => 'Доставка',
							'description'      => 'Выберите доставку по городу или в другой город.',
							'helper'           => 'Стоимость и сроки зависят от адреса.',
							'icon_variant'     => 'delivery',
							'icon_style'       => 'soft',
						),
					),
					'responsive'       => array(
						'desktop_columns' => 2,
						'tablet_columns'  => 1,
						'mobile_columns'  => 1,
						'card_density'    => 'comfortable',
					),
					'admin_preview'    => array(
						'enabled' => true,
					),
				);
				continue;
			}
			if ( OptionKeys::SECTION_STEP_3 === $section_key ) {
				$tree[ $section_key ] = array(
					'min_lead_time_days' => array(
						'pickup'               => 1,
						'krasnoyarsk_delivery' => 1,
						'other_city_delivery'  => 2,
					),
					'max_days_ahead' => array(
						'pickup'               => 14,
						'krasnoyarsk_delivery' => 21,
						'other_city_delivery'  => 30,
					),
					'copy' => array(
						'title' => 'Выберите дату получения',
						'helper_by_scenario' => array(
							'pickup'               => 'Выберите удобную дату самовывоза.',
							'krasnoyarsk_delivery' => 'Выберите дату доставки по Красноярску.',
							'other_city_delivery'  => 'Выберите дату отправки в другой город.',
						),
						'errors' => array(
							'invalid_date' => 'Выбранная дата недоступна. Обновите шаг и выберите другую дату.',
							'empty_date'   => 'Выберите дату, чтобы продолжить.',
							'conditions_unconfirmed' => 'Подтвердите ознакомление с условиями, чтобы продолжить.',
						),
						'admin_preview' => array(
							'enabled' => true,
						),
					),
					'calendar_style' => array(
						'density'          => 'comfortable',
						'day_shape'        => 'rounded',
						'highlight_style'  => 'accent',
						'show_weekend_tint'=> true,
					),
					'weekday_rules' => array(
						'pickup'                => array( 1, 2, 3, 4, 5, 6 ),
						'krasnoyarsk_delivery'  => array( 1, 2, 3, 4, 5, 6 ),
						'other_city_delivery'   => array( 1, 3, 5 ),
					),
					'holiday_dates' => array(),
					'closed_dates'  => array(),
					'conditions_copy' => array(
						'intro_by_scenario' => array(
							'pickup'               => '',
							'krasnoyarsk_delivery' => '',
							'other_city_delivery'  => '',
						),
						'secondary_notes' => array(
							'',
							'',
							'',
						),
						'krasnoyarsk_delivery' => array(
							'title'               => '',
							'body'                => '',
							'delivery_within_day' => 'Доставка в течение дня в выбранную дату. Интервал уточняется у курьера.',
						),
						'other_city_delivery' => array(
							'title'          => '',
							'body'           => '',
							'logistics_note' => 'Отправка выполняется через логистическую компанию после комплектации и согласования реквизитов.',
						),
						'pickup' => array(
							'title'                => '',
							'body'                 => '',
							'office_block_title'   => 'Офис и график работы',
							'office_address'       => '',
							'office_description'   => 'Выдача заказа в офисе самовывоза после уведомления о готовности.',
							'office_hours_plain'   => "Пн–Пт 10:00–20:00\nСб–Вс 11:00–18:00",
							'office_hours'         => array( '10:00–13:00', '13:00–17:00', '17:00–20:00' ),
							'convenience_helper'   => 'Можно приехать в удобное время в рамках указанного расписания — уточните готовность заказа по уведомлению.',
							'critical_notice'      => '',
							'show_multi_office_slot' => true,
						),
					),
				);
				continue;
			}
			if ( OptionKeys::SECTION_STEP_4 === $section_key ) {
				$tree[ $section_key ] = array(
					'contact_block' => array(
						'title'               => 'Контактные данные',
						'intro'               => 'Укажите данные для связи и оформления заказа.',
						'patronymic_required' => false,
						'field_order'         => array( 'last_name', 'first_name', 'patronymic', 'gender', 'birthdate', 'email', 'phone', 'order_notes' ),
						'field_visibility'    => array(
							'last_name'  => true,
							'first_name' => true,
							'patronymic' => true,
							'gender'     => true,
							'birthdate'  => true,
							'email'      => true,
							'phone'      => true,
							'order_notes'=> true,
						),
						'field_required'      => array(
							'last_name'  => true,
							'first_name' => true,
							'patronymic' => false,
							'gender'     => false,
							'birthdate'  => true,
							'email'      => true,
							'phone'      => true,
							'order_notes'=> false,
						),
						'placeholders'        => array(
							'last_name'  => '',
							'first_name' => '',
							'patronymic' => '',
							'gender'     => '',
							'birthdate'  => '',
							'email'      => '',
							'phone'      => '',
							'order_notes'=> '',
						),
						'labels'              => array(
							'last_name'    => 'Фамилия',
							'first_name'   => 'Имя',
							'patronymic'   => 'Отчество',
							'gender'       => 'Пол',
							'birthdate'    => 'Дата рождения',
							'email'        => 'Email',
							'phone'        => 'Телефон',
							'order_notes'  => 'Примечания к заказу',
							'country_code' => 'Код страны',
						),
						'hints'               => array(
							'email'      => 'На этот адрес отправим подтверждение заказа.',
							'phone'      => 'Введите номер без кода страны — он выбран слева.',
							'patronymic' => 'Укажите при наличии.',
							'gender'     => 'Необязательное поле.',
							'birthdate'  => 'Используем для корректной обработки заказа и персонализации сервиса.',
							'order_notes'=> 'Оставьте детали по доставке, упаковке или пожелания к заказу.',
						),
						'gender_options'      => array(
							'placeholder' => 'Не указывать',
							'male'        => 'Мужчина',
							'female'      => 'Женщина',
						),
						'validation_messages' => array(
							'required'           => 'Заполните это поле.',
							'email_invalid'      => 'Введите корректный email.',
							'phone_required'     => 'Укажите номер телефона.',
							'phone_format'       => 'Введите номер полностью.',
							'birthdate_required' => 'Укажите дату рождения.',
							'birthdate_invalid'  => 'Введите корректную дату рождения.',
							'birthdate_range'    => 'Допустимый возраст: от 0 до 120 лет.',
							'order_notes_length' => 'Превышена максимальная длина примечания.',
							'address_required'   => 'Заполните это поле.',
							'address_region'     => 'Выберите корректный регион.',
							'address_city'       => 'Выберите населённый пункт из списка.',
							'address_postcode'   => 'Слишком длинный индекс.',
							'step_blocked'       => 'Заполните обязательные поля текущего шага.',
							'conditions_required'=> 'Подтвердите ознакомление с условиями, чтобы продолжить.',
						),
						'validation_constraints' => array(
							'birthdate_min_age'    => 0,
							'birthdate_max_age'    => 120,
							'phone_digits_override'=> 0,
						),
						'ajax_messages' => array(
							'draft_save_failed'    => 'Не удалось сохранить данные.',
							'step_sync_failed'     => 'Не удалось синхронизировать шаг. Обновите страницу.',
							'scenario_sync_failed' => 'Не удалось сохранить выбор сценария.',
						),
						'order_notes_max_length' => 500,
						'order_notes_counter'    => array(
							'enabled' => true,
						),
						'phone_country_codes' => self::default_phone_country_codes(),
						'default_phone_country_iso' => 'RU',
						'layout'              => array(
							'desktop_columns' => 3,
							'tablet_columns'  => 2,
							'mobile_columns'  => 1,
							'grid_gap'        => '0.75rem 1rem',
						),
						'field_state_styles'  => array(
							'invalid_style' => 'default',
							'hint_style'    => 'default',
							'focus_style'   => 'default',
							'disabled_style'=> 'default',
						),
					),
					'address_block' => array(
						'title'               => 'Адрес доставки',
						'intro'               => 'Укажите адрес, чтобы мы могли доставить заказ.',
						'default_country'     => 'RU',
						'subfields_order'     => array( 'country', 'state', 'city', 'address_1', 'address_2', 'postcode' ),
						'subfields_visible'   => array(
							'country'    => true,
							'state'      => true,
							'city'       => true,
							'address_1'  => true,
							'address_2'  => true,
							'postcode'   => true,
						),
						'postcode_max_length' => 16,
						'labels'              => array(
							'country'   => 'Страна',
							'state'     => 'Регион',
							'city'      => 'Населённый пункт',
							'address_1' => 'Улица, дом',
							'address_2' => 'Квартира, офис',
							'postcode'  => 'Почтовый индекс',
						),
					),
					'address_geo' => array(
						'RU' => array(
							'label'   => 'Россия',
							'regions' => array(
								'KRA' => array(
									'label'        => 'Красноярский край',
									'settlements'  => array( 'Красноярск', 'Норильск', 'Ачинск' ),
								),
								'MOW' => array(
									'label'        => 'Москва',
									'settlements'  => array( 'Москва' ),
								),
								'SPE' => array(
									'label'        => 'Санкт-Петербург',
									'settlements'  => array( 'Санкт-Петербург' ),
								),
							),
						),
						'KZ' => array(
							'label'   => 'Казахстан',
							'regions' => array(
								'ALA' => array(
									'label'        => 'Алматы',
									'settlements'  => array( 'Алматы' ),
								),
								'AST' => array(
									'label'        => 'Астана',
									'settlements'  => array( 'Астана' ),
								),
							),
						),
						'BY' => array(
							'label'   => 'Беларусь',
							'regions' => array(
								'MINSK' => array(
									'label'        => 'Минская область',
									'settlements'  => array( 'Минск', 'Борисов' ),
								),
							),
						),
					),
					'discount_layout' => array(
						'placement'             => 'step_4',
						'separate_step_enabled' => false,
						'order'                 => array( 'coupon' ),
					),
					'discount_block_styles' => array(
						'state_empty'   => 'default',
						'state_success' => 'success',
						'state_error'   => 'error',
						'focus_style'   => 'default',
					),
					'coupon_block' => array(
						'title'                 => 'Промокод',
						'intro'                 => 'Введите промокод.',
						'input_label'           => 'Промокод',
						'placeholder'           => 'Например, SALE10',
						'apply_label'           => 'Применить',
						'empty_message'         => 'Введите промокод.',
						'success_message'       => 'Промокод применён.',
						'error_message'         => 'Не удалось применить промокод. Проверьте написание и срок действия купона.',
						'allow_remove_applied'  => true,
						'summary_section_title' => '',
					),
					'gift_card_block' => array(
						'title'                      => '',
						'intro'                      => '',
						'input_label'                => '',
						'placeholder'                => '',
						'apply_label'                => '',
						'empty_message'              => '',
						'success_message'            => '',
						'error_message'              => '',
						'peer_next_to_payment'       => true,
						'allow_remove_applied'       => true,
						'card_title'                 => '',
						'card_subtitle'              => '',
						'unavailable_message'        => '',
					),
					'payment_block' => array(
						'title' => 'Способ оплаты',
						'intro' => 'Выберите удобный способ оплаты.',
						'gateway_order' => array(),
						'card_surface' => 'visual',
						'auto_classic_on_empty_gateway_fields' => true,
						'decorative_card_fields' => true,
						'layout' => array(
							'desktop_columns' => 2,
							'tablet_columns'  => 2,
							'mobile_columns'  => 1,
							'grid_gap'        => '0.6rem 0.75rem',
						),
						'card_style' => 'default',
						'card_active_style' => 'accent',
						'radio_style' => 'default',
						'description_style' => 'muted',
						'show_description' => true,
						'required' => true,
						'bank_card_visual' => array(
							'enabled'                 => true,
							'confirm_on_click_only'   => true,
							'allow_deselect'          => true,
							'card_max_width'          => '100%',
							'glow_color'              => '#a78bfa',
							'glow_intensity'          => 'medium',
							'show_check_pill'         => true,
						),
						'error_message' => 'Выберите способ оплаты.',
						'messages' => array(
							'loading' => 'Сохраняем выбранный способ оплаты...',
							'success' => 'Способ оплаты обновлён.',
							'error'   => 'Не удалось переключить способ оплаты.',
						),
						'summary_mini_review' => array(
							'enabled'                  => true,
							'title'                    => '',
							'intro'                    => '',
							'method_label'             => '',
							'id_label'                 => '',
							'state_loading'            => '',
							'state_success'            => '',
							'state_error'              => '',
							'show_gateway_id'          => false,
							'show_gateway_description' => true,
						),
						'diagnostics' => array(
							'enabled' => true,
						),
					),
					'geo_preview' => array(
						'enabled' => true,
					),
				);
				continue;
			}
			if ( OptionKeys::SECTION_DELIVERY === $section_key ) {
				$tree[ $section_key ] = array(
					'shipping_catalog' => array(
						'sort_order' => array( 'post_russia', 'courier', 'pvz', 'krasnoyarsk_delivery', 'pickup', 'custom_1', 'custom_2' ),
						'methods'    => array(
							'post_russia' => array(
								'title'               => 'Почта России',
								'price'               => 321,
								'eta'                 => '',
								'description'         => 'Отправка через отделение Почты России.',
								'active'              => true,
								'requires_address'    => true,
								'visibility_scenarios'=> array( 'other_city_delivery' ),
							),
							'courier' => array(
								'title'               => 'Курьером до двери',
								'price'               => 375,
								'eta'                 => '2 дней',
								'description'         => 'Доставка курьером по адресу получателя.',
								'active'              => true,
								'requires_address'    => true,
								'visibility_scenarios'=> array( 'krasnoyarsk_delivery', 'other_city_delivery' ),
								'tariffs'             => array(
									'express'  => array( 'title' => 'Курьером до двери (экспресс)', 'price' => 550, 'eta' => '2 дней', 'active' => true ),
									'standard' => array( 'title' => 'Курьером до двери (стандарт)', 'price' => 375, 'eta' => '2 дней', 'active' => true ),
									'slot_1'   => array( 'title' => '', 'price' => 0, 'eta' => '', 'active' => false ),
									'slot_2'   => array( 'title' => '', 'price' => 0, 'eta' => '', 'active' => false ),
								),
							),
							'pvz' => array(
								'title'               => 'Доставка до ПВЗ',
								'price'               => 185,
								'eta'                 => '2 дней',
								'description'         => 'Получение заказа в пункте выдачи.',
								'active'              => true,
								'requires_address'    => false,
								'visibility_scenarios'=> array( 'krasnoyarsk_delivery', 'other_city_delivery' ),
								'tariffs'             => array(
									'express'  => array( 'title' => 'Доставка до ПВЗ (экспресс)', 'price' => 360, 'eta' => '2 дней', 'active' => true ),
									'standard' => array( 'title' => 'Доставка до ПВЗ (стандарт)', 'price' => 185, 'eta' => '2 дней', 'active' => true ),
									'slot_1'   => array( 'title' => '', 'price' => 0, 'eta' => '', 'active' => false ),
									'slot_2'   => array( 'title' => '', 'price' => 0, 'eta' => '', 'active' => false ),
								),
							),
							'krasnoyarsk_delivery' => array(
								'title'               => 'Доставка по Красноярску',
								'price'               => 400,
								'eta'                 => 'в течение дня',
								'description'         => 'Локальная доставка по городу.',
								'active'              => true,
								'requires_address'    => true,
								'visibility_scenarios'=> array( 'krasnoyarsk_delivery' ),
							),
							'pickup' => array(
								'title'               => 'Самовывоз',
								'price'               => 0,
								'eta'                 => '',
								'description'         => 'Получение заказа в офисе самовывоза.',
								'active'              => true,
								'requires_address'    => false,
								'visibility_scenarios'=> array( 'pickup', 'krasnoyarsk_delivery', 'other_city_delivery' ),
							),
							'custom_1' => array(
								'title'               => 'Пользовательский метод 1',
								'price'               => 0,
								'eta'                 => '',
								'description'         => '',
								'active'              => false,
								'requires_address'    => false,
								'visibility_scenarios'=> array( 'pickup', 'krasnoyarsk_delivery', 'other_city_delivery' ),
							),
							'custom_2' => array(
								'title'               => 'Пользовательский метод 2',
								'price'               => 0,
								'eta'                 => '',
								'description'         => '',
								'active'              => false,
								'requires_address'    => false,
								'visibility_scenarios'=> array( 'pickup', 'krasnoyarsk_delivery', 'other_city_delivery' ),
							),
						),
						'error_copy' => array(
							'method_unavailable' => 'Выбранный метод доставки недоступен. Выберите другой вариант.',
							'tariff_unavailable' => 'Выбранный тариф недоступен. Выберите другой тариф.',
						),
						'bulk_update' => array(
							'enabled'           => true,
							'seasonal_delta_pct'=> 0,
							'seasonal_delta_abs'=> 0,
							'eta_suffix'        => '',
						),
						'preview' => array(
							'enabled'           => true,
							'mock_subtotal'     => 3670,
							'mock_discount'     => 200,
							'mock_tax'          => 160,
						),
					),
				);
				continue;
			}
			if ( OptionKeys::SECTION_PICKUP === $section_key ) {
				$tree[ $section_key ] = array(
					'enable_point_selection' => false,
					'map_slot_enabled'       => true,
					'map_widget'             => array(
						'enabled'            => true,
						'provider'           => 'yandex',
						'api_key'            => '',
						'center_lat'         => 56.010563,
						'center_lng'         => 92.852572,
						'zoom'               => 14,
						'marker_label'       => 'Пункт самовывоза',
						'marker_hint'        => 'Заберите заказ в рабочие часы.',
						'fallback_title'     => 'Карта временно недоступна',
						'fallback_message'   => 'Посмотрите адрес пункта самовывоза выше и постройте маршрут в приложении карт.',
						'desktop_height'     => 250,
						'mobile_height'      => 190,
						'diagnostics_enabled'=> true,
					),
					'points'                 => array(
						array(
							'id'          => 'pickup_main',
							'title'       => 'Основная точка самовывоза',
							'address'     => 'г. Красноярск, ул. Маерчака, д. 10, оф. 17-13',
							'description' => 'Ежедневно с 10:00 до 20:00',
							'map_hint'    => 'Слот карты/схемы будет подключен здесь.',
						),
					),
				);
				continue;
			}
			if ( OptionKeys::SECTION_SERVICE === $section_key ) {
				$tree[ $section_key ] = array(
					'testing_mode_toggle' => true,
					'testing_scenarios'   => array(
						'default_sandbox' => 'pickup_happy_path',
						'step_scenarios'  => array(
							'step_1' => 'cart_review',
							'step_2' => 'date_available',
							'step_3' => 'conditions_pickup',
							'step_4' => 'payment_success',
						),
					),
					'test_utilities'      => array(
						'fulfillment' => array(
							'pickup'               => true,
							'krasnoyarsk_delivery' => true,
							'other_city_delivery'  => true,
						),
						'discounts'   => array(
							'coupon_success'    => true,
							'coupon_rejected'   => true,
							'gift_card_success' => true,
							'gift_card_rejected'=> true,
						),
						'validation_payment' => array(
							'show_validation_errors' => true,
							'show_payment_loading'   => true,
							'show_payment_error'     => true,
							'show_payment_success'   => true,
						),
					),
					'health_checks'       => array(
						'enabled' => true,
					),
				);
				continue;
			}
			if ( OptionKeys::SECTION_PAYMENT === $section_key ) {
				$tree[ $section_key ] = array(
					'admin_note' => __( 'Способы оплаты и их доступность настраиваются в WooCommerce → Настройки → Платежи. Здесь — подсказки и текстовые акценты для блока оплаты.', 'mp-custom-checkout' ),
					'place_order' => array(
						'confirm_copy' => __( 'Проверьте данные и подтвердите оплату.', 'mp-custom-checkout' ),
					),
				);
				continue;
			}
			if ( OptionKeys::SECTION_STYLES === $section_key ) {
				$tree[ $section_key ] = array(
					'layout' => array(
						'global_density' => 'comfortable',
						'sidebar_behavior' => 'sticky',
					),
					'note' => __( 'Глобальные CSS-переменные задаются в служебном разделе (design_tokens). Стили шагов — в настройках шагов 1–4.', 'mp-custom-checkout' ),
				);
				continue;
			}
			if ( OptionKeys::SECTION_FIELDS === $section_key ) {
				$tree[ $section_key ] = array(
					'registry_overrides' => array(
						'enabled' => false,
					),
					'note' => __( 'Основная конфигурация полей находится на шаге 4. Здесь можно зафиксировать точечные переопределения для сущностных блоков.', 'mp-custom-checkout' ),
				);
				continue;
			}
			if ( OptionKeys::SECTION_DELIVERY === $section_key ) {
				$tree[ $section_key ] = array(
					'wc_integration' => array(
						'respect_chosen_shipping_methods' => true,
					),
					'note' => __( 'Сценарии доставки (город / другой регион) задаются в реестре шагов. Тарифы WooCommerce — в зонах доставки.', 'mp-custom-checkout' ),
				);
				continue;
			}
			if ( OptionKeys::SECTION_GIFT_CARD === $section_key ) {
				$tree[ $section_key ] = array(
					'integration' => array(
						'respect_pw_session' => true,
					),
					'note' => __( 'Тексты и лейблы блока подарочной карты настраиваются на шаге 4 (контакты и оплата).', 'mp-custom-checkout' ),
				);
				continue;
			}
			if ( OptionKeys::SECTION_COUPONS === $section_key ) {
				$tree[ $section_key ] = array(
					'behavior' => array(
						'allow_coupon_with_gift_card' => true,
					),
					'note' => __( 'Поведение купонов также зависит от настроек WooCommerce и совместимости с подарочными картами.', 'mp-custom-checkout' ),
				);
				continue;
			}
			if ( OptionKeys::SECTION_PREVIEW === $section_key ) {
				$tree[ $section_key ] = array(
					'admin' => array(
						'show_live_preview' => true,
					),
					'note' => __( 'Параметры превью в админке. Проверяйте сценарии после изменения реестра шагов.', 'mp-custom-checkout' ),
				);
				continue;
			}
			if ( OptionKeys::SECTION_LOGS === $section_key ) {
				$tree[ $section_key ] = array(
					'export_enabled'        => true,
					'clear_enabled'         => true,
					'critical_only_default' => false,
					'max_records_ui'        => 200,
				);
				continue;
			}
			if ( OptionKeys::SECTION_MOTION === $section_key ) {
				$tree[ $section_key ] = DefaultMotionSettingsRegistry::all();
				continue;
			}

			$tree[ $section_key ] = array();
		}

		return $tree;
	}

	/**
	 * Список стран для выбора кода телефона: dial, ISO, длина национальной части (без кода страны), label (подпись в списке, обычно код ISO).
	 *
	 * @return array<int, array{dial: string, iso: string, national_digits: int, label: string}>
	 */
	public static function default_phone_country_codes(): array {
		return array(
			array(
				'dial'             => '+7',
				'iso'              => 'RU',
				'national_digits'  => 10,
				'label'            => 'RU',
			),
			array(
				'dial'             => '+7',
				'iso'              => 'KZ',
				'national_digits'  => 10,
				'label'            => 'KZ',
			),
			array(
				'dial'             => '+375',
				'iso'              => 'BY',
				'national_digits'  => 9,
				'label'            => 'BY',
			),
			array(
				'dial'             => '+994',
				'iso'              => 'AZ',
				'national_digits'  => 9,
				'label'            => 'AZ',
			),
			array(
				'dial'             => '+374',
				'iso'              => 'AM',
				'national_digits'  => 8,
				'label'            => 'AM',
			),
			array(
				'dial'             => '+995',
				'iso'              => 'GE',
				'national_digits'  => 9,
				'label'            => 'GE',
			),
			array(
				'dial'             => '+996',
				'iso'              => 'KG',
				'national_digits'  => 9,
				'label'            => 'KG',
			),
			array(
				'dial'             => '+992',
				'iso'              => 'TJ',
				'national_digits'  => 9,
				'label'            => 'TJ',
			),
			array(
				'dial'             => '+998',
				'iso'              => 'UZ',
				'national_digits'  => 9,
				'label'            => 'UZ',
			),
		);
	}

	/**
	 * Сброс кэша после обновления опций.
	 */
	public static function clear_cache(): void {
		self::$merged_cache = null;
	}

	/**
	 * Исторически в `coupon_block.intro` часто копировали текст подарочной карты — для промокода это неверно.
	 *
	 * @param array<string, mixed> $merged
	 * @return array<string, mixed>
	 */
	private static function normalize_merged_coupon_intro( array $merged ): array {
		if ( ! isset( $merged[ OptionKeys::SECTION_STEP_4 ] ) || ! is_array( $merged[ OptionKeys::SECTION_STEP_4 ] ) ) {
			return $merged;
		}
		$s4 = &$merged[ OptionKeys::SECTION_STEP_4 ];
		if ( ! isset( $s4['coupon_block'] ) || ! is_array( $s4['coupon_block'] ) ) {
			return $merged;
		}
		$cb = &$s4['coupon_block'];
		if ( ! isset( $cb['intro'] ) ) {
			return $merged;
		}
		$intro = trim( (string) $cb['intro'] );
		if ( '' === $intro ) {
			return $merged;
		}
		$gift_intro = '';
		if ( isset( $s4['gift_card_block'] ) && is_array( $s4['gift_card_block'] ) && isset( $s4['gift_card_block']['intro'] ) ) {
			$gift_intro = trim( (string) $s4['gift_card_block']['intro'] );
		}
		$legacy_gift_phrases = array(
			'Введите код подарочной карты.',
			'Введите код подарочной карты',
		);
		$should_fix = ( '' !== $gift_intro && $intro === $gift_intro )
			|| in_array( $intro, $legacy_gift_phrases, true )
			|| (bool) preg_match( '/подарочн(ой|ая)?\s+карт/i', $intro );
		if ( $should_fix ) {
			$cb['intro'] = 'Введите промокод.';
		}
		return $merged;
	}

	/**
	 * Слияние сохранённых настроек с дефолтами (пользователь перекрывает дефолты).
	 *
	 * @return array<string, mixed>
	 */
	public static function get_merged(): array {
		if ( null !== self::$merged_cache ) {
			return self::$merged_cache;
		}

		$stored = get_option( OptionKeys::MAIN, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		$defaults       = self::get_defaults_tree();
		self::$merged_cache = array_replace_recursive( $defaults, $stored );
		self::$merged_cache = self::normalize_merged_coupon_intro( self::$merged_cache );

		return self::$merged_cache;
	}

	/**
	 * Значение по пути через точку, например `labels.step_1.title` или `feature_flags.custom_checkout_route`.
	 *
	 * @param mixed $default Fallback, если путь не найден.
	 * @return mixed
	 */
	public static function get( string $dot_path, $default = null ) {
		$data = self::get_merged();
		$keys = explode( '.', $dot_path );
		$node = $data;

		foreach ( $keys as $key ) {
			if ( ! is_array( $node ) || ! array_key_exists( $key, $node ) ) {
				return $default;
			}
			$node = $node[ $key ];
		}

		return $node;
	}

	/**
	 * Весь раздел по ключу {@see OptionKeys::SECTION_*} или {@see OptionKeys::KEY_*}.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_section( string $section_key ): array {
		$value = self::get( $section_key, array() );
		return is_array( $value ) ? $value : array();
	}
}
