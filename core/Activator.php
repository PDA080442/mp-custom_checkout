<?php
/**
 * Обработчик активации плагина.
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout;

use MP\CustomCheckout\Settings\OptionKeys;

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

		add_option( OptionKeys::MAIN, array(), '', false );
		add_option( OptionKeys::DB_VERSION, '0', '', false );
	}
}
