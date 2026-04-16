<?php
/**
 * Внутренний PSR-4-совместимый автозагрузчик классов плагина.
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout;

defined( 'ABSPATH' ) || exit;

/**
 * Class Autoloader
 */
final class Autoloader {

	/**
	 * Соответствие префиксов пространства имён каталогам (порядок: от более длинного префикса к базовому).
	 *
	 * @var array<string, string>
	 */
	private static function get_prefix_map(): array {
		return array(
			'MP\\CustomCheckout\\Admin\\'       => MP_CUSTOM_CHECKOUT_PATH . 'admin/',
			'MP\\CustomCheckout\\Frontend\\'    => MP_CUSTOM_CHECKOUT_PATH . 'frontend/',
			'MP\\CustomCheckout\\Checkout\\'    => MP_CUSTOM_CHECKOUT_PATH . 'core/Checkout/',
			'MP\\CustomCheckout\\Core\\'        => MP_CUSTOM_CHECKOUT_PATH . 'core/',
			'MP\\CustomCheckout\\Integrations\\' => MP_CUSTOM_CHECKOUT_PATH . 'integrations/',
			'MP\\CustomCheckout\\'              => MP_CUSTOM_CHECKOUT_PATH . 'core/',
		);
	}

	/**
	 * Регистрирует автозагрузчик.
	 */
	public static function register(): void {
		spl_autoload_register( array( self::class, 'autoload' ) );
	}

	/**
	 * Загружает класс по соглашению PSR-4.
	 *
	 * @param string $class Полное имя класса.
	 */
	public static function autoload( string $class ): void {
		foreach ( self::get_prefix_map() as $prefix => $base_dir ) {
			if ( strpos( $class, $prefix ) !== 0 ) {
				continue;
			}

			$relative = substr( $class, strlen( $prefix ) );
			$relative = str_replace( '\\', DIRECTORY_SEPARATOR, $relative );
			$file     = $base_dir . $relative . '.php';

			if ( is_readable( $file ) ) {
				require_once $file;
			}

			return;
		}
	}
}
