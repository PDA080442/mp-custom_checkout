<?php
/**
 * Подключение публичных CSS/JS checkout.
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout\Hooks;

defined( 'ABSPATH' ) || exit;

/**
 * Class FrontendAssetsHooks
 */
final class FrontendAssetsHooks {
	public static function register(): void {
		\MP\CustomCheckout\Frontend\Hooks\FrontendAssetsHooks::register();
	}

	/**
	 * @param string $style_path Абсолютный путь CSS.
	 * @param string $script_path Абсолютный путь JS.
	 */
}
