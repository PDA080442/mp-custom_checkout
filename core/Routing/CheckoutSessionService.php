<?php
/**
 * Изолированная сессия checkout-flow (шаг, snapshot, ответы).
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout\Routing;

use MP\CustomCheckout\DependencyFailureGuard;
use MP\CustomCheckout\Hooks\CheckoutRouteHooks;
use MP\CustomCheckout\Settings\SafeSettingsResolver;
use MP\CustomCheckout\Settings\ScenarioStepRegistry;

defined( 'ABSPATH' ) || exit;

/**
 * Class CheckoutSessionService
 */
final class CheckoutSessionService {

	public const SESSION_KEY = 'mp_cc_checkout_flow';

	/**
	 * Подписка на lifecycle checkout-flow.
	 */
	public static function register(): void {
		add_action( 'template_redirect', array( __CLASS__, 'maybe_initialize_context' ), 4 );
		add_action( 'woocommerce_thankyou', array( __CLASS__, 'clear_on_success_thankyou' ), 20, 1 );
		add_action( 'mp_custom_checkout_success_screen', array( __CLASS__, 'clear_on_success' ), 5, 2 );
		add_action( 'woocommerce_cart_emptied', array( __CLASS__, 'clear_on_abandoned_flow' ), 20 );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_clear_on_order_cancel' ), 2 );
	}

	/**
	 * Инициализация контекста при открытии кастомного checkout.
	 */
	public static function maybe_initialize_context(): void {
		if ( ! CheckoutRouteHooks::is_checkout_route() ) {
			return;
		}

		$flow = self::get_flow();
		if ( ! isset( $flow['context_id'] ) || ! is_string( $flow['context_id'] ) || '' === $flow['context_id'] ) {
			$flow = self::build_initial_flow();
			if ( empty( $flow ) ) {
				self::log_session_error( 'session_init_failed' );
				return;
			}
		} else {
			$flow['updated_at'] = time();
		}

		$step_manager = new CheckoutStepManager( $flow );
		$active_step  = $step_manager->get_current_step_id();
		if ( is_string( $active_step ) && '' !== $active_step ) {
			$flow['current_step'] = $active_step;
		}

		self::persist_flow( $flow );
	}

	/**
	 * Получить текущее состояние flow.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_flow(): array {
		$session = self::get_wc_session();
		if ( ! $session ) {
			return array();
		}

		$flow = $session->get( self::SESSION_KEY, array() );
		return is_array( $flow ) ? $flow : array();
	}

	/**
	 * Сохранить текущий шаг пользователя.
	 */
	public static function set_current_step( string $step_id ): void {
		$flow = self::ensure_initialized();
		if ( empty( $flow ) ) {
			return;
		}

		$step_id = sanitize_key( $step_id );
		if ( '' === $step_id ) {
			return;
		}

		$flow['current_step'] = $step_id;
		$flow['updated_at']   = time();

		self::persist_flow( $flow );
	}

	/**
	 * Сохранить промежуточные ответы шага.
	 *
	 * @param array<string, mixed> $answers Данные шага.
	 */
	public static function set_step_answers( string $step_id, array $answers ): void {
		$flow = self::ensure_initialized();
		if ( empty( $flow ) ) {
			return;
		}

		$step_id = sanitize_key( $step_id );
		if ( '' === $step_id ) {
			return;
		}

		if ( ! isset( $flow['answers'] ) || ! is_array( $flow['answers'] ) ) {
			$flow['answers'] = array();
		}

		$flow['answers'][ $step_id ] = self::sanitize_recursive( $answers );
		$flow['updated_at']          = time();

		self::persist_flow( $flow );
	}

	/**
	 * Очистка после успешного завершения checkout.
	 *
	 * @param int       $order_id ID заказа.
	 * @param \WC_Order $order    Заказ.
	 */
	public static function clear_on_success( $order_id, $order ): void {
		unset( $order_id, $order );
		self::clear();
	}

	/**
	 * Очистка fallback-путя после стандартного хука WooCommerce thankyou.
	 */
	public static function clear_on_success_thankyou( $order_id ): void {
		unset( $order_id );
		self::clear();
	}

	/**
	 * Очистка при отмене / брошенном потоке.
	 */
	public static function clear_on_abandoned_flow(): void {
		self::clear();
	}

	/**
	 * Сброс при переходе на endpoint отмены заказа.
	 */
	public static function maybe_clear_on_order_cancel(): void {
		if ( ! function_exists( 'is_wc_endpoint_url' ) ) {
			return;
		}

		if ( is_wc_endpoint_url( 'order-cancel' ) ) {
			self::clear();
		}
	}

	/**
	 * Полная очистка checkout-flow session.
	 */
	public static function clear(): void {
		$session = self::get_wc_session();
		if ( ! $session ) {
			return;
		}

		$session->set( self::SESSION_KEY, array() );
	}

	/**
	 * Создание стартового snapshot корзины и сценария.
	 *
	 * @return array<string, mixed>
	 */
	private static function build_initial_flow(): array {
		$step_order = self::get_initial_step_order();
		$first_step = isset( $step_order[0] ) && is_string( $step_order[0] ) ? $step_order[0] : ScenarioStepRegistry::STEP_CART;
		$scenario   = self::get_initial_scenario();

		$flow = array(
			'context_id'    => wp_generate_uuid4(),
			'created_at'    => time(),
			'updated_at'    => time(),
			'current_step'  => $first_step,
			'step_order'    => $step_order,
			'scenario'      => $scenario,
			'snapshot'      => self::build_snapshot(),
			'answers'       => array(),
		);

		return (array) apply_filters( 'mp_custom_checkout_session_initial_flow', $flow );
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function build_snapshot(): array {
		$snapshot = array(
			'generated_at' => time(),
			'currency'     => function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : '',
			'items_count'  => 0,
			'cart_hash'    => '',
			'subtotal'     => '',
			'total'        => '',
			'needs_shipping' => false,
		);

		if ( function_exists( 'WC' ) && WC()->cart instanceof \WC_Cart ) {
			$cart = WC()->cart;
			$snapshot['items_count']     = (int) $cart->get_cart_contents_count();
			$snapshot['cart_hash']       = (string) $cart->get_cart_hash();
			$snapshot['subtotal']        = (string) $cart->get_cart_subtotal();
			$snapshot['total']           = (string) wc_price( (float) $cart->get_total( 'edit' ) );
			$snapshot['needs_shipping']  = (bool) $cart->needs_shipping();
		}

		return (array) apply_filters( 'mp_custom_checkout_session_snapshot', $snapshot );
	}

	/**
	 * @return \WC_Session|null
	 */
	private static function get_wc_session(): ?\WC_Session {
		if ( ! DependencyFailureGuard::is_woocommerce_integration_ready() ) {
			return null;
		}

		if ( ! function_exists( 'WC' ) || ! WC()->session instanceof \WC_Session ) {
			return null;
		}

		return WC()->session;
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function ensure_initialized(): array {
		$flow = self::get_flow();
		if ( ! empty( $flow ) ) {
			return $flow;
		}

		$flow = self::build_initial_flow();
		if ( empty( $flow ) ) {
			self::log_session_error( 'session_auto_init_failed' );
			return array();
		}

		self::persist_flow( $flow );
		return $flow;
	}

	/**
	 * @param array<string, mixed> $flow
	 */
	private static function persist_flow( array $flow ): void {
		$session = self::get_wc_session();
		if ( ! $session ) {
			self::log_session_error( 'session_missing_on_persist' );
			return;
		}

		$session->set( self::SESSION_KEY, $flow );
	}

	/**
	 * @param mixed $value
	 * @return mixed
	 */
	private static function sanitize_recursive( $value ) {
		if ( is_array( $value ) ) {
			$result = array();
			foreach ( $value as $key => $item ) {
				$clean_key = is_string( $key ) ? sanitize_key( $key ) : $key;
				$result[ $clean_key ] = self::sanitize_recursive( $item );
			}
			return $result;
		}

		if ( is_bool( $value ) || is_int( $value ) || is_float( $value ) ) {
			return $value;
		}

		if ( is_scalar( $value ) ) {
			return sanitize_text_field( (string) $value );
		}

		return '';
	}

	private static function log_session_error( string $code ): void {
		do_action(
			'mp_custom_checkout_log',
			'error',
			sprintf( '[checkout_session] %s', $code ),
			array( 'code' => $code )
		);
	}

	/**
	 * @return array<int, string>
	 */
	private static function get_initial_step_order(): array {
		$stored = SafeSettingsResolver::get( 'registry.step_order', ScenarioStepRegistry::default_step_order() );
		if ( ! is_array( $stored ) ) {
			return ScenarioStepRegistry::default_step_order();
		}

		$order = array();
		foreach ( $stored as $item ) {
			if ( ! is_string( $item ) ) {
				continue;
			}
			$key = sanitize_key( $item );
			if ( '' !== $key ) {
				$order[] = $key;
			}
		}

		$order = array_values( array_unique( $order ) );
		return ! empty( $order ) ? $order : ScenarioStepRegistry::default_step_order();
	}

	private static function get_initial_scenario(): string {
		$scenario = SafeSettingsResolver::get( 'registry.default_scenario', ScenarioStepRegistry::SCENARIO_PICKUP );
		$scenario = sanitize_key( is_string( $scenario ) ? $scenario : '' );
		$known    = array_keys( ScenarioStepRegistry::scenarios() );

		return in_array( $scenario, $known, true ) ? $scenario : ScenarioStepRegistry::SCENARIO_PICKUP;
	}
}
