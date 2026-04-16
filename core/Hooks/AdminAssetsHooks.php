<?php
/**
 * Подключение CSS/JS в админке настроек плагина.
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout\Hooks;

defined( 'ABSPATH' ) || exit;

/**
 * Class AdminAssetsHooks
 */
final class AdminAssetsHooks {
	public static function register(): void {
		\MP\CustomCheckout\Admin\Hooks\AdminAssetsHooks::register();
	}
}
