<?php
/**
 * Реестр кастомных order meta ключей MP Custom Checkout.
 *
 * @package MP_Custom_Checkout
 */
namespace MP\CustomCheckout\Checkout\Order;

defined( 'ABSPATH' ) || exit;

final class OrderMetaKeys {
	// Fulfillment / scenario.
	public const SCENARIO_ID      = '_mp_cc_scenario_id';
	public const SCENARIO_LABEL   = '_mp_cc_scenario_label';
	public const SCENARIO_PAYLOAD = '_mp_cc_scenario_payload';

	// Pickup point (used only for pickup scenario).
	public const PICKUP_POINT_ID          = '_mp_cc_pickup_point_id';
	public const PICKUP_POINT_TITLE       = '_mp_cc_pickup_point_title';
	public const PICKUP_POINT_ADDRESS     = '_mp_cc_pickup_point_address';
	public const PICKUP_POINT_DESCRIPTION = '_mp_cc_pickup_point_description';
	public const PICKUP_POINT_PAYLOAD     = '_mp_cc_pickup_point_payload';

	// CDEK PVZ (official_cdek rate): snapshot from MP flow for QA / reporting — не дублирует meta самой ставки на shipping item.
	public const CDEK_OFFICE_CODE = '_mp_cc_cdek_office_code';
	public const CDEK_RATE_ID     = '_mp_cc_cdek_rate_id';

	// Selected fulfillment date.
	public const SELECTED_DATE       = '_mp_cc_selected_date';
	public const SELECTED_DATE_LABEL = '_mp_cc_selected_date_label';

	// Conditions confirmation + receipt.
	public const CONDITIONS_CONFIRMED    = '_mp_cc_conditions_confirmed';
	public const CONDITIONS_CONFIRMED_AT = '_mp_cc_conditions_confirmed_at';
	public const CONDITIONS_SUMMARY      = '_mp_cc_conditions_summary';

	// Personal / contact extras.
	public const BILLING_PATRONYMIC      = '_mp_cc_billing_patronymic';
	public const BILLING_BIRTHDATE       = '_mp_cc_billing_birthdate';
	public const GENDER                 = '_mp_cc_gender';
	public const PHONE_COUNTRY_ISO      = '_mp_cc_phone_country_iso';
	public const PHONE_DIAL_CODE        = '_mp_cc_phone_dial_code';
	public const BILLING_PHONE_LOCAL    = '_mp_cc_billing_phone_local';
	public const ORDER_NOTES            = '_mp_cc_order_notes';

	// Structured address.
	public const ADDRESS_COUNTRY_CODE = '_mp_cc_address_country_code';
	public const ADDRESS_REGION_CODE  = '_mp_cc_address_region_code';
	public const ADDRESS_CITY         = '_mp_cc_address_city';
	public const ADDRESS_LINE1        = '_mp_cc_address_line1';
	public const ADDRESS_LINE2        = '_mp_cc_address_line2';
	public const ADDRESS_POSTCODE     = '_mp_cc_address_postcode';

	// Discounts snapshot.
	public const APPLIED_COUPONS          = '_mp_cc_applied_coupons';
	public const APPLIED_GIFT_CARDS       = '_mp_cc_applied_gift_cards';
	public const COUPON_DISCOUNT_TOTAL    = '_mp_cc_coupon_discount_total';
	public const GIFT_CARD_TOTAL          = '_mp_cc_gift_card_total';

	/**
	 * Список всех ключей для удобной диагностики / аудита.
	 *
	 * @return string[]
	 */
	public static function all(): array {
		return array(
			self::SCENARIO_ID,
			self::SCENARIO_LABEL,
			self::SCENARIO_PAYLOAD,
			self::PICKUP_POINT_ID,
			self::PICKUP_POINT_TITLE,
			self::PICKUP_POINT_ADDRESS,
			self::PICKUP_POINT_DESCRIPTION,
			self::PICKUP_POINT_PAYLOAD,
			self::CDEK_OFFICE_CODE,
			self::CDEK_RATE_ID,
			self::SELECTED_DATE,
			self::SELECTED_DATE_LABEL,
			self::CONDITIONS_CONFIRMED,
			self::CONDITIONS_CONFIRMED_AT,
			self::CONDITIONS_SUMMARY,
			self::BILLING_PATRONYMIC,
			self::BILLING_BIRTHDATE,
			self::GENDER,
			self::PHONE_COUNTRY_ISO,
			self::PHONE_DIAL_CODE,
			self::BILLING_PHONE_LOCAL,
			self::ORDER_NOTES,
			self::ADDRESS_COUNTRY_CODE,
			self::ADDRESS_REGION_CODE,
			self::ADDRESS_CITY,
			self::ADDRESS_LINE1,
			self::ADDRESS_LINE2,
			self::ADDRESS_POSTCODE,
			self::APPLIED_COUPONS,
			self::APPLIED_GIFT_CARDS,
			self::COUPON_DISCOUNT_TOTAL,
			self::GIFT_CARD_TOTAL,
		);
	}
}

