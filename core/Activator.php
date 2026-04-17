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
	 * Флаг: нужно выполнить add_rewrite_rule + flush на первом обычном запросе после активации.
	 * В callback активации не трогаем rewrite — на части хостингов это даёт фатал/нестабильность.
	 */
	public const OPTION_NEEDS_REWRITE_FLUSH = 'mp_cc_needs_rewrite_flush';

	/**
	 * Запускается при активации плагина.
	 */
	public static function activate(): void {
		if ( ! defined( 'MP_CUSTOM_CHECKOUT_VERSION' ) ) {
			return;
		}

		add_option( OptionKeys::MAIN, array(), '', false );
		add_option( OptionKeys::DB_VERSION, '0', '', false );
		update_option( self::OPTION_NEEDS_REWRITE_FLUSH, 1, false );
	}
}
