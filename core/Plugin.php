<?php
/**
 * Центральная точка входа плагина (загрузчик жизненного цикла).
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout;

use MP\CustomCheckout\Hooks\PluginHooksRegistrar;
use MP\CustomCheckout\Settings\SettingsMigrationManager;

defined( 'ABSPATH' ) || exit;

/**
 * Class Plugin
 */
final class Plugin {

	/**
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * @return Plugin
	 */
	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {}

	/**
	 * Подключение хуков и инициализация.
	 */
	public function boot(): void {
		DependencyFailureGuard::boot();
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ), 0 );
		add_action( 'plugins_loaded', array( SettingsMigrationManager::class, 'maybe_migrate' ), 15 );
		add_action( 'plugins_loaded', array( PluginHooksRegistrar::class, 'register' ), 25 );
	}

	/**
	 * Загрузка переводов (текстовый домен).
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain(
			MP_CUSTOM_CHECKOUT_TEXT_DOMAIN,
			false,
			dirname( MP_CUSTOM_CHECKOUT_BASENAME ) . '/languages'
		);
	}
}
