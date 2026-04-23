<?php
/**
 * Подключение публичных CSS/JS checkout.
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout\Frontend\Hooks;

use MP\CustomCheckout\DependencyFailureGuard;
use MP\CustomCheckout\Routing\PickupPointRegistry;
use MP\CustomCheckout\Integrations\WooCommerce\GiftCardIntegration;
use MP\CustomCheckout\Routing\CheckoutScenarioRules;
use MP\CustomCheckout\Settings\DefaultLabelsRegistry;
use MP\CustomCheckout\Settings\FeatureFlagResolver;
use MP\CustomCheckout\Settings\OptionKeys;
use MP\CustomCheckout\Settings\SafeSettingsResolver;
use MP\CustomCheckout\Settings\ScenarioStepRegistry;

defined( 'ABSPATH' ) || exit;

final class FrontendAssetsHooks {

	public const HANDLE_STYLE  = 'mp-cc-checkout-frontend';
	public const HANDLE_SCRIPT = 'mp-cc-checkout-frontend';

	public static function register(): void {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ), 20 );
	}

	public static function enqueue(): void {
		if ( ! \MP\CustomCheckout\Hooks\CheckoutRouteHooks::is_checkout_route() ) {
			return;
		}
		if ( ! DependencyFailureGuard::is_woocommerce_integration_ready() ) {
			return;
		}

		$style_path = MP_CUSTOM_CHECKOUT_PATH . 'assets/css/checkout-frontend.css';
		$script_path = MP_CUSTOM_CHECKOUT_PATH . 'assets/js/checkout-frontend.js';
		$version    = self::asset_version( $style_path, $script_path );

		self::maybe_enqueue_woocommerce_base_styles();

		wp_enqueue_style( self::HANDLE_STYLE, MP_CUSTOM_CHECKOUT_URL . 'assets/css/checkout-frontend.css', array(), $version );
		wp_add_inline_style( self::HANDLE_STYLE, self::build_design_tokens_css() );
		wp_add_inline_style( self::HANDLE_STYLE, self::build_motion_runtime_css() );

		wp_enqueue_script( self::HANDLE_SCRIPT, MP_CUSTOM_CHECKOUT_URL . 'assets/js/checkout-frontend.js', self::script_dependencies(), $version, true );
		$initial_context = isset( $GLOBALS['mp_cc_checkout_context'] ) && is_array( $GLOBALS['mp_cc_checkout_context'] )
			? $GLOBALS['mp_cc_checkout_context']
			: array();
		wp_localize_script(
			self::HANDLE_SCRIPT,
			'mpCcCheckout',
			array(
				'initialContext' => $initial_context,
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'mp_cc_checkout' ),
				'flags'   => FeatureFlagResolver::frontend_payload(),
				'runtime' => array(
					'isCheckoutRoute'   => \MP\CustomCheckout\Hooks\CheckoutRouteHooks::is_checkout_route(),
					'isPlainPermalinks' => \MP\CustomCheckout\Checkout\Routing\CheckoutPermalinkCompatibility::is_plain_permalinks(),
					'checkoutUrl'       => \MP\CustomCheckout\Routing\CheckoutRouteConfig::get_checkout_url(),
				),
				'endpoints' => array(
					'checkoutAction' => \MP\CustomCheckout\Checkout\Hooks\CheckoutAjaxHooks::ACTION,
					'entryAction'    => \MP\CustomCheckout\Checkout\Hooks\CheckoutEntryAjaxHooks::ACTION,
				),
				'nonces' => array(
					'checkout' => wp_create_nonce( 'mp_cc_checkout' ),
					'entry'    => wp_create_nonce( 'mp_cc_checkout_entry' ),
				),
				'uiText' => self::ui_text_dictionaries(),
				'stepOneConfig' => self::step_one_config(),
				'scenarioUiConfig' => self::scenario_ui_config(),
				'stepThreeConfig' => self::step_three_config(),
				'stepFourConfig'  => self::step_four_config(),
				'deliveryConfig'  => self::delivery_config(),
				'pickupConfig' => PickupPointRegistry::config(),
				'scenarioStepMap' => self::scenario_step_map(),
				'designTokens' => self::design_tokens_for_runtime(),
				'motion'       => self::motion_config_for_runtime(),
				'paymentCardArt' => self::payment_card_art_urls(),
				'giftCardIntegrationAvailable' => ( new GiftCardIntegration() )->is_pw_gift_cards_available(),
			)
		);
		do_action( 'mp_custom_checkout_enqueue_frontend_assets' );
	}

	/**
	 * Базовые стили WooCommerce (сетка/формы), чтобы тема не «ломала» типографику и отступы рядом с checkout.
	 */
	private static function maybe_enqueue_woocommerce_base_styles(): void {
		foreach ( array( 'woocommerce-general', 'woocommerce-layout', 'woocommerce-smallscreen' ) as $handle ) {
			if ( wp_style_is( $handle, 'registered' ) ) {
				wp_enqueue_style( $handle );
			}
		}
	}

	/**
	 * URL иллюстраций для визуальных карточек способов оплаты (PNG в assets/images/payment).
	 *
	 * @return array<string, string>
	 */
	private static function payment_card_art_urls(): array {
		$base = MP_CUSTOM_CHECKOUT_URL . 'assets/images/payment/';
		$dir  = MP_CUSTOM_CHECKOUT_PATH . 'assets/images/payment/';
		$map  = array(
			'bank'      => 'bank-card-generic.png',
			'generic'   => 'bank-card-generic.png',
			'robokassa' => 'robokassa-card.png',
			'yookassa'  => 'yookassa-card.png',
			'gift_card' => 'gift-card-peer.png',
		);
		$out = array();
		foreach ( $map as $key => $file ) {
			$path = $dir . $file;
			if ( is_readable( $path ) ) {
				$url = $base . $file;
				$m   = (int) filemtime( $path );
				if ( $m > 0 ) {
					$url .= '?ver=' . (string) $m;
				}
				$out[ $key ] = $url;
			} else {
				$out[ $key ] = '';
			}
		}
		return $out;
	}

	private static function asset_version( string $style_path, string $script_path ): string {
		$style_mtime  = is_readable( $style_path ) ? (int) filemtime( $style_path ) : 0;
		$script_mtime = is_readable( $script_path ) ? (int) filemtime( $script_path ) : 0;
		$latest       = max( $style_mtime, $script_mtime );
		return $latest <= 0 ? MP_CUSTOM_CHECKOUT_VERSION : MP_CUSTOM_CHECKOUT_VERSION . '-' . (string) $latest;
	}

	private static function script_dependencies(): array {
		$deps = array( 'jquery' );
		// wc-checkout ожидает нативную форму checkout на странице и может мешать SPA; фрагменты корзины оставляем для синка с темой/sticky.
		if ( wp_script_is( 'wc-cart-fragments', 'registered' ) ) {
			$deps[] = 'wc-cart-fragments';
		}
		return $deps;
	}

	private static function ui_text_dictionaries(): array {
		$stored = SafeSettingsResolver::get_section( 'labels' );
		if ( empty( $stored ) ) {
			$stored = DefaultLabelsRegistry::all();
		}
		$defaults_all = DefaultLabelsRegistry::all();
		$def_checkout = isset( $defaults_all['checkout'] ) && is_array( $defaults_all['checkout'] ) ? $defaults_all['checkout'] : array();
		$stored_checkout = isset( $stored['checkout'] ) && is_array( $stored['checkout'] ) ? $stored['checkout'] : array();
		return array(
			'common'       => isset( $stored['common'] ) && is_array( $stored['common'] ) ? $stored['common'] : array(),
			'checkout'     => array_merge( $def_checkout, $stored_checkout ),
			'step_1'       => isset( $stored['step_1'] ) && is_array( $stored['step_1'] ) ? $stored['step_1'] : array(),
			'step_2'       => isset( $stored['step_2'] ) && is_array( $stored['step_2'] ) ? $stored['step_2'] : array(),
			'step_3'       => isset( $stored['step_3'] ) && is_array( $stored['step_3'] ) ? $stored['step_3'] : array(),
			'step_4'       => isset( $stored['step_4'] ) && is_array( $stored['step_4'] ) ? $stored['step_4'] : array(),
			'coupon'       => isset( $stored['coupon'] ) && is_array( $stored['coupon'] ) ? $stored['coupon'] : array(),
			'gift_card'    => isset( $stored['gift_card'] ) && is_array( $stored['gift_card'] ) ? $stored['gift_card'] : array(),
			'order_review' => isset( $stored['order_review'] ) && is_array( $stored['order_review'] ) ? $stored['order_review'] : array(),
		);
	}

	private static function scenario_step_map(): array {
		$registry = SafeSettingsResolver::get_section( 'registry' );
		$scenarios = isset( $registry['scenarios'] ) && is_array( $registry['scenarios'] ) ? $registry['scenarios'] : ScenarioStepRegistry::scenarios();
		$step_order = isset( $registry['step_order'] ) && is_array( $registry['step_order'] ) ? $registry['step_order'] : ScenarioStepRegistry::default_step_order();
		$step_definitions = isset( $registry['step_definitions'] ) && is_array( $registry['step_definitions'] ) ? $registry['step_definitions'] : ScenarioStepRegistry::step_definitions();
		$rules = array();
		foreach ( array_keys( $scenarios ) as $scenario_id ) {
			$rules[ $scenario_id ] = CheckoutScenarioRules::build( (string) $scenario_id );
		}
		return array( 'scenarios' => $scenarios, 'stepOrder' => $step_order, 'stepDefinitions' => $step_definitions, 'scenarioRules' => $rules );
	}

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

	private static function step_one_config(): array {
		$config = SafeSettingsResolver::get_section( 'step_1' );
		return is_array( $config ) ? $config : array();
	}

	private static function scenario_ui_config(): array {
		$config = SafeSettingsResolver::get_section( 'step_2' );
		return is_array( $config ) ? $config : array();
	}

	private static function step_three_config(): array {
		$config = SafeSettingsResolver::get_section( 'step_3' );
		return is_array( $config ) ? $config : array();
	}

	private static function step_four_config(): array {
		$config = SafeSettingsResolver::get_section( 'step_4' );
		$config = is_array( $config ) ? $config : array();
		$config['available_gateways'] = self::available_payment_gateways_for_runtime();
		return $config;
	}

	private static function delivery_config(): array {
		$config = SafeSettingsResolver::get_section( 'delivery' );
		return is_array( $config ) ? $config : array();
	}

	private static function available_payment_gateways_for_runtime(): array {
		if ( ! function_exists( 'WC' ) || ! WC() ) {
			return array();
		}
		$gateways = WC()->payment_gateways();
		if ( ! $gateways instanceof \WC_Payment_Gateways ) {
			return array();
		}
		$available = $gateways->get_available_payment_gateways();
		$result    = array();
		foreach ( $available as $gateway ) {
			if ( ! $gateway instanceof \WC_Payment_Gateway ) {
				continue;
			}
			$result[] = array(
				'id'          => sanitize_key( (string) $gateway->id ),
				'title'       => wp_strip_all_tags( (string) $gateway->get_title() ),
				'description' => wp_strip_all_tags( (string) $gateway->get_description() ),
			);
		}
		return $result;
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
		$value = str_replace( array( ';', '{', '}', "\n", "\r", "\t" ), '', $value );
		return $value;
	}

	/**
	 * Motion-конфиг для checkout SPA (валидированный снимок раздела motion).
	 *
	 * @return array<string, mixed>
	 */
	private static function motion_config_for_runtime(): array {
		$raw = SafeSettingsResolver::get_section( OptionKeys::SECTION_MOTION );
		if ( empty( $raw ) || ! is_array( $raw ) ) {
			$raw = array();
		}
		$defaults = \MP\CustomCheckout\Settings\DefaultMotionSettingsRegistry::all();
		$merged   = array_replace_recursive( $defaults, $raw );

		$durations = isset( $merged['durations_ms'] ) && is_array( $merged['durations_ms'] ) ? $merged['durations_ms'] : array();
		$ease      = isset( $merged['ease'] ) && is_array( $merged['ease'] ) ? $merged['ease'] : array();
		$delay     = isset( $merged['delay_ms'] ) && is_array( $merged['delay_ms'] ) ? $merged['delay_ms'] : array();
		$throttle  = isset( $merged['throttle'] ) && is_array( $merged['throttle'] ) ? $merged['throttle'] : array();
		$toggles   = isset( $merged['toggles'] ) && is_array( $merged['toggles'] ) ? $merged['toggles'] : array();

		$san_durations = array();
		foreach ( array( 'step_transition', 'rail', 'step_screen', 'field_state', 'summary_numbers', 'skeleton_shimmer' ) as $key ) {
			$san_durations[ $key ] = self::clamp_int( isset( $durations[ $key ] ) ? (int) $durations[ $key ] : 0, 0, 4000 );
		}

		return array(
			'respect_prefers_reduced_motion' => ! empty( $merged['respect_prefers_reduced_motion'] ),
			'force_reduced_motion'           => ! empty( $merged['force_reduced_motion'] ),
			'instrumentation_enabled'        => ! empty( $merged['instrumentation_enabled'] ),
			'throttle'                       => array(
				'enabled'           => ! isset( $throttle['enabled'] ) || ! empty( $throttle['enabled'] ),
				'min_interval_ms' => self::clamp_int( isset( $throttle['min_interval_ms'] ) ? (int) $throttle['min_interval_ms'] : 120, 0, 2000 ),
			),
			'durations_ms'                   => $san_durations,
			'ease'                           => array(
				'standard'   => self::sanitize_motion_easing( isset( $ease['standard'] ) ? (string) $ease['standard'] : '' ),
				'emphasized' => self::sanitize_motion_easing( isset( $ease['emphasized'] ) ? (string) $ease['emphasized'] : '' ),
			),
			'delay_ms'                       => array(
				'summary_stagger_base' => self::clamp_int( isset( $delay['summary_stagger_base'] ) ? (int) $delay['summary_stagger_base'] : 0, 0, 2000 ),
			),
			'toggles'                        => array(
				'rail'                    => ! isset( $toggles['rail'] ) || ! empty( $toggles['rail'] ),
				'step_reveal'             => ! isset( $toggles['step_reveal'] ) || ! empty( $toggles['step_reveal'] ),
				'field_state'             => ! isset( $toggles['field_state'] ) || ! empty( $toggles['field_state'] ),
				'summary_numbers'         => ! isset( $toggles['summary_numbers'] ) || ! empty( $toggles['summary_numbers'] ),
				'step_transition_overlay' => ! isset( $toggles['step_transition_overlay'] ) || ! empty( $toggles['step_transition_overlay'] ),
			),
		);
	}

	/**
	 * CSS custom properties для #mp-cc-checkout (длительности и easing в безопасном виде).
	 */
	private static function build_motion_runtime_css(): string {
		$cfg = self::motion_config_for_runtime();
		$dur = isset( $cfg['durations_ms'] ) && is_array( $cfg['durations_ms'] ) ? $cfg['durations_ms'] : array();
		$ease = isset( $cfg['ease'] ) && is_array( $cfg['ease'] ) ? $cfg['ease'] : array();

		$ms_to_s = static function ( int $ms ): string {
			$ms = max( 0, $ms );
			return (string) round( $ms / 1000, 4 ) . 's';
		};

		$rules   = array();
		$rules[] = '--mp-cc-motion-duration-step_transition:' . $ms_to_s( (int) ( $dur['step_transition'] ?? 180 ) ) . ';';
		$rules[] = '--mp-cc-motion-duration-rail:' . $ms_to_s( (int) ( $dur['rail'] ?? 420 ) ) . ';';
		$rules[] = '--mp-cc-motion-duration-step_screen:' . $ms_to_s( (int) ( $dur['step_screen'] ?? 200 ) ) . ';';
		$rules[] = '--mp-cc-motion-duration-field:' . $ms_to_s( (int) ( $dur['field_state'] ?? 220 ) ) . ';';
		$rules[] = '--mp-cc-motion-duration-summary:' . $ms_to_s( (int) ( $dur['summary_numbers'] ?? 340 ) ) . ';';
		$rules[] = '--mp-cc-mobile-motion-duration:' . $ms_to_s( (int) ( $dur['step_screen'] ?? 200 ) ) . ';';
		$rules[] = '--mp-cc-motion-ease-standard:' . self::sanitize_motion_easing( (string) ( $ease['standard'] ?? '' ) ) . ';';
		$rules[] = '--mp-cc-motion-ease-emphasized:' . self::sanitize_motion_easing( (string) ( $ease['emphasized'] ?? '' ) ) . ';';
		$rules[] = '--mp-cc-skeleton-shimmer-duration:' . $ms_to_s( (int) ( $dur['skeleton_shimmer'] ?? 1100 ) ) . ';';

		return '#mp-cc-checkout{' . implode( '', $rules ) . '}';
	}

	/**
	 * @param int $value Value.
	 * @param int $min   Min.
	 * @param int $max   Max.
	 */
	private static function clamp_int( int $value, int $min, int $max ): int {
		if ( $value < $min ) {
			return $min;
		}
		if ( $value > $max ) {
			return $max;
		}
		return $value;
	}

	private static function sanitize_motion_easing( string $value ): string {
		$value = trim( wp_strip_all_tags( $value ) );
		$value = str_replace( array( ';', '{', '}', '"', "'", "\n", "\r", "\t" ), '', $value );
		if ( '' === $value ) {
			return 'ease';
		}
		if ( ! preg_match( '/^[a-z0-9%,.\\s()\\-]+$/i', $value ) ) {
			return 'ease';
		}
		return $value;
	}
}
