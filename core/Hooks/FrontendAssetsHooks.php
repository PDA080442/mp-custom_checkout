<?php
/**
 * Подключение публичных CSS/JS checkout.
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout\Hooks;

use MP\CustomCheckout\DependencyFailureGuard;
use MP\CustomCheckout\Routing\PickupPointRegistry;
use MP\CustomCheckout\Routing\CheckoutScenarioRules;
use MP\CustomCheckout\Settings\DefaultLabelsRegistry;
use MP\CustomCheckout\Settings\FeatureFlagResolver;
use MP\CustomCheckout\Settings\SafeSettingsResolver;
use MP\CustomCheckout\Settings\ScenarioStepRegistry;

defined( 'ABSPATH' ) || exit;

/**
 * Class FrontendAssetsHooks
 */
final class FrontendAssetsHooks {

	public const HANDLE_STYLE  = 'mp-cc-checkout-frontend';
	public const HANDLE_SCRIPT = 'mp-cc-checkout-frontend';

	/**
	 * Регистрация wp_enqueue_scripts.
	 */
	public static function register(): void {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ), 20 );
	}

	/**
	 * Подключение ассетов только на маршруте checkout и при готовности WooCommerce.
	 */
	public static function enqueue(): void {
		if ( ! CheckoutRouteHooks::is_checkout_route() ) {
			return;
		}

		if ( ! DependencyFailureGuard::is_woocommerce_integration_ready() ) {
			return;
		}

		$style_path = MP_CUSTOM_CHECKOUT_PATH . 'assets/css/checkout-frontend.css';
		$script_path = MP_CUSTOM_CHECKOUT_PATH . 'assets/js/checkout-frontend.js';
		$version    = self::asset_version( $style_path, $script_path );

		wp_enqueue_style(
			self::HANDLE_STYLE,
			MP_CUSTOM_CHECKOUT_URL . 'assets/css/checkout-frontend.css',
			array(),
			$version
		);
		wp_add_inline_style( self::HANDLE_STYLE, self::build_design_tokens_css() );

		$deps = self::script_dependencies();

		wp_enqueue_script(
			self::HANDLE_SCRIPT,
			MP_CUSTOM_CHECKOUT_URL . 'assets/js/checkout-frontend.js',
			$deps,
			$version,
			true
		);

		wp_localize_script(
			self::HANDLE_SCRIPT,
			'mpCcCheckout',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'mp_cc_checkout' ),
				'flags'   => FeatureFlagResolver::frontend_payload(),
				'runtime' => array(
					'isCheckoutRoute'   => CheckoutRouteHooks::is_checkout_route(),
					'isPlainPermalinks' => \MP\CustomCheckout\Routing\CheckoutPermalinkCompatibility::is_plain_permalinks(),
					'checkoutUrl'       => \MP\CustomCheckout\Routing\CheckoutRouteConfig::get_checkout_url(),
				),
				'endpoints' => array(
					'checkoutAction' => CheckoutAjaxHooks::ACTION,
					'entryAction'    => CheckoutEntryAjaxHooks::ACTION,
				),
				'nonces' => array(
					'checkout' => wp_create_nonce( 'mp_cc_checkout' ),
					'entry'    => wp_create_nonce( 'mp_cc_checkout_entry' ),
				),
				'uiText' => self::ui_text_dictionaries(),
				'stepOneConfig' => self::step_one_config(),
				'pickupConfig' => PickupPointRegistry::config(),
				'scenarioStepMap' => self::scenario_step_map(),
				'designTokens' => self::design_tokens_for_runtime(),
			)
		);

		do_action( 'mp_custom_checkout_enqueue_frontend_assets' );
	}

	/**
	 * @param string $style_path Абсолютный путь CSS.
	 * @param string $script_path Абсолютный путь JS.
	 */
	private static function asset_version( string $style_path, string $script_path ): string {
		$style_mtime  = is_readable( $style_path ) ? (int) filemtime( $style_path ) : 0;
		$script_mtime = is_readable( $script_path ) ? (int) filemtime( $script_path ) : 0;
		$latest       = max( $style_mtime, $script_mtime );
		if ( $latest <= 0 ) {
			return MP_CUSTOM_CHECKOUT_VERSION;
		}

		return MP_CUSTOM_CHECKOUT_VERSION . '-' . (string) $latest;
	}

	/**
	 * @return array<int, string>
	 */
	private static function script_dependencies(): array {
		$deps = array( 'jquery' );
		foreach ( array( 'wc-cart-fragments', 'wc-checkout' ) as $handle ) {
			if ( wp_script_is( $handle, 'registered' ) ) {
				$deps[] = $handle;
			}
		}

		return $deps;
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function ui_text_dictionaries(): array {
		$stored = SafeSettingsResolver::get_section( 'labels' );
		if ( empty( $stored ) ) {
			$stored = DefaultLabelsRegistry::all();
		}

		return array(
			'common'       => isset( $stored['common'] ) && is_array( $stored['common'] ) ? $stored['common'] : array(),
			'step_1'       => isset( $stored['step_1'] ) && is_array( $stored['step_1'] ) ? $stored['step_1'] : array(),
			'step_2'       => isset( $stored['step_2'] ) && is_array( $stored['step_2'] ) ? $stored['step_2'] : array(),
			'step_3'       => isset( $stored['step_3'] ) && is_array( $stored['step_3'] ) ? $stored['step_3'] : array(),
			'step_4'       => isset( $stored['step_4'] ) && is_array( $stored['step_4'] ) ? $stored['step_4'] : array(),
			'coupon'       => isset( $stored['coupon'] ) && is_array( $stored['coupon'] ) ? $stored['coupon'] : array(),
			'gift_card'    => isset( $stored['gift_card'] ) && is_array( $stored['gift_card'] ) ? $stored['gift_card'] : array(),
			'order_review' => isset( $stored['order_review'] ) && is_array( $stored['order_review'] ) ? $stored['order_review'] : array(),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function scenario_step_map(): array {
		$registry = SafeSettingsResolver::get_section( 'registry' );
		$scenarios = isset( $registry['scenarios'] ) && is_array( $registry['scenarios'] )
			? $registry['scenarios']
			: ScenarioStepRegistry::scenarios();
		$step_order = isset( $registry['step_order'] ) && is_array( $registry['step_order'] )
			? $registry['step_order']
			: ScenarioStepRegistry::default_step_order();
		$step_definitions = isset( $registry['step_definitions'] ) && is_array( $registry['step_definitions'] )
			? $registry['step_definitions']
			: ScenarioStepRegistry::step_definitions();

		$rules = array();
		foreach ( array_keys( $scenarios ) as $scenario_id ) {
			$rules[ $scenario_id ] = CheckoutScenarioRules::build( (string) $scenario_id );
		}

		return array(
			'scenarios'        => $scenarios,
			'stepOrder'        => $step_order,
			'stepDefinitions'  => $step_definitions,
			'scenarioRules'    => $rules,
		);
	}

	/**
	 * @return array<string, string>
	 */
	private static function design_tokens_for_runtime(): array {
		$tokens = SafeSettingsResolver::get_section( 'design_tokens' );
		if ( empty( $tokens ) ) {
			$tokens = array();
		}

		$result = array();
		foreach ( $tokens as $key => $value ) {
			if ( ! is_string( $key ) || '' === $key ) {
				continue;
			}
			$token_key = sanitize_key( $key );
			if ( '' === $token_key ) {
				continue;
			}
			$result[ $token_key ] = is_scalar( $value ) ? self::sanitize_css_token_value( (string) $value ) : '';
		}

		return $result;
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function step_one_config(): array {
		$config = SafeSettingsResolver::get_section( 'step_1' );
		return is_array( $config ) ? $config : array();
	}

	private static function build_design_tokens_css(): string {
		$tokens = self::design_tokens_for_runtime();
		if ( empty( $tokens ) ) {
			return '';
		}

		$lines = array();
		foreach ( $tokens as $token => $value ) {
			if ( '' === $value ) {
				continue;
			}
			$lines[] = '--mp-cc-' . $token . ': ' . $value . ';';
		}

		return ':root{' . implode( '', $lines ) . '}';
	}

	private static function sanitize_css_token_value( string $value ): string {
		$value = trim( wp_strip_all_tags( $value ) );
		// Убираем управляющие символы и потенциальные разделители деклараций.
		$value = str_replace( array( ';', '{', '}', "\n", "\r", "\t" ), '', $value );
		return $value;
	}
}
