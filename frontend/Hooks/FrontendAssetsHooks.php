<?php
/**
 * Подключение публичных CSS/JS checkout.
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout\Frontend\Hooks;

use MP\CustomCheckout\DependencyFailureGuard;
use MP\CustomCheckout\Hooks\CheckoutRouteHooks;
use MP\CustomCheckout\Routing\PickupPointRegistry;
use MP\CustomCheckout\Integrations\WooCommerce\CdekMpCheckoutWidgetConfig;
use MP\CustomCheckout\Integrations\WooCommerce\GiftCardIntegration;
use MP\CustomCheckout\Routing\CheckoutScenarioRules;
use MP\CustomCheckout\Settings\DefaultLabelsRegistry;
use MP\CustomCheckout\Settings\FeatureFlagResolver;
use MP\CustomCheckout\Settings\MotionSettingsResolver;
use MP\CustomCheckout\Settings\OptionKeys;
use MP\CustomCheckout\Settings\SafeSettingsResolver;
use MP\CustomCheckout\Settings\ScenarioStepRegistry;

defined( 'ABSPATH' ) || exit;

final class FrontendAssetsHooks {

	public const HANDLE_STYLE  = 'mp-cc-checkout-frontend';
	public const HANDLE_SCRIPT = 'mp-cc-checkout-frontend';
	public const HANDLE_CDEK_BRIDGE = 'mp-cc-cdek-widget-bridge';

	public static function register(): void {
		// Раньше основного enqueue: без этого при отложенном CSS (оптимизаторы темы / RUCSS)
		// переменные из inline handle не успевают — #mp-cc-checkout наследует body (часто тёмная тема)
		// и даёт «белый на белом», заголовок на чёрном фоне, сломанный stacked-timeline до загрузки файла.
		add_action( 'wp_head', array( __CLASS__, 'print_critical_checkout_shell_css' ), 1 );
		// Позже типичных плагинов (DaData и т.д.), чтобы jquery.suggestions успел зарегистрироваться как зависимость.
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ), 50 );
	}

	/**
	 * Минимальные стили и дизайн-токены в &lt;head&gt; до отложенных стилей темы/плагинов оптимизации.
	 */
	public static function print_critical_checkout_shell_css(): void {
		if ( ! CheckoutRouteHooks::is_checkout_route() ) {
			return;
		}
		if ( ! DependencyFailureGuard::is_woocommerce_integration_ready() ) {
			return;
		}
		$css = self::build_critical_checkout_shell_css();
		if ( '' === $css ) {
			return;
		}
		echo '<style id="mp-cc-checkout-critical-shell">' . esc_html( $css ) . '</style>' . "\n";
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
		wp_add_inline_style( self::HANDLE_STYLE, self::build_progress_step_index_css() );
		wp_add_inline_style( self::HANDLE_STYLE, self::build_checkout_layout_css() );
		wp_add_inline_style( self::HANDLE_STYLE, self::build_motion_runtime_css() );

		$mp_ctx          = isset( $GLOBALS['mp_cc_checkout_context'] ) && is_array( $GLOBALS['mp_cc_checkout_context'] ) ? $GLOBALS['mp_cc_checkout_context'] : array();
		$checkout_flow   = isset( $mp_ctx['checkout_flow'] ) && is_array( $mp_ctx['checkout_flow'] ) ? $mp_ctx['checkout_flow'] : null;
		$cdek_widget_cfg = CdekMpCheckoutWidgetConfig::build_for_frontend( $checkout_flow );

		self::maybe_register_cdek_widget_bundle( $cdek_widget_cfg );

		$script_deps = self::script_dependencies();
		if ( wp_script_is( 'cdek-widget', 'registered' ) && ! empty( $cdek_widget_cfg['map_ready'] ) ) {
			wp_enqueue_script( 'cdek-widget' );
			$script_deps[] = 'cdek-widget';
		}

		wp_enqueue_script( self::HANDLE_SCRIPT, MP_CUSTOM_CHECKOUT_URL . 'assets/js/checkout-frontend.js', array_values( array_unique( $script_deps ) ), $version, true );
		$initial_context = isset( $GLOBALS['mp_cc_checkout_context'] ) && is_array( $GLOBALS['mp_cc_checkout_context'] )
			? $GLOBALS['mp_cc_checkout_context']
			: array();
		wp_localize_script(
			self::HANDLE_SCRIPT,
			'mpCcCdekWidget',
			$cdek_widget_cfg
		);
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
				'dadata'                       => self::dadata_runtime_settings(),
			)
		);

		$bridge_path = MP_CUSTOM_CHECKOUT_PATH . 'assets/js/cdek-widget-bridge.js';
		if ( is_readable( $bridge_path ) ) {
			$bridge_ver = (string) max( (int) filemtime( $bridge_path ), (int) filemtime( $script_path ) );
			$bridge_deps = array( 'jquery', self::HANDLE_SCRIPT );
			if ( wp_script_is( 'cdek-widget', 'registered' ) && ! empty( $cdek_widget_cfg['map_ready'] ) ) {
				$bridge_deps[] = 'cdek-widget';
			}
			wp_enqueue_script(
				self::HANDLE_CDEK_BRIDGE,
				MP_CUSTOM_CHECKOUT_URL . 'assets/js/cdek-widget-bridge.js',
				array_values( array_unique( $bridge_deps ) ),
				MP_CUSTOM_CHECKOUT_VERSION . '-' . $bridge_ver,
				true
			);
		}
		do_action( 'mp_custom_checkout_enqueue_frontend_assets' );
	}

	/**
	 * Регистрирует UMD CDEK Widget 3.x из официального плагина (handle `cdek-widget`, глобал `window.cdek` через wp_localize).
	 *
	 * @param array<string, mixed> $cdek_widget_cfg
	 */
	private static function maybe_register_cdek_widget_bundle( array $cdek_widget_cfg ): void {
		if ( empty( $cdek_widget_cfg['map_ready'] ) ) {
			return;
		}
		if ( class_exists( '\Cdek\UI\CdekWidget', false ) ) {
			\Cdek\UI\CdekWidget::registerScripts();
			return;
		}
		if ( ! class_exists( '\Cdek\Loader', false ) ) {
			return;
		}
		$rel = 'build/cdek-widget.umd.js';
		$abs = \Cdek\Loader::getPluginPath( $rel );
		if ( ! is_readable( $abs ) ) {
			return;
		}
		$url = \Cdek\Loader::getPluginUrl( $rel );
		if ( ! is_string( $url ) || '' === $url ) {
			return;
		}
		$mtime = is_readable( $abs ) ? (int) filemtime( $abs ) : 0;
		wp_register_script(
			'cdek-widget',
			esc_url_raw( $url ),
			array(),
			$mtime > 0 ? (string) $mtime : false,
			true
		);
		if ( function_exists( 'WC' ) && class_exists( '\Cdek\ShippingMethod', false ) && class_exists( '\WC_AJAX', false ) && class_exists( '\Cdek\Config', false ) ) {
			try {
				$shipping = \Cdek\ShippingMethod::factory();
			} catch ( \Throwable $e ) {
				return;
			}
			$locale = function_exists( 'get_user_locale' ) ? (string) get_user_locale() : '';
			$lang   = ( 0 === mb_strpos( $locale, 'en' ) ) ? 'eng' : 'rus';
			wp_localize_script(
				'cdek-widget',
				'cdek',
				array(
					'key'   => isset( $shipping->yandex_map_api_key ) ? (string) $shipping->yandex_map_api_key : '',
					'close' => ! empty( $shipping->map_auto_close ),
					'lang'  => $lang,
					'saver' => \WC_AJAX::get_endpoint( \Cdek\Config::DELIVERY_NAME . '_save-office' ),
				)
			);
		}
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
			'robokassa' => 'robokassa-card_without_bg.png',
			'yookassa'  => 'yookassa-card-no-bg-preview.png',
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
		// Плагин «Подсказки» от DaData.ru и аналоги: если handle зарегистрирован — грузим после него (jquery.suggestions).
		foreach ( self::dadata_script_handle_candidates() as $handle ) {
			if ( wp_script_is( $handle, 'registered' ) || wp_script_is( $handle, 'queued' ) ) {
				$deps[] = $handle;
			}
		}
		return array_values( array_unique( $deps ) );
	}

	/**
	 * Распространённые handle'ы фронта DaData (зависят от версии плагина).
	 *
	 * @return array<int, string>
	 */
	private static function dadata_script_handle_candidates(): array {
		return array(
			'dadata-ru',
			'dadata_ru',
			'dadata-main',
			'dadata-frontend',
			'jquery-suggestions',
			'suggestions',
		);
	}

	/**
	 * Токен/секрет для подсказок DaData на кастомном checkout.
	 *
	 * Сначала срабатывает фильтр {@see 'mp_custom_checkout_dadata_settings'} (массив с ключами token, secret?, enabled?).
	 * Иначе пробуем типичные option из настроек «Общие → DaData» и смежных плагинов.
	 *
	 * @return array{enabled:bool,token:string,secret:string}
	 */
	private static function dadata_runtime_settings(): array {
		$empty = array(
			'enabled' => false,
			'token'   => '',
			'secret'  => '',
		);
		$filtered = apply_filters( 'mp_custom_checkout_dadata_settings', null );
		if ( is_array( $filtered ) && isset( $filtered['token'] ) && is_string( $filtered['token'] ) && '' !== trim( $filtered['token'] ) ) {
			return array(
				'enabled' => ! isset( $filtered['enabled'] ) ? true : (bool) $filtered['enabled'],
				'token'   => trim( (string) $filtered['token'] ),
				'secret'  => isset( $filtered['secret'] ) && is_string( $filtered['secret'] ) ? trim( (string) $filtered['secret'] ) : '',
			);
		}
		$token  = '';
		$secret = '';
		foreach ( array( 'dadata_api_token', 'dadata_api_key', 'dadata_token', 'dadata_secret_token' ) as $opt_key ) {
			$v = get_option( $opt_key, '' );
			if ( is_string( $v ) && '' !== trim( $v ) ) {
				$token = trim( $v );
				break;
			}
		}
		if ( '' === $token ) {
			$nested = get_option( 'dadata_general_options', array() );
			if ( is_array( $nested ) && isset( $nested['token'] ) && is_string( $nested['token'] ) && '' !== trim( $nested['token'] ) ) {
				$token = trim( $nested['token'] );
			}
		}
		foreach ( array( 'dadata_secret_key', 'dadata_api_secret' ) as $sk ) {
			$v = get_option( $sk, '' );
			if ( is_string( $v ) && '' !== trim( $v ) ) {
				$secret = trim( $v );
				break;
			}
		}
		if ( '' === $token ) {
			return $empty;
		}
		return array(
			'enabled' => true,
			'token'   => $token,
			'secret'  => $secret,
		);
	}

	private static function ui_text_dictionaries(): array {
		$stored = SafeSettingsResolver::get_section( 'labels' );
		if ( empty( $stored ) ) {
			$stored = DefaultLabelsRegistry::all();
		}
		$defaults_all = DefaultLabelsRegistry::all();
		$def_checkout = isset( $defaults_all['checkout'] ) && is_array( $defaults_all['checkout'] ) ? $defaults_all['checkout'] : array();
		$stored_checkout = isset( $stored['checkout'] ) && is_array( $stored['checkout'] ) ? $stored['checkout'] : array();
		$def_delivery = isset( $defaults_all['delivery'] ) && is_array( $defaults_all['delivery'] ) ? $defaults_all['delivery'] : array();
		$stored_delivery = isset( $stored['delivery'] ) && is_array( $stored['delivery'] ) ? $stored['delivery'] : array();
		return array(
			'common'       => isset( $stored['common'] ) && is_array( $stored['common'] ) ? $stored['common'] : array(),
			'checkout'     => array_merge( $def_checkout, $stored_checkout ),
			'step_1'       => isset( $stored['step_1'] ) && is_array( $stored['step_1'] ) ? $stored['step_1'] : array(),
			'step_2'       => isset( $stored['step_2'] ) && is_array( $stored['step_2'] ) ? $stored['step_2'] : array(),
			'step_3'       => isset( $stored['step_3'] ) && is_array( $stored['step_3'] ) ? $stored['step_3'] : array(),
			'step_4'       => isset( $stored['step_4'] ) && is_array( $stored['step_4'] ) ? $stored['step_4'] : array(),
			'delivery'     => array_merge( $def_delivery, $stored_delivery ),
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
		if ( ! isset( $config['payment_block'] ) || ! is_array( $config['payment_block'] ) ) {
			$config['payment_block'] = array();
		}
		$config['payment_block']['discount_toggles'] = self::discount_toggles_runtime_config();
		return $config;
	}

	/**
	 * Готовит рантайм-конфиг тогглов промокода и подарочной карты на шаге оплаты.
	 *
	 * @return array<string, mixed>
	 */
	private static function discount_toggles_runtime_config(): array {
		$step_four = SafeSettingsResolver::get_section( 'step_4' );
		$payment_block = is_array( $step_four ) && isset( $step_four['payment_block'] ) && is_array( $step_four['payment_block'] )
			? $step_four['payment_block']
			: array();
		$raw = isset( $payment_block['discount_toggles'] ) && is_array( $payment_block['discount_toggles'] )
			? $payment_block['discount_toggles']
			: array();
		return array(
			'coupon_in_step'      => isset( $raw['coupon_in_step'] ) ? (bool) $raw['coupon_in_step'] : true,
			'gift_card_in_step'   => isset( $raw['gift_card_in_step'] ) ? (bool) $raw['gift_card_in_step'] : true,
			'coupon_in_summary'   => isset( $raw['coupon_in_summary'] ) ? (bool) $raw['coupon_in_summary'] : false,
			'gift_card_in_summary'=> isset( $raw['gift_card_in_summary'] ) ? (bool) $raw['gift_card_in_summary'] : false,
			'coupon_icon_url'     => '',
			'gift_card_icon_url'  => '',
		);
	}

	private static function delivery_config(): array {
		$config = SafeSettingsResolver::get_section( 'delivery' );
		return is_array( $config ) ? $config : array();
	}

	/**
	 * URL статики оплаты из каталога плагина (`assets/images/payment-defaults/`).
	 */
	private static function payment_default_asset_url( string $filename ): string {
		$filename = ltrim( $filename, '/' );
		$abs      = MP_CUSTOM_CHECKOUT_PATH . 'assets/images/payment-defaults/' . $filename;
		$ver      = ( is_readable( $abs ) ) ? (string) filemtime( $abs ) : MP_CUSTOM_CHECKOUT_VERSION;
		$base     = trailingslashit( (string) MP_CUSTOM_CHECKOUT_URL ) . 'assets/images/payment-defaults/' . $filename;
		return esc_url_raw( $base . '?v=' . rawurlencode( $ver ) );
	}

	/**
	 * Иконка способа оплаты: фиксированные PNG по подписи и id шлюза.
	 */
	private static function default_payment_gateway_icon_url( string $gateway_id, string $display_title ): string {
		$id    = strtolower( $gateway_id );
		$plain = wp_strip_all_tags( $display_title );
		if ( function_exists( 'mb_strtolower' ) ) {
			$title = mb_strtolower( $plain, 'UTF-8' );
		} else {
			$title = strtolower( $plain );
		}

		// По id шлюза — раньше эвристик по заголовку: в подписи часто есть «картой»,
		// из‑за чего ветка «карт» перехватывала ЮKassa раньше, чем срабатывала привязка к sbp.png.
		if ( false !== strpos( $id, 'robokassa' ) ) {
			return self::payment_default_asset_url( 'split.png' );
		}
		if ( false !== strpos( $id, 'yookassa' ) || false !== strpos( $id, 'yoomoney' ) || false !== strpos( $id, 'yandex_kassa' ) || false !== strpos( $id, 'yandex-kassa' ) ) {
			return self::payment_default_asset_url( 'sbp.png' );
		}
		if ( false !== strpos( $id, 'stripe' ) || false !== strpos( $id, 'paypal' ) ) {
			return self::payment_default_asset_url( 'bank_cart.png' );
		}

		if ( false !== strpos( $title, 'сплит' ) || false !== strpos( $title, 'split' ) ) {
			return self::payment_default_asset_url( 'split.png' );
		}
		if ( false !== strpos( $title, 'сбп' ) || false !== strpos( $title, 'sbp' ) ) {
			return self::payment_default_asset_url( 'sbp.png' );
		}
		// Частая опечатка: латинская «p» вместо кириллической «п» в «СБП» (например «сpб»).
		if ( preg_match( '/с(?:п|p)б/ui', $title ) ) {
			return self::payment_default_asset_url( 'sbp.png' );
		}
		if ( false !== strpos( $title, 'карт' ) || false !== strpos( $title, 'card' ) || false !== strpos( $title, 'банк' ) ) {
			return self::payment_default_asset_url( 'bank_cart.png' );
		}

		return self::payment_default_asset_url( 'bank_cart.png' );
	}

	private static function available_payment_gateways_for_runtime(): array {
		if ( ! function_exists( 'WC' ) || ! WC() ) {
			return array();
		}
		$gateways = WC()->payment_gateways();
		if ( ! $gateways instanceof \WC_Payment_Gateways ) {
			return array();
		}
		$force_checkout = CheckoutRouteHooks::is_checkout_route();
		if ( $force_checkout ) {
			add_filter( 'woocommerce_is_checkout', '__return_true', PHP_INT_MAX );
		}
		try {
			$available = $gateways->get_available_payment_gateways();
		} finally {
			if ( $force_checkout ) {
				remove_filter( 'woocommerce_is_checkout', '__return_true', PHP_INT_MAX );
			}
		}
		$step_four = SafeSettingsResolver::get_section( 'step_4' );
		$payment_block = is_array( $step_four ) && isset( $step_four['payment_block'] ) && is_array( $step_four['payment_block'] )
			? $step_four['payment_block']
			: array();
		$title_overrides = isset( $payment_block['gateway_titles'] ) && is_array( $payment_block['gateway_titles'] )
			? $payment_block['gateway_titles']
			: array();
		$result    = array();
		foreach ( $available as $gateway ) {
			if ( ! $gateway instanceof \WC_Payment_Gateway ) {
				continue;
			}
			$gid = sanitize_key( (string) $gateway->id );
			$wc_title = wp_strip_all_tags( (string) $gateway->get_title() );
			$override = isset( $title_overrides[ $gid ] ) ? trim( wp_strip_all_tags( (string) $title_overrides[ $gid ] ) ) : '';
			$title    = '' !== $override ? $override : $wc_title;
			$icon     = self::default_payment_gateway_icon_url( $gid, $title );
			$result[] = array(
				'id'          => $gid,
				'title'       => $title,
				'description' => wp_strip_all_tags( (string) $gateway->get_description() ),
				'icon'        => $icon,
			);
		}
		return $result;
	}

	private static function default_shell_design_variables(): array {
		return array(
			'color-text'         => '#1a1a1a',
			'color-text-muted'   => '#666666',
			'color-background'   => '#ffffff',
			'color-border'       => '#e5e5e5',
			'color-accent'       => '#111111',
			'color-rail'         => '#2563eb',
			'color-error'        => '#b91c1c',
			'color-success'      => '#15803d',
			'radius-sm'          => '6px',
			'radius-md'          => '10px',
			'transition-duration' => '0.2s',
			'summary-receipt-bg' => '#f3f4f6',
			'font-family-base'   => 'system-ui,-apple-system,Segoe UI,Roboto,sans-serif',
			'font-size-base'     => '16px',
		);
	}

	/**
	 * Слияние дефолтов с design_tokens из настроек (ключи из БД могут быть с подчёркиванием).
	 *
	 * @return array<string, string>
	 */
	private static function merged_shell_design_variables(): array {
		$out = self::default_shell_design_variables();
		foreach ( self::design_tokens_for_runtime() as $key => $value ) {
			if ( ! is_string( $key ) || '' === $value ) {
				continue;
			}
			$hyphen = str_replace( '_', '-', sanitize_key( $key ) );
			if ( '' === $hyphen ) {
				continue;
			}
			$out[ $hyphen ] = self::sanitize_css_token_value( (string) $value );
		}
		return $out;
	}

	/**
	 * Компактный CSS для первого кадра: токены на #mp-cc-checkout, фон body, stacked-timeline.
	 */
	private static function build_critical_checkout_shell_css(): string {
		$vars = array_merge( self::merged_shell_design_variables(), self::progress_step_index_shell_variables() );
		$decl = array();
		foreach ( $vars as $name => $value ) {
			$decl[] = '--mp-cc-' . $name . ':' . $value . ';';
		}
		$bg = isset( $vars['color-background'] ) ? $vars['color-background'] : '#ffffff';
		$fg = isset( $vars['color-text'] ) ? $vars['color-text'] : '#1a1a1a';

		$parts   = array();
		$parts[] = 'body.mp-custom-checkout{background:' . $bg . ';color:' . $fg . ';}';
		$parts[] = '#mp-cc-checkout{' . implode( '', $decl ) . self::checkout_shell_layout_inline_properties() . 'color:var(--mp-cc-color-text);background:var(--mp-cc-color-background);min-height:100vh;font-family:var(--mp-cc-font-family-base);font-size:var(--mp-cc-font-size-base);line-height:1.45;padding-block:var(--mp-cc-shell-padding-block,75px);}';
		$parts[] = '.mp-cc-v2-shell-head__title{color:var(--mp-cc-color-text);}';
		$parts[] = '.mp-cc-layout--stacked-timeline .mp-cc-region--progress,.mp-cc-layout--stacked-timeline .mp-cc-region--actions{display:none;}';
		$parts[] = '.mp-cc-layout--stacked-timeline .mp-cc-region--content{position:relative;padding-left:2.5rem;}';
		// Кнопки «Далее» в шаге не обёрнуты в .mp-cc-nav — до загрузки основного CSS тема может сжать button.
		$parts[] = '#mp-cc-checkout .mp-cc-nav__btn{box-sizing:border-box;display:inline-flex;align-items:center;justify-content:center;min-width:8rem;min-height:2.75rem;padding:0.625rem 1rem;border:1px solid var(--mp-cc-color-accent,#111111);border-radius:var(--mp-cc-radius-sm,6px);background:var(--mp-cc-color-background,#ffffff);color:var(--mp-cc-color-text,#1a1a1a);font:inherit;}';
		$parts[] = '#mp-cc-checkout .mp-cc-nav__btn--next{background:var(--mp-cc-color-accent,#111111);color:#fff;border-color:var(--mp-cc-color-accent,#111111);}';
		$parts[] = '#mp-cc-checkout .mp-cc-step-card__actions{display:flex;flex-wrap:wrap;gap:0.75rem;justify-content:flex-end;width:100%;min-width:0;margin-top:0.85rem;}';

		return implode( '', $parts );
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

	/**
	 * CSS custom properties для кружков с номером шага (вертикальный таймлайн), из раздела styles.
	 *
	 * @return array<string, string> имя без префикса --mp-cc- (с дефисами) => значение
	 */
	private static function progress_step_index_shell_variables(): array {
		$defaults = array(
			'pending_bg'       => '#ffffff',
			'pending_digit'    => '#666666',
			'pending_border'   => '#e5e5e5',
			'active_bg'        => '#2563eb',
			'active_digit'     => '#ffffff',
			'active_border'    => '#2563eb',
			'complete_bg'      => '#15803d',
			'complete_digit'   => '#ffffff',
			'complete_border'  => '#15803d',
			'border_width'     => '2px',
		);
		$styles = SafeSettingsResolver::get_section( OptionKeys::SECTION_STYLES );
		$cfg    = isset( $styles['progress_step_index'] ) && is_array( $styles['progress_step_index'] ) ? $styles['progress_step_index'] : array();
		$merged = array_replace_recursive( $defaults, array_intersect_key( $cfg, $defaults ) );
		$key_to_var = array(
			'pending_bg'       => 'progress-index-pending-bg',
			'pending_digit'    => 'progress-index-pending-digit',
			'pending_border'   => 'progress-index-pending-border',
			'active_bg'        => 'progress-index-active-bg',
			'active_digit'     => 'progress-index-active-digit',
			'active_border'    => 'progress-index-active-border',
			'complete_bg'      => 'progress-index-complete-bg',
			'complete_digit'   => 'progress-index-complete-digit',
			'complete_border'  => 'progress-index-complete-border',
		);
		$out = array();
		foreach ( $key_to_var as $cfg_key => $css_name ) {
			$raw = isset( $merged[ $cfg_key ] ) ? (string) $merged[ $cfg_key ] : $defaults[ $cfg_key ];
			$norm = self::normalize_admin_hex_color( $raw );
			$hex  = function_exists( 'sanitize_hex_color' ) ? sanitize_hex_color( $norm ) : '';
			$out[ $css_name ] = $hex ? self::sanitize_css_token_value( $hex ) : self::sanitize_css_token_value( (string) $defaults[ $cfg_key ] );
		}
		$bw_raw = isset( $merged['border_width'] ) ? (string) $merged['border_width'] : $defaults['border_width'];
		$bw     = self::sanitize_css_size_value( $bw_raw );
		$out['progress-index-border-width'] = '' !== $bw ? $bw : self::sanitize_css_token_value( (string) $defaults['border_width'] );
		return $out;
	}

	private static function build_progress_step_index_css(): string {
		$props = self::progress_step_index_shell_variables();
		if ( empty( $props ) ) {
			return '';
		}
		$decl = array();
		foreach ( $props as $name => $value ) {
			if ( '' === $value ) {
				continue;
			}
			$decl[] = '--mp-cc-' . $name . ':' . $value . ';';
		}
		return '#mp-cc-checkout{' . implode( '', $decl ) . '}';
	}

	/**
	 * CSS-свойства для ширины контента и вертикальных отступов всего checkout (переменные на #mp-cc-checkout).
	 */
	private static function checkout_shell_layout_inline_properties(): string {
		$general   = SafeSettingsResolver::get_section( OptionKeys::SECTION_GENERAL );
		$layout    = ( is_array( $general ) && isset( $general['checkout_layout'] ) && is_array( $general['checkout_layout'] ) ) ? $general['checkout_layout'] : array();
		$max_width = isset( $layout['max_width'] ) ? self::sanitize_css_size_value( (string) $layout['max_width'] ) : '';
		if ( '' === $max_width ) {
			$max_width = '1140px';
		}
		$vpad = isset( $layout['vertical_padding'] ) ? self::sanitize_css_size_value( (string) $layout['vertical_padding'] ) : '';
		if ( '' === $vpad ) {
			$vpad = '75px';
		}
		return '--mp-cc-shell-max-width:' . $max_width . ';--mp-cc-shell-padding-block:' . $vpad . ';';
	}

	private static function build_checkout_layout_css(): string {
		return '#mp-cc-checkout{' . self::checkout_shell_layout_inline_properties() . '}';
	}

	private static function sanitize_css_token_value( string $value ): string {
		$value = trim( wp_strip_all_tags( $value ) );
		$value = str_replace( array( ';', '{', '}', "\n", "\r", "\t" ), '', $value );
		return $value;
	}

	/**
	 * Подготовка цвета для sanitize_hex_color: допускает ввод без «#» (fff / 2a1f28).
	 */
	private static function normalize_admin_hex_color( string $raw ): string {
		$raw = trim( $raw );
		if ( '' === $raw ) {
			return '';
		}
		if ( '#' === $raw[0] ) {
			return $raw;
		}
		if ( preg_match( '/^[0-9a-fA-F]{3}$/', $raw ) || preg_match( '/^[0-9a-fA-F]{6}$/', $raw ) ) {
			return '#' . $raw;
		}
		return $raw;
	}

	private static function sanitize_css_size_value( string $value ): string {
		$raw = self::sanitize_css_token_value( $value );
		if ( '' === $raw ) {
			return '';
		}
		if ( preg_match( '/^\d+(?:\.\d+)?$/', $raw ) ) {
			return $raw . 'px';
		}
		if ( preg_match( '/^\d+(?:\.\d+)?(px|rem|em|vw|%)$/', $raw ) ) {
			return $raw;
		}
		return '';
	}

	/**
	 * Motion-конфиг для checkout SPA (валидированный снимок раздела motion).
	 *
	 * @return array<string, mixed>
	 */
	private static function motion_config_for_runtime(): array {
		$raw = SafeSettingsResolver::get_section( OptionKeys::SECTION_MOTION );
		if ( ! is_array( $raw ) ) {
			$raw = array();
		}
		return MotionSettingsResolver::runtime_payload( $raw );
	}

	/**
	 * CSS custom properties для #mp-cc-checkout (длительности и easing в безопасном виде).
	 */
	private static function build_motion_runtime_css(): string {
		return MotionSettingsResolver::build_inline_css( self::motion_config_for_runtime() );
	}
}
