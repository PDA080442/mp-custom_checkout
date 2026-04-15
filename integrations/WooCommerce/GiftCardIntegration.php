<?php
/**
 * Интеграция с плагином PW Gift Cards (Pimwick Plugins).
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout\Integrations\WooCommerce;

defined( 'ABSPATH' ) || exit;

/**
 * Class GiftCardIntegration
 */
final class GiftCardIntegration {

	/**
	 * Основной класс плагина PW Gift Cards (если используется стандартная сборка).
	 */
	private const PW_GIFT_CARDS_CLASS = 'PW_Gift_Cards';

	/**
	 * Плагин PW Gift Cards активен и класс доступен.
	 */
	public function is_pw_gift_cards_available(): bool {
		return class_exists( self::PW_GIFT_CARDS_CLASS, false );
	}
}
