<?php
/**
 * Обработчик деактивации плагина.
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout;

defined( 'ABSPATH' ) || exit;

/**
 * Class Deactivator
 */
final class Deactivator {

	/**
	 * Запускается при деактивации плагина.
	 */
	public static function deactivate(): void {
		if ( ! defined( 'MP_CUSTOM_CHECKOUT_VERSION' ) ) {
			return;
		}
		delete_option( \MP\CustomCheckout\Activator::OPTION_NEEDS_REWRITE_FLUSH );
	}
}
