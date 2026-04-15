<?php
/**
 * Plugin Name:       MP Custom Checkout
 * Plugin URI:        https://example.com/mp-custom-checkout
 * Description:       Кастомный многошаговый checkout для WooCommerce.
 * Version:           0.1.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Metaphysics Parfum
 * Text Domain:       mp-custom-checkout
 * Domain Path:       /languages
 *
 * @package MP_Custom_Checkout
 */

defined( 'ABSPATH' ) || exit;

define( 'MP_CUSTOM_CHECKOUT_VERSION', '0.1.0' );
define( 'MP_CUSTOM_CHECKOUT_FILE', __FILE__ );
define( 'MP_CUSTOM_CHECKOUT_PATH', plugin_dir_path( __FILE__ ) );
define( 'MP_CUSTOM_CHECKOUT_URL', plugin_dir_url( __FILE__ ) );
define( 'MP_CUSTOM_CHECKOUT_BASENAME', plugin_basename( __FILE__ ) );
define( 'MP_CUSTOM_CHECKOUT_TEXT_DOMAIN', 'mp-custom-checkout' );

require_once MP_CUSTOM_CHECKOUT_PATH . 'core/Autoloader.php';

\MP\CustomCheckout\Autoloader::register();

register_activation_hook( MP_CUSTOM_CHECKOUT_FILE, array( \MP\CustomCheckout\Activator::class, 'activate' ) );
register_deactivation_hook( MP_CUSTOM_CHECKOUT_FILE, array( \MP\CustomCheckout\Deactivator::class, 'deactivate' ) );

\MP\CustomCheckout\Plugin::instance()->boot();
