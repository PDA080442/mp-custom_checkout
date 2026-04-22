<?php
/**
 * Подключение CSS/JS в админке настроек плагина.
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout\Admin\Hooks;

use MP\CustomCheckout\Settings\FeatureFlagResolver;
use MP\CustomCheckout\Settings\SafeSettingsResolver;
use MP\CustomCheckout\Routing\PickupPointRegistry;

defined( 'ABSPATH' ) || exit;

final class AdminAssetsHooks {
	public const PAGE_SLUG = 'mp-custom-checkout';
	public const HANDLE_STYLE  = 'mp-cc-admin';
	public const HANDLE_SCRIPT = 'mp-cc-admin';

	public static function register(): void {
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ), 10 );
	}

	public static function enqueue( string $hook_suffix ): void {
		if ( self::is_order_list_screen( $hook_suffix ) ) {
			wp_enqueue_style( self::HANDLE_STYLE, MP_CUSTOM_CHECKOUT_URL . 'assets/css/admin.css', array(), MP_CUSTOM_CHECKOUT_VERSION );
			return;
		}
		if ( false === strpos( $hook_suffix, self::PAGE_SLUG ) ) {
			return;
		}
		wp_enqueue_style( 'mp-cc-checkout-frontend', MP_CUSTOM_CHECKOUT_URL . 'assets/css/checkout-frontend.css', array(), MP_CUSTOM_CHECKOUT_VERSION );
		wp_enqueue_style( self::HANDLE_STYLE, MP_CUSTOM_CHECKOUT_URL . 'assets/css/admin.css', array( 'mp-cc-checkout-frontend' ), MP_CUSTOM_CHECKOUT_VERSION );
		wp_enqueue_script( self::HANDLE_SCRIPT, MP_CUSTOM_CHECKOUT_URL . 'assets/js/admin.js', array( 'jquery' ), MP_CUSTOM_CHECKOUT_VERSION, true );
		wp_localize_script(
			self::HANDLE_SCRIPT,
			'mpCcAdmin',
			array(
				'featureFlags' => FeatureFlagResolver::all(),
				'stepOneConfig' => SafeSettingsResolver::get_section( 'step_1' ),
				'stepOneDefaults' => self::step_one_defaults(),
				'settingsDefaults' => self::settings_defaults(),
				'scenarioUiConfig' => SafeSettingsResolver::get_section( 'step_2' ),
				'stepThreeConfig' => SafeSettingsResolver::get_section( 'step_3' ),
				'stepFourConfig' => SafeSettingsResolver::get_section( 'step_4' ),
				'deliveryConfig' => SafeSettingsResolver::get_section( 'delivery' ),
				'pickupConfig' => PickupPointRegistry::config(),
				'labels' => SafeSettingsResolver::get_section( 'labels' ),
			)
		);
		do_action( 'mp_custom_checkout_enqueue_admin_assets', $hook_suffix );
	}

	private static function is_order_list_screen( string $hook_suffix ): bool {
		if ( 'woocommerce_page_wc-orders' === $hook_suffix ) {
			return true;
		}
		if ( 'edit.php' !== $hook_suffix ) {
			return false;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && isset( $screen->post_type ) && 'shop_order' === $screen->post_type ) {
			return true;
		}
		return isset( $_GET['post_type'] ) && 'shop_order' === sanitize_key( wp_unslash( (string) $_GET['post_type'] ) );
	}

	private static function step_one_defaults(): array {
		$tree = SafeSettingsResolver::get_defaults_tree();
		$defaults = isset( $tree['step_1'] ) && is_array( $tree['step_1'] ) ? $tree['step_1'] : array();
		return $defaults;
	}

	private static function settings_defaults(): array {
		$tree = SafeSettingsResolver::get_defaults_tree();
		return is_array( $tree ) ? $tree : array();
	}
}
