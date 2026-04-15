<?php
/**
 * Совместимость с структурой постоянных ссылок и сброс правил rewrite.
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout\Routing;

use MP\CustomCheckout\Hooks\CheckoutRouteHooks;

defined( 'ABSPATH' ) || exit;

/**
 * Class CheckoutPermalinkCompatibility
 */
final class CheckoutPermalinkCompatibility {

	/**
	 * Регистрация обработчиков смены permalink.
	 */
	public static function register(): void {
		add_action( 'updated_option', array( __CLASS__, 'on_updated_option' ), 10, 3 );
	}

	/**
	 * Plain permalinks («простые» ссылки) — без rewrite, доступ через ?mpcc_checkout=1.
	 */
	public static function is_plain_permalinks(): bool {
		$structure = (string) get_option( 'permalink_structure', '' );
		return '' === $structure;
	}

	/**
	 * Структура ЧПУ задана — rewrite-правила должны быть в .htaccess/веб-сервере.
	 */
	public static function has_pretty_permalinks(): bool {
		return ! self::is_plain_permalinks();
	}

	/**
	 * Поддерживаются ли красивые URL для кастомного маршрута (не plain).
	 */
	public static function supports_rewrite_rules(): bool {
		return self::has_pretty_permalinks();
	}

	/**
	 * После смены структуры ссылок — перерегистрация и сброс rewrite.
	 *
	 * @param mixed $old_value Предыдущее значение.
	 * @param mixed $value     Новое значение.
	 */
	public static function on_updated_option( string $option, $old_value, $value ): void {
		if ( 'permalink_structure' !== $option ) {
			return;
		}

		CheckoutRouteHooks::add_rewrite_rules();
		CheckoutSuccessRouteHooks::add_rewrite_rules();
		flush_rewrite_rules( false );
	}
}
