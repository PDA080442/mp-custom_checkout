<?php
/**
 * Plugin Name:       MP Custom Checkout
 * Plugin URI:        https://example.com/mp-custom-checkout
 * Description:       Кастомный многошаговый checkout для WooCommerce.
 * Version:           0.1.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Popravkin Danil
 * Text Domain:       mp-custom-checkout
 * Domain Path:       /languages
 *
 * @package MP_Custom_Checkout
 */

defined( 'ABSPATH' ) || exit;

/**
 * Ранняя проверка PHP: весь код ниже использует синтаксис/типы 7.4+.
 * Без этого на PHP ниже 7.4 загрузка Autoloader (void) даёт фатал ещё до работы плагина.
 */
if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
	add_action(
		'admin_notices',
		function () {
			if ( ! current_user_can( 'activate_plugins' ) ) {
				return;
			}
			echo '<div class="notice notice-error"><p>';
			echo esc_html(
				sprintf(
					/* translators: %s: current PHP version */
					__( 'MP Custom Checkout требует PHP 7.4 или новее. Текущая версия: %s.', 'mp-custom-checkout' ),
					PHP_VERSION
				)
			);
			echo '</p></div>';
		}
	);
	return;
}

define( 'MP_CUSTOM_CHECKOUT_VERSION', '0.1.0' );
define( 'MP_CUSTOM_CHECKOUT_FILE', __FILE__ );
define( 'MP_CUSTOM_CHECKOUT_PATH', plugin_dir_path( __FILE__ ) );
define( 'MP_CUSTOM_CHECKOUT_URL', plugin_dir_url( __FILE__ ) );
define( 'MP_CUSTOM_CHECKOUT_BASENAME', plugin_basename( __FILE__ ) );
define( 'MP_CUSTOM_CHECKOUT_TEXT_DOMAIN', 'mp-custom-checkout' );

require_once MP_CUSTOM_CHECKOUT_PATH . 'core/Autoloader.php';

\MP\CustomCheckout\Autoloader::register();
// ClassAliasRegistry — только из Plugin::boot(): при активации class_exists() для алиасов тянул бы весь фронт/админ и давал фатал в sandbox.

register_activation_hook( MP_CUSTOM_CHECKOUT_FILE, array( \MP\CustomCheckout\Activator::class, 'activate' ) );
register_deactivation_hook( MP_CUSTOM_CHECKOUT_FILE, array( \MP\CustomCheckout\Deactivator::class, 'deactivate' ) );

/**
 * Нужно ли выполнять полный boot (хуки, AJAX, админка).
 *
 * При активации WordPress сначала вызывает {@see plugin_sandbox_scrape()}: задаётся
 * {@see WP_SANDBOX_SCRAPING} и подключается файл плагина **до** записи в active_plugins.
 * Если в этом запросе вызвать весь boot, возможны фаталы; полная инициализация идёт
 * на следующем запросе, когда плагин уже в списке активных.
 *
 * @return bool
 */
function mp_cc_should_run_full_boot(): bool {
	if ( defined( 'WP_SANDBOX_SCRAPING' ) && WP_SANDBOX_SCRAPING ) {
		return false;
	}

	/**
	 * Не полагаемся на active_plugins: кэш/фильтры на отдельных хостингах давали ложные значения.
	 * Вне sandbox неактивный плагин ядро не подключает — если файл загружен, можно поднимать boot.
	 *
	 * @param bool $run По умолчанию true вне sandbox.
	 */
	return (bool) apply_filters( 'mp_custom_checkout_run_boot', true );
}

if ( mp_cc_should_run_full_boot() ) {
	\MP\CustomCheckout\Plugin::instance()->boot();
}
