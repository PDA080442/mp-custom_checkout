<?php
/**
 * Обработчик активации плагина.
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout;

defined( 'ABSPATH' ) || exit;

/**
 * Class Activator
 */
final class Activator {

	/**
	 * Запускается при активации плагина.
	 */
	public static function activate(): void {
		if ( ! defined( 'MP_CUSTOM_CHECKOUT_VERSION' ) ) {
			return;
		}
	}
}
