<?php
/**
 * AJAX endpoint'ы checkout (авторизованные и гости).
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout\Checkout\Hooks;

use MP\CustomCheckout\DependencyFailureGuard;
use MP\CustomCheckout\Integrations\WooCommerce\GiftCardIntegration;
use MP\CustomCheckout\Routing\CheckoutDateAvailabilityEngine;
use MP\CustomCheckout\Routing\CheckoutRouteContext;
use MP\CustomCheckout\Routing\CheckoutScenarioRules;
use MP\CustomCheckout\Routing\CheckoutSuccessRouteConfig;
use MP\CustomCheckout\Checkout\Routing\CheckoutSessionService;
use MP\CustomCheckout\Routing\CheckoutStepManager;
use MP\CustomCheckout\Settings\DefaultFeatureFlagsRegistry;
use MP\CustomCheckout\Settings\FeatureFlagResolver;
use MP\CustomCheckout\Settings\SafeSettingsResolver;

defined( 'ABSPATH' ) || exit;

final class CheckoutAjaxHooks {

	public const ACTION = 'mp_cc_checkout';

	public static function register(): void {
		add_action( 'wp_ajax_' . self::ACTION, array( __CLASS__, 'handle' ) );
		add_action( 'wp_ajax_nopriv_' . self::ACTION, array( __CLASS__, 'handle' ) );
	}

	public static function handle(): void {
		if ( ! DependencyFailureGuard::is_woocommerce_integration_ready() ) {
			wp_send_json_error( array( 'message' => __( 'WooCommerce недоступен.', 'mp-custom-checkout' ) ), 503 );
		}
		if ( function_exists( 'wc_load_cart' ) ) {
			wc_load_cart();
		}
		check_ajax_referer( 'mp_cc_checkout', 'nonce' );
		$sub_action = isset( $_POST['sub_action'] ) ? sanitize_key( wp_unslash( $_POST['sub_action'] ) ) : '';
		if ( '' === $sub_action ) {
			do_action( 'mp_custom_checkout_log', 'warning', '[ajax] empty_sub_action', array( 'source' => 'ajax', 'event_type' => 'ajax_error' ) );
			wp_send_json_error( array( 'code' => 'empty_sub_action', 'message' => __( 'Не указано действие checkout.', 'mp-custom-checkout' ) ), 400 );
		}
		if ( ! self::is_session_sub_action( $sub_action ) ) {
			do_action( 'mp_custom_checkout_log', 'warning', '[ajax] unknown_sub_action', array( 'source' => 'ajax', 'event_type' => 'ajax_error', 'sub_action' => $sub_action ) );
			wp_send_json_error( array( 'code' => 'unknown_sub_action', 'message' => __( 'Неизвестное действие checkout.', 'mp-custom-checkout' ) ), 400 );
		}
		try {
			if ( self::handle_session_sub_action( $sub_action ) ) {
				return;
			}
		} catch ( \Throwable $e ) {
			do_action(
				'mp_custom_checkout_log',
				'error',
				'[ajax] unhandled_exception',
				array(
					'source'     => 'ajax',
					'event_type' => 'ajax_error',
					'channel'    => 'critical',
					'sub_action' => $sub_action,
					'message'    => $e->getMessage(),
				)
			);
			wp_send_json_error( array( 'code' => 'ajax_unhandled_exception', 'message' => __( 'Внутренняя ошибка checkout. Повторите попытку.', 'mp-custom-checkout' ) ), 500 );
		}
		do_action( 'mp_custom_checkout_ajax_request', $sub_action );
		wp_send_json_success( array( 'sub_action' => $sub_action ) );
	}

	private static function handle_session_sub_action( string $sub_action ): bool {
		if ( self::is_session_sub_action( $sub_action ) && 'session_abandon' !== $sub_action && ! self::validate_context_id() ) {
			wp_send_json_error( array( 'code' => 'stale_context', 'message' => __( 'Сессия checkout устарела. Обновите страницу.', 'mp-custom-checkout' ) ), 409 );
		}
		if ( 'session_set_step' === $sub_action ) {
			$step_id = isset( $_POST['step_id'] ) ? sanitize_key( wp_unslash( $_POST['step_id'] ) ) : '';
			if ( '' === $step_id ) {
				wp_send_json_error( array( 'code' => 'invalid_step_id', 'message' => __( 'Не указан шаг checkout.', 'mp-custom-checkout' ) ), 400 );
			}
			$flow    = CheckoutSessionService::get_flow();
			$manager = new CheckoutStepManager( is_array( $flow ) ? $flow : null );
			if ( ! $manager->can_navigate_to( $step_id ) ) {
				wp_send_json_error( array( 'code' => 'invalid_step_navigation', 'message' => __( 'Переход на указанный шаг недоступен.', 'mp-custom-checkout' ) ), 400 );
			}
			CheckoutSessionService::set_current_step( $step_id );
			wp_send_json_success(
				array(
					'sub_action'    => $sub_action,
					'current_step'  => $step_id,
					'flow'          => self::build_flow_payload(),
					'cart'          => CheckoutRouteContext::get_cart_data(),
				)
			);
		}
		if ( 'session_set_answers' === $sub_action ) {
			$step_id = isset( $_POST['step_id'] ) ? sanitize_key( wp_unslash( $_POST['step_id'] ) ) : '';
			$answers = isset( $_POST['answers'] ) && is_array( $_POST['answers'] ) ? wp_unslash( $_POST['answers'] ) : array();
			if ( '' === $step_id ) {
				wp_send_json_error( array( 'code' => 'invalid_step_id', 'message' => __( 'Не указан шаг checkout.', 'mp-custom-checkout' ) ), 400 );
			}
			$answers = self::sanitize_payload_shape( is_array( $answers ) ? $answers : array(), 4, 80 );
			if ( in_array( $step_id, array( 'date', 'conditions' ), true ) ) {
				$flow          = CheckoutSessionService::get_flow();
				$existing_date = array();
				if ( is_array( $flow ) && isset( $flow['answers']['date_conditions'] ) && is_array( $flow['answers']['date_conditions'] ) ) {
					$existing_date = $flow['answers']['date_conditions'];
				}
				// Частичный payload (напр. только с шага «условия») дополняем сохранённым date_conditions.
				$answers = array_replace_recursive( $existing_date, $answers );
				if ( ! self::validate_date_answers_payload( $answers ) ) {
					$message = SafeSettingsResolver::get( 'step_3.copy.errors.invalid_date', __( 'Выбранная дата недоступна. Обновите шаг и выберите другую дату.', 'mp-custom-checkout' ) );
					$message = is_string( $message ) && '' !== trim( $message ) ? $message : __( 'Выбранная дата недоступна. Обновите шаг и выберите другую дату.', 'mp-custom-checkout' );
					wp_send_json_error( array( 'code' => 'invalid_date_selection', 'message' => $message ), 422 );
				}
				if ( ! self::validate_shipping_answers_payload( $answers ) ) {
					wp_send_json_error(
						array(
							'code'    => 'invalid_shipping_method',
							'message' => __( 'Выбранный метод доставки недоступен. Обновите шаг и выберите заново.', 'mp-custom-checkout' ),
						),
						422
					);
				}
			}
			CheckoutSessionService::set_step_answers( $step_id, $answers );
			wp_send_json_success( array( 'sub_action' => $sub_action, 'step_id' => $step_id, 'flow' => self::build_flow_payload(), 'cart' => CheckoutRouteContext::get_cart_data() ) );
		}
		if ( 'session_set_scenario' === $sub_action ) {
			$scenario_raw = isset( $_POST['scenario'] ) ? sanitize_key( wp_unslash( $_POST['scenario'] ) ) : '';
			if ( '' === $scenario_raw ) {
				wp_send_json_error( array( 'code' => 'invalid_scenario', 'message' => __( 'Не указан сценарий оформления.', 'mp-custom-checkout' ) ), 400 );
			}
			$scenario = CheckoutScenarioRules::sanitize_scenario( $scenario_raw );
			if ( $scenario !== $scenario_raw ) {
				do_action( 'mp_custom_checkout_log', 'warning', '[scenario_switch] invalid_scenario_requested', array( 'requested' => $scenario_raw, 'resolved' => $scenario ) );
			}
			CheckoutSessionService::set_scenario( $scenario );
			wp_send_json_success( array( 'sub_action' => $sub_action, 'scenario' => $scenario, 'flow' => self::build_flow_payload(), 'cart' => CheckoutRouteContext::get_cart_data() ) );
		}
		if ( 'session_get_state' === $sub_action ) {
			CheckoutSessionService::sync_discounts_from_cart();
			wp_send_json_success(
				array_merge(
					array(
						'sub_action' => $sub_action,
						'flow'       => self::build_flow_payload(),
						'cart'       => CheckoutRouteContext::get_cart_data(),
					),
					self::get_payment_fields_for_context()
				)
			);
		}
		if ( 'set_payment_gateway' === $sub_action ) {
			self::handle_set_payment_gateway();
		}
		if ( 'submit_payment' === $sub_action ) {
			self::handle_submit_payment();
		}
		if ( 'validation_log' === $sub_action ) {
			$step_id = isset( $_POST['step_id'] ) ? sanitize_key( wp_unslash( $_POST['step_id'] ) ) : '';
			$errors  = isset( $_POST['errors'] ) && is_array( $_POST['errors'] ) ? wp_unslash( $_POST['errors'] ) : array();
			if ( '' === $step_id ) {
				wp_send_json_error( array( 'code' => 'invalid_step_id', 'message' => __( 'Не указан шаг для validation_log.', 'mp-custom-checkout' ) ), 400 );
			}
			$clean_errors = array();
			foreach ( $errors as $field => $reason ) {
				$key = sanitize_key( (string) $field );
				if ( '' === $key ) {
					continue;
				}
				$clean_errors[ $key ] = sanitize_key( (string) $reason );
			}
			do_action( 'mp_custom_checkout_log', 'warning', '[validation] step_failed', array( 'step_id' => $step_id, 'errors' => $clean_errors, 'context_id' => isset( $_POST['context_id'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['context_id'] ) ) : '' ) );
			wp_send_json_success( array( 'sub_action' => $sub_action, 'logged' => true ) );
		}
		if ( 'gateway_render_diagnostics' === $sub_action ) {
			$issues = isset( $_POST['issues'] ) && is_array( $_POST['issues'] ) ? wp_unslash( $_POST['issues'] ) : array();
			$clean_issues = array();
			foreach ( $issues as $issue ) {
				if ( ! is_scalar( $issue ) ) {
					continue;
				}
				$line = sanitize_text_field( (string) $issue );
				if ( '' === $line ) {
					continue;
				}
				$clean_issues[] = $line;
			}
			if ( ! empty( $clean_issues ) ) {
				do_action( 'mp_custom_checkout_log', 'warning', '[payment_gateway] render_diagnostics', array( 'issues' => $clean_issues, 'context_id' => isset( $_POST['context_id'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['context_id'] ) ) : '' ) );
			}
			wp_send_json_success( array( 'sub_action' => $sub_action, 'logged' => ! empty( $clean_issues ) ) );
		}
		if ( 'client_error_log' === $sub_action ) {
			$type    = isset( $_POST['error_type'] ) ? sanitize_key( wp_unslash( (string) $_POST['error_type'] ) ) : 'js_error';
			$message = isset( $_POST['message'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['message'] ) ) : '';
			$stack   = isset( $_POST['stack'] ) ? sanitize_textarea_field( wp_unslash( (string) $_POST['stack'] ) ) : '';
			$state   = isset( $_POST['state'] ) ? sanitize_key( wp_unslash( (string) $_POST['state'] ) ) : '';
			if ( '' === $message ) {
				wp_send_json_error( array( 'code' => 'empty_client_error', 'message' => __( 'Пустое сообщение client error.', 'mp-custom-checkout' ) ), 400 );
			}
			do_action( 'mp_custom_checkout_log', 'error', '[client_js] runtime_error', array( 'source' => 'client_js', 'event_type' => $type, 'message' => $message, 'stack' => $stack, 'state' => $state ) );
			wp_send_json_success( array( 'sub_action' => $sub_action, 'logged' => true ) );
		}
		if ( 'ajax_error_log' === $sub_action ) {
			$operation = isset( $_POST['operation'] ) ? sanitize_key( wp_unslash( (string) $_POST['operation'] ) ) : '';
			$status    = isset( $_POST['status'] ) ? (int) $_POST['status'] : 0;
			$error     = isset( $_POST['error'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['error'] ) ) : '';
			$response  = isset( $_POST['response_snippet'] ) ? sanitize_textarea_field( wp_unslash( (string) $_POST['response_snippet'] ) ) : '';
			do_action( 'mp_custom_checkout_log', 'error', '[ajax_client] request_failed', array( 'source' => 'ajax', 'event_type' => 'ajax_error', 'operation' => $operation, 'status' => $status, 'error' => $error, 'response' => $response ) );
			wp_send_json_success( array( 'sub_action' => $sub_action, 'logged' => true ) );
		}
		if ( 'update_quantity' === $sub_action ) {
			self::handle_update_quantity();
		}
		if ( 'remove_item' === $sub_action ) {
			self::handle_remove_item();
		}
		if ( 'apply_coupon' === $sub_action ) {
			self::handle_apply_coupon();
		}
		if ( 'remove_coupon' === $sub_action ) {
			self::handle_remove_coupon();
		}
		if ( 'apply_gift_card' === $sub_action ) {
			self::handle_apply_gift_card();
		}
		if ( 'remove_gift_card' === $sub_action ) {
			self::handle_remove_gift_card();
		}
		if ( 'session_abandon' === $sub_action ) {
			CheckoutSessionService::clear_on_abandoned_flow();
			wp_send_json_success( array( 'sub_action' => $sub_action, 'cleared' => true ) );
		}
		return false;
	}

	private static function is_session_sub_action( string $sub_action ): bool {
		return in_array( $sub_action, array( 'session_set_step', 'session_set_answers', 'session_set_scenario', 'session_get_state', 'session_abandon', 'update_quantity', 'remove_item', 'validation_log', 'apply_coupon', 'remove_coupon', 'apply_gift_card', 'remove_gift_card', 'set_payment_gateway', 'gateway_render_diagnostics', 'submit_payment', 'client_error_log', 'ajax_error_log' ), true );
	}

	/**
	 * @param mixed $payload
	 * @return array<string, mixed>
	 */
	private static function sanitize_payload_shape( $payload, int $max_depth = 4, int $max_items = 80 ): array {
		if ( ! is_array( $payload ) ) {
			return array();
		}
		return self::sanitize_payload_shape_recursive( $payload, 0, $max_depth, $max_items );
	}

	/**
	 * @param array<mixed, mixed> $node
	 * @return array<string, mixed>
	 */
	private static function sanitize_payload_shape_recursive( array $node, int $depth, int $max_depth, int $max_items ): array {
		if ( $depth >= $max_depth ) {
			return array();
		}
		$out = array();
		$count = 0;
		foreach ( $node as $key => $value ) {
			if ( $count >= $max_items ) {
				break;
			}
			$count++;
			$k = sanitize_key( is_string( $key ) ? $key : (string) $key );
			if ( '' === $k ) {
				continue;
			}
			if ( is_array( $value ) ) {
				$out[ $k ] = self::sanitize_payload_shape_recursive( $value, $depth + 1, $max_depth, $max_items );
				continue;
			}
			if ( is_bool( $value ) || is_int( $value ) || is_float( $value ) || null === $value ) {
				$out[ $k ] = $value;
				continue;
			}
			if ( is_object( $value ) ) {
				$out[ $k ] = sanitize_text_field( wp_json_encode( $value ) ?: '' );
				continue;
			}
			$out[ $k ] = sanitize_textarea_field( (string) $value );
		}
		return $out;
	}

	private static function handle_set_payment_gateway(): void {
		$gateway = isset( $_POST['gateway'] ) ? sanitize_key( wp_unslash( $_POST['gateway'] ) ) : '';
		if ( '' === $gateway ) {
			wp_send_json_error( array( 'code' => 'invalid_gateway', 'message' => __( 'Не выбран способ оплаты.', 'mp-custom-checkout' ) ), 400 );
		}
		if ( ! function_exists( 'WC' ) || ! WC() ) {
			wp_send_json_error( array( 'code' => 'wc_unavailable', 'message' => __( 'WooCommerce недоступен.', 'mp-custom-checkout' ) ), 503 );
		}
		$pm = WC()->payment_gateways();
		if ( ! $pm instanceof \WC_Payment_Gateways ) {
			wp_send_json_error( array( 'code' => 'wc_gateway_unavailable', 'message' => __( 'Платёжные шлюзы WooCommerce недоступны.', 'mp-custom-checkout' ) ), 503 );
		}
		$available = $pm->get_available_payment_gateways();
		if ( ! isset( $available[ $gateway ] ) ) {
			wp_send_json_error( array( 'code' => 'gateway_not_available', 'message' => __( 'Выбранный способ оплаты сейчас недоступен.', 'mp-custom-checkout' ) ), 422 );
		}
		if ( WC()->session ) {
			WC()->session->set( 'chosen_payment_method', $gateway );
		}
		$flow                   = CheckoutSessionService::get_flow();
		$answers                = isset( $flow['answers'] ) && is_array( $flow['answers'] ) ? $flow['answers'] : array();
		$contact                = isset( $answers['contact_billing'] ) && is_array( $answers['contact_billing'] ) ? $answers['contact_billing'] : array();
		$contact['payment_gateway'] = $gateway;
		$contact['gateway']     = $gateway;
		CheckoutSessionService::set_step_answers( 'contact_payment', $contact );
		$fields_payload = self::build_payment_fields_payload_from_session();
		wp_send_json_success(
			array_merge(
				array(
					'sub_action'        => 'set_payment_gateway',
					'payment_gateway'   => $gateway,
					'flow'              => self::build_flow_payload(),
					'cart'              => CheckoutRouteContext::get_cart_data(),
				),
				$fields_payload
			)
		);
	}

	/**
	 * HTML полей оплаты WooCommerce для текущего выбранного шлюза (синхронизация chosen_payment_method из flow).
	 *
	 * @return array{payment_fields_html:string,payment_fields_gateway:string}
	 */
	public static function get_payment_fields_for_context(): array {
		if ( ! function_exists( 'WC' ) || ! WC() ) {
			return array(
				'payment_fields_html'    => '',
				'payment_fields_gateway' => '',
			);
		}
		self::sync_chosen_payment_method_from_flow();
		return self::build_payment_fields_payload_from_session();
	}

	private static function sync_chosen_payment_method_from_flow(): void {
		if ( ! function_exists( 'WC' ) || ! WC() || ! WC()->session ) {
			return;
		}
		$flow = CheckoutSessionService::get_flow();
		if ( ! is_array( $flow ) ) {
			return;
		}
		$answers = isset( $flow['answers'] ) && is_array( $flow['answers'] ) ? $flow['answers'] : array();
		$contact = isset( $answers['contact_billing'] ) && is_array( $answers['contact_billing'] ) ? $answers['contact_billing'] : array();
		$gw      = isset( $contact['payment_gateway'] ) ? sanitize_key( (string) $contact['payment_gateway'] ) : '';
		if ( '' === $gw && isset( $contact['gateway'] ) ) {
			$gw = sanitize_key( (string) $contact['gateway'] );
		}
		if ( '' !== $gw ) {
			WC()->session->set( 'chosen_payment_method', $gw );
		}
	}

	/**
	 * @return array{payment_fields_html:string,payment_fields_gateway:string}
	 */
	private static function build_payment_fields_payload_from_session(): array {
		if ( ! function_exists( 'WC' ) || ! WC() || ! WC()->session ) {
			return array(
				'payment_fields_html'    => '',
				'payment_fields_gateway' => '',
			);
		}
		$gateway_id = sanitize_key( (string) WC()->session->get( 'chosen_payment_method' ) );
		if ( '' === $gateway_id ) {
			return array(
				'payment_fields_html'    => '',
				'payment_fields_gateway' => '',
			);
		}
		$pm = WC()->payment_gateways();
		if ( ! $pm instanceof \WC_Payment_Gateways ) {
			return array(
				'payment_fields_html'    => '',
				'payment_fields_gateway' => $gateway_id,
			);
		}
		$available = $pm->get_available_payment_gateways();
		if ( ! isset( $available[ $gateway_id ] ) || ! $available[ $gateway_id ] instanceof \WC_Payment_Gateway ) {
			return array(
				'payment_fields_html'    => '',
				'payment_fields_gateway' => $gateway_id,
			);
		}
		$html = self::capture_gateway_payment_fields_html( $available[ $gateway_id ] );
		return array(
			'payment_fields_html'    => $html,
			'payment_fields_gateway' => $gateway_id,
		);
	}

	private static function capture_gateway_payment_fields_html( \WC_Payment_Gateway $gateway ): string {
		try {
			ob_start();
			$gateway->payment_fields();
			return (string) ob_get_clean();
		} catch ( \Throwable $e ) {
			while ( ob_get_level() > 0 ) {
				ob_end_clean();
			}
			do_action(
				'mp_custom_checkout_log',
				'warning',
				'[payment_fields] capture_failed',
				array(
					'source'      => 'ajax',
					'event_type'  => 'payment_fields',
					'gateway_id'  => method_exists( $gateway, 'get_id' ) ? $gateway->get_id() : '',
					'message'     => $e->getMessage(),
				)
			);
			return '';
		}
	}

	private static function handle_submit_payment(): void {
		if ( ! function_exists( 'WC' ) || ! WC() || ! WC()->cart instanceof \WC_Cart ) {
			wp_send_json_error( array( 'code' => 'cart_unavailable', 'message' => __( 'Корзина недоступна.', 'mp-custom-checkout' ) ), 503 );
		}
		$cart = WC()->cart;
		if ( $cart->is_empty() ) {
			wp_send_json_error( array( 'code' => 'empty_cart', 'message' => __( 'Корзина пуста. Невозможно отправить оплату.', 'mp-custom-checkout' ) ), 422 );
		}
		$flow    = CheckoutSessionService::get_flow();
		$answers = isset( $flow['answers'] ) && is_array( $flow['answers'] ) ? $flow['answers'] : array();
		$contact = isset( $answers['contact_billing'] ) && is_array( $answers['contact_billing'] ) ? $answers['contact_billing'] : array();
		$gateway = isset( $contact['payment_gateway'] ) ? sanitize_key( (string) $contact['payment_gateway'] ) : '';
		if ( '' === $gateway ) {
			wp_send_json_error( array( 'code' => 'gateway_missing', 'message' => __( 'Не выбран способ оплаты.', 'mp-custom-checkout' ) ), 422 );
		}
		$pm = WC()->payment_gateways();
		if ( ! $pm instanceof \WC_Payment_Gateways ) {
			wp_send_json_error( array( 'code' => 'gateway_unavailable', 'message' => __( 'Платёжные шлюзы недоступны.', 'mp-custom-checkout' ) ), 503 );
		}
		$available = $pm->get_available_payment_gateways();
		if ( ! isset( $available[ $gateway ] ) ) {
			wp_send_json_error( array( 'code' => 'gateway_not_available', 'message' => __( 'Выбранный способ оплаты недоступен.', 'mp-custom-checkout' ) ), 422 );
		}
		$wc_session = WC()->session;
		$lock_key   = 'mp_cc_payment_submit_lock';
		if ( $wc_session instanceof \WC_Session ) {
			$last_lock = (int) $wc_session->get( $lock_key, 0 );
			if ( $last_lock > 0 && ( time() - $last_lock ) < 8 ) {
				wp_send_json_error( array( 'code' => 'payment_locked', 'message' => __( 'Оплата уже отправляется. Подождите завершения операции.', 'mp-custom-checkout' ) ), 429 );
			}
			$wc_session->set( $lock_key, time() );
		}
		try {
			$order = self::create_order_from_cart_and_answers( $contact, $gateway );
			if ( ! $order instanceof \WC_Order ) {
				throw new \RuntimeException( __( 'Не удалось создать заказ для оплаты.', 'mp-custom-checkout' ) );
			}
			$gateway_obj = $available[ $gateway ];
			if ( ! $gateway_obj instanceof \WC_Payment_Gateway ) {
				throw new \RuntimeException( __( 'Ошибка инициализации способа оплаты.', 'mp-custom-checkout' ) );
			}
			$is_testing = FeatureFlagResolver::is_enabled( DefaultFeatureFlagsRegistry::FLAG_CHECKOUT_TESTING_MODE, false );
			if ( $is_testing ) {
				$order->payment_complete();
				$order->add_order_note( 'MP CC testing mode: payment auto-confirmed.' );
				$cart->empty_cart( false );
				$success_url = CheckoutSuccessRouteConfig::get_success_url( (int) $order->get_id(), (string) $order->get_order_key() );
				wp_send_json_success( array( 'sub_action' => 'submit_payment', 'status' => 'success', 'confirmed' => true, 'success_url' => $success_url, 'order_id' => (int) $order->get_id(), 'flow' => self::build_flow_payload(), 'cart' => CheckoutRouteContext::get_cart_data() ) );
			}
			$result = $gateway_obj->process_payment( (int) $order->get_id() );
			$result = is_array( $result ) ? $result : array();
			$status = isset( $result['result'] ) ? (string) $result['result'] : '';
			if ( 'success' !== $status ) {
				throw new \RuntimeException( __( 'Платежный шлюз вернул ошибку отправки.', 'mp-custom-checkout' ) );
			}
			$success_url = CheckoutSuccessRouteConfig::get_success_url( (int) $order->get_id(), (string) $order->get_order_key() );
			$cart->empty_cart( false );
			wp_send_json_success( array( 'sub_action' => 'submit_payment', 'status' => 'success', 'confirmed' => true, 'success_url' => $success_url, 'order_id' => (int) $order->get_id(), 'gateway_redirect' => isset( $result['redirect'] ) ? (string) $result['redirect'] : '', 'flow' => self::build_flow_payload(), 'cart' => CheckoutRouteContext::get_cart_data() ) );
		} catch ( \Throwable $e ) {
			do_action( 'mp_custom_checkout_log', 'error', '[payment_submit] failed', array( 'message' => $e->getMessage(), 'gateway' => $gateway ) );
			wp_send_json_error( array( 'code' => 'payment_submit_failed', 'message' => __( 'Не удалось отправить оплату. Проверьте данные и попробуйте снова.', 'mp-custom-checkout' ), 'flow' => self::build_flow_payload(), 'cart' => CheckoutRouteContext::get_cart_data() ), 422 );
		} finally {
			if ( $wc_session instanceof \WC_Session ) {
				$wc_session->set( $lock_key, 0 );
			}
		}
	}

	private static function create_order_from_cart_and_answers( array $contact, string $gateway ): ?\WC_Order {
		if ( ! function_exists( 'wc_create_order' ) || ! function_exists( 'WC' ) || ! WC()->cart instanceof \WC_Cart ) {
			return null;
		}
		$order = wc_create_order();
		if ( ! $order instanceof \WC_Order ) {
			return null;
		}
		foreach ( WC()->cart->get_cart() as $item ) {
			$product = isset( $item['data'] ) && $item['data'] instanceof \WC_Product ? $item['data'] : null;
			$qty     = isset( $item['quantity'] ) ? (int) $item['quantity'] : 0;
			if ( ! $product || $qty <= 0 ) {
				continue;
			}
			$order->add_product( $product, $qty );
		}
		$order->set_payment_method( $gateway );
		$order->set_billing_first_name( isset( $contact['billing_first_name'] ) ? (string) $contact['billing_first_name'] : '' );
		$order->set_billing_last_name( isset( $contact['billing_last_name'] ) ? (string) $contact['billing_last_name'] : '' );
		$order->set_billing_email( isset( $contact['billing_email'] ) ? (string) $contact['billing_email'] : '' );
		$order->set_billing_phone( isset( $contact['billing_phone'] ) ? (string) $contact['billing_phone'] : '' );
		$order->set_billing_country( isset( $contact['country'] ) ? (string) $contact['country'] : '' );
		$order->set_billing_state( isset( $contact['state'] ) ? (string) $contact['state'] : '' );
		$order->set_billing_city( isset( $contact['city'] ) ? (string) $contact['city'] : '' );
		$order->set_billing_address_1( isset( $contact['address_1'] ) ? (string) $contact['address_1'] : '' );
		$order->set_billing_address_2( isset( $contact['address_2'] ) ? (string) $contact['address_2'] : '' );
		$order->set_billing_postcode( isset( $contact['postcode'] ) ? (string) $contact['postcode'] : '' );
		// Ensure our contact fields/meta are applied before totals calculation.
		if ( class_exists( '\\MP\\CustomCheckout\\Checkout\\Hooks\\OrderMetaHooks' ) ) {
			OrderMetaHooks::apply_contact_fields_to_order( $order, array() );
		}
		$order->calculate_totals( true );
		// Persist the remaining meta (scenario/date/conditions/discounts...) after totals are calculated.
		// Uses in-memory meta updates, persisted together with the final $order->save().
		if ( class_exists( '\\MP\\CustomCheckout\\Checkout\\Hooks\\OrderMetaHooks' ) ) {
			OrderMetaHooks::on_checkout_order_created( $order, array() );
		} else {
			do_action( 'mp_custom_checkout_save_order_meta', $order, array() );
		}
		$order->save();
		return $order;
	}

	private static function validate_context_id(): bool {
		$posted_context = isset( $_POST['context_id'] ) ? sanitize_text_field( wp_unslash( $_POST['context_id'] ) ) : '';
		$flow           = CheckoutSessionService::get_public_state();
		if ( empty( $flow ) ) {
			return true;
		}
		return CheckoutSessionService::validate_context_id( $flow, $posted_context );
	}

	/** @param array<string, mixed> $answers */
	private static function validate_date_answers_payload( array $answers ): bool {
		$selected_date = isset( $answers['selected_date'] ) ? sanitize_text_field( (string) $answers['selected_date'] ) : '';
		if ( '' === $selected_date ) {
			do_action( 'mp_custom_checkout_log', 'warning', '[date_sync] empty_date_selected', array() );
			return false;
		}
		if ( 1 !== preg_match( '/^\d{4}-\d{2}-\d{2}$/', $selected_date ) ) {
			do_action( 'mp_custom_checkout_log', 'warning', '[date_sync] invalid_date_format', array( 'selected_date' => $selected_date ) );
			return false;
		}
		$flow     = CheckoutSessionService::get_public_state();
		$scenario = isset( $flow['scenario'] ) ? (string) $flow['scenario'] : '';
		$scenario = CheckoutScenarioRules::sanitize_scenario( $scenario );
		$rules    = CheckoutDateAvailabilityEngine::build_rules( $scenario );
		$allowed  = isset( $rules['available_dates'] ) && is_array( $rules['available_dates'] ) ? $rules['available_dates'] : array();
		$is_valid = in_array( $selected_date, $allowed, true );
		if ( ! $is_valid ) {
			do_action( 'mp_custom_checkout_log', 'error', '[date_sync] selected_date_not_available', array( 'selected_date' => $selected_date, 'scenario' => $scenario ) );
		}
		return $is_valid;
	}

	/** @param array<string, mixed> $answers */
	private static function validate_shipping_answers_payload( array $answers ): bool {
		if ( ! FeatureFlagResolver::is_enabled( DefaultFeatureFlagsRegistry::FLAG_CHECKOUT_UI_V2, false ) ) {
			return true;
		}
		$method_id = isset( $answers['shipping_method_id'] ) ? sanitize_key( (string) $answers['shipping_method_id'] ) : '';
		if ( '' === $method_id ) {
			return false;
		}
		$catalog = self::shipping_catalog();
		if ( ! isset( $catalog[ $method_id ] ) || ! is_array( $catalog[ $method_id ] ) ) {
			do_action( 'mp_custom_checkout_log', 'warning', '[shipping_sync] unknown_shipping_method', array( 'shipping_method_id' => $method_id ) );
			return false;
		}
		$method = $catalog[ $method_id ];
		$flow = CheckoutSessionService::get_public_state();
		$current_scenario = isset( $flow['scenario'] ) ? CheckoutScenarioRules::sanitize_scenario( (string) $flow['scenario'] ) : '';
		$allowed_scenarios = isset( $method['visibility_scenarios'] ) && is_array( $method['visibility_scenarios'] ) ? $method['visibility_scenarios'] : array();
		if ( ! empty( $allowed_scenarios ) && '' !== $current_scenario && ! in_array( $current_scenario, $allowed_scenarios, true ) ) {
			do_action( 'mp_custom_checkout_log', 'warning', '[shipping_sync] scenario_visibility_blocked', array( 'shipping_method_id' => $method_id, 'scenario' => $current_scenario ) );
			return false;
		}
		if ( ! empty( $method['tariffs'] ) && is_array( $method['tariffs'] ) ) {
			$tariff_id = isset( $answers['shipping_tariff_id'] ) ? sanitize_key( (string) $answers['shipping_tariff_id'] ) : '';
			if ( '' === $tariff_id || ! in_array( $tariff_id, $method['tariffs'], true ) ) {
				do_action( 'mp_custom_checkout_log', 'warning', '[shipping_sync] invalid_shipping_tariff', array( 'shipping_method_id' => $method_id, 'shipping_tariff_id' => $tariff_id ) );
				return false;
			}
		}
		return true;
	}

	/** @return array<string, array<string, mixed>> */
	private static function shipping_catalog(): array {
		$delivery = SafeSettingsResolver::get_section( 'delivery' );
		$catalog  = isset( $delivery['shipping_catalog'] ) && is_array( $delivery['shipping_catalog'] ) ? $delivery['shipping_catalog'] : array();
		$methods  = isset( $catalog['methods'] ) && is_array( $catalog['methods'] ) ? $catalog['methods'] : array();
		$result   = array();
		foreach ( $methods as $method_id => $method ) {
			$id = sanitize_key( (string) $method_id );
			if ( '' === $id || ! is_array( $method ) ) {
				continue;
			}
			if ( array_key_exists( 'active', $method ) && empty( $method['active'] ) ) {
				continue;
			}
			$tariffs = array();
			if ( isset( $method['tariffs'] ) && is_array( $method['tariffs'] ) ) {
				foreach ( $method['tariffs'] as $tariff_id => $tariff ) {
					$t_id = sanitize_key( (string) $tariff_id );
					if ( '' === $t_id ) {
						continue;
					}
					if ( is_array( $tariff ) && array_key_exists( 'active', $tariff ) && empty( $tariff['active'] ) ) {
						continue;
					}
					$tariffs[] = $t_id;
				}
			}
			$visibility = isset( $method['visibility_scenarios'] ) && is_array( $method['visibility_scenarios'] ) ? array_values( array_map( 'sanitize_key', $method['visibility_scenarios'] ) ) : array();
			$result[ $id ] = array(
				'tariffs' => $tariffs,
				'visibility_scenarios' => array_values( array_filter( $visibility ) ),
			);
		}
		if ( empty( $result ) ) {
			$result = array(
				'post_russia'          => array( 'tariffs' => array(), 'visibility_scenarios' => array( 'other_city_delivery' ) ),
				'courier'              => array( 'tariffs' => array( 'express', 'standard' ), 'visibility_scenarios' => array( 'krasnoyarsk_delivery', 'other_city_delivery' ) ),
				'pvz'                  => array( 'tariffs' => array( 'express', 'standard' ), 'visibility_scenarios' => array( 'krasnoyarsk_delivery', 'other_city_delivery' ) ),
				'krasnoyarsk_delivery' => array( 'tariffs' => array(), 'visibility_scenarios' => array( 'krasnoyarsk_delivery' ) ),
				'pickup'               => array( 'tariffs' => array(), 'visibility_scenarios' => array( 'pickup' ) ),
			);
		}
		return $result;
	}

	private static function handle_update_quantity(): void { /* migrated intact from legacy */
		$item_key = isset( $_POST['item_key'] ) ? wc_clean( wp_unslash( $_POST['item_key'] ) ) : '';
		$qty_raw  = isset( $_POST['quantity'] ) ? wp_unslash( $_POST['quantity'] ) : null;
		$qty      = is_numeric( $qty_raw ) ? (int) $qty_raw : 0;
		if ( '' === $item_key || $qty <= 0 ) { wp_send_json_error( array( 'code' => 'invalid_quantity_payload', 'message' => __( 'Некорректные данные количества.', 'mp-custom-checkout' ) ), 400 ); }
		if ( ! function_exists( 'WC' ) || ! WC()->cart instanceof \WC_Cart ) { wp_send_json_error( array( 'code' => 'cart_unavailable', 'message' => __( 'Корзина недоступна.', 'mp-custom-checkout' ) ), 503 ); }
		$cart = WC()->cart; $item = $cart->get_cart_item( $item_key );
		if ( ! is_array( $item ) || empty( $item['data'] ) || ! $item['data'] instanceof \WC_Product ) { wp_send_json_error( array( 'code' => 'cart_item_not_found', 'message' => __( 'Позиция корзины не найдена.', 'mp-custom-checkout' ) ), 404 ); }
		$product = $item['data']; $min_qty = max( 1, (int) $product->get_min_purchase_quantity() ); $max_qty = (int) $product->get_max_purchase_quantity(); if ( $max_qty <= 0 ) { $max_qty = 9999; }
		if ( $qty < $min_qty || $qty > $max_qty ) { wp_send_json_error( array( 'code' => 'quantity_out_of_bounds', 'message' => sprintf( __( 'Допустимое количество: от %1$d до %2$d.', 'mp-custom-checkout' ), $min_qty, $max_qty ), 'min' => $min_qty, 'max' => $max_qty ), 400 ); }
		$result = $cart->set_quantity( $item_key, $qty, true ); if ( false === $result ) { do_action( 'mp_custom_checkout_log', 'error', '[cart_quantity] update_failed', array( 'item_key' => $item_key, 'quantity' => $qty ) ); wp_send_json_error( array( 'code' => 'update_failed', 'message' => __( 'Не удалось обновить количество.', 'mp-custom-checkout' ) ), 500 ); }
		$updated_item   = $cart->get_cart_item( $item_key ); $line_subtotal = ''; if ( is_array( $updated_item ) && isset( $updated_item['data'] ) && $updated_item['data'] instanceof \WC_Product ) { $line_subtotal = $cart->get_product_subtotal( $updated_item['data'], (int) $qty ); }
		CheckoutSessionService::sync_discounts_from_cart();
		wp_send_json_success( array( 'sub_action' => 'update_quantity', 'item' => array( 'key' => $item_key, 'quantity' => $qty, 'line_subtotal' => (string) $line_subtotal ), 'cart' => CheckoutRouteContext::get_cart_data(), 'flow' => self::build_flow_payload() ) );
	}

	private static function handle_remove_item(): void { $item_key = isset( $_POST['item_key'] ) ? wc_clean( wp_unslash( $_POST['item_key'] ) ) : ''; if ( '' === $item_key ) { wp_send_json_error( array( 'code' => 'invalid_remove_payload', 'message' => __( 'Не указан ключ позиции корзины.', 'mp-custom-checkout' ) ), 400 ); } if ( ! function_exists( 'WC' ) || ! WC()->cart instanceof \WC_Cart ) { wp_send_json_error( array( 'code' => 'cart_unavailable', 'message' => __( 'Корзина недоступна.', 'mp-custom-checkout' ) ), 503 ); } $cart = WC()->cart; $item = $cart->get_cart_item( $item_key ); if ( ! is_array( $item ) ) { wp_send_json_error( array( 'code' => 'cart_item_not_found', 'message' => __( 'Позиция корзины не найдена.', 'mp-custom-checkout' ) ), 404 ); } $removed = $cart->remove_cart_item( $item_key ); if ( false === $removed ) { do_action( 'mp_custom_checkout_log', 'error', '[cart_remove] remove_failed', array( 'item_key' => $item_key ) ); wp_send_json_error( array( 'code' => 'remove_failed', 'message' => __( 'Не удалось удалить позицию из корзины.', 'mp-custom-checkout' ) ), 500 ); } CheckoutSessionService::sync_discounts_from_cart(); wp_send_json_success( array( 'sub_action' => 'remove_item', 'item_key' => $item_key, 'cart' => CheckoutRouteContext::get_cart_data(), 'flow' => self::build_flow_payload(), 'is_empty' => 0 === (int) $cart->get_cart_contents_count() ) ); }
	private static function handle_apply_coupon(): void { $raw_code = isset( $_POST['coupon_code'] ) ? wc_clean( wp_unslash( $_POST['coupon_code'] ) ) : ''; $code = function_exists( 'wc_format_coupon_code' ) ? wc_format_coupon_code( $raw_code ) : strtolower( $raw_code ); if ( '' === $code ) { wp_send_json_error( array( 'code' => 'coupon_empty', 'message' => __( 'Введите код купона.', 'mp-custom-checkout' ) ), 400 ); } if ( ! function_exists( 'WC' ) || ! WC()->cart instanceof \WC_Cart ) { wp_send_json_error( array( 'code' => 'cart_unavailable', 'message' => __( 'Корзина недоступна.', 'mp-custom-checkout' ) ), 503 ); } if ( function_exists( 'wc_coupons_enabled' ) && ! wc_coupons_enabled() ) { wp_send_json_error( array( 'code' => 'coupons_disabled', 'message' => __( 'Купоны отключены в настройках магазина.', 'mp-custom-checkout' ) ), 400 ); } $cart = WC()->cart; if ( function_exists( 'wc_clear_notices' ) ) { wc_clear_notices(); } $applied = $cart->apply_coupon( $code ); $cart->calculate_totals(); $message = self::extract_coupon_notice_message( true ); if ( false === $applied ) { $error_message = '' !== $message ? $message : __( 'Не удалось применить промокод.', 'mp-custom-checkout' ); do_action( 'mp_custom_checkout_log', 'warning', '[coupon] apply_failed', array( 'coupon_code' => $code, 'message' => $error_message ) ); wp_send_json_error( array( 'code' => 'coupon_apply_failed', 'message' => $error_message, 'applied_coupons' => array_values( $cart->get_applied_coupons() ), 'cart' => CheckoutRouteContext::get_cart_data(), 'flow' => self::build_flow_payload() ), 422 ); } CheckoutSessionService::sync_discounts_from_cart(); $success_message = '' !== $message ? $message : __( 'Промокод применён.', 'mp-custom-checkout' ); wp_send_json_success( array( 'sub_action' => 'apply_coupon', 'coupon_code' => $code, 'message' => $success_message, 'applied_coupons' => array_values( $cart->get_applied_coupons() ), 'cart' => CheckoutRouteContext::get_cart_data(), 'flow' => self::build_flow_payload() ) ); }
	private static function handle_remove_coupon(): void { $raw_code = isset( $_POST['coupon_code'] ) ? wc_clean( wp_unslash( $_POST['coupon_code'] ) ) : ''; $code = function_exists( 'wc_format_coupon_code' ) ? wc_format_coupon_code( $raw_code ) : strtolower( $raw_code ); if ( '' === $code ) { wp_send_json_error( array( 'code' => 'coupon_empty', 'message' => __( 'Не указан купон для удаления.', 'mp-custom-checkout' ) ), 400 ); } if ( ! function_exists( 'WC' ) || ! WC()->cart instanceof \WC_Cart ) { wp_send_json_error( array( 'code' => 'cart_unavailable', 'message' => __( 'Корзина недоступна.', 'mp-custom-checkout' ) ), 503 ); } $cart = WC()->cart; if ( ! in_array( $code, $cart->get_applied_coupons(), true ) ) { wp_send_json_error( array( 'code' => 'coupon_not_found', 'message' => __( 'Купон уже не применён.', 'mp-custom-checkout' ), 'applied_coupons' => array_values( $cart->get_applied_coupons() ), 'cart' => CheckoutRouteContext::get_cart_data(), 'flow' => self::build_flow_payload() ), 404 ); } if ( function_exists( 'wc_clear_notices' ) ) { wc_clear_notices(); } $removed = $cart->remove_coupon( $code ); $cart->calculate_totals(); if ( false === $removed ) { do_action( 'mp_custom_checkout_log', 'warning', '[coupon] remove_failed', array( 'coupon_code' => $code ) ); wp_send_json_error( array( 'code' => 'coupon_remove_failed', 'message' => __( 'Не удалось удалить купон.', 'mp-custom-checkout' ), 'applied_coupons' => array_values( $cart->get_applied_coupons() ), 'cart' => CheckoutRouteContext::get_cart_data(), 'flow' => self::build_flow_payload() ), 422 ); } CheckoutSessionService::sync_discounts_from_cart(); wp_send_json_success( array( 'sub_action' => 'remove_coupon', 'coupon_code' => $code, 'message' => __( 'Купон удалён.', 'mp-custom-checkout' ), 'applied_coupons' => array_values( $cart->get_applied_coupons() ), 'cart' => CheckoutRouteContext::get_cart_data(), 'flow' => self::build_flow_payload() ) ); }
	private static function handle_apply_gift_card(): void { $raw_code = isset( $_POST['gift_card_code'] ) ? wc_clean( wp_unslash( $_POST['gift_card_code'] ) ) : ''; $code = trim( (string) $raw_code ); if ( '' === $code ) { wp_send_json_error( array( 'code' => 'gift_card_empty', 'message' => __( 'Введите код подарочной карты.', 'mp-custom-checkout' ) ), 400 ); } if ( ! function_exists( 'WC' ) || ! WC()->cart instanceof \WC_Cart ) { wp_send_json_error( array( 'code' => 'cart_unavailable', 'message' => __( 'Корзина недоступна.', 'mp-custom-checkout' ) ), 503 ); } $integration = new GiftCardIntegration(); if ( ! $integration->is_pw_gift_cards_available() ) { do_action( 'mp_custom_checkout_log', 'error', '[gift_card] pw_unavailable', array( 'gift_card_code' => $code ) ); wp_send_json_error( array( 'code' => 'pw_unavailable', 'message' => __( 'Интеграция подарочных карт недоступна.', 'mp-custom-checkout' ) ), 503 ); } $existing_cards = $integration->get_applied_gift_cards(); if ( ! empty( $existing_cards ) && ! in_array( $code, $existing_cards, true ) ) { wp_send_json_error( array( 'code' => 'gift_card_single_only', 'message' => __( 'Можно применить только одну подарочную карту на заказ.', 'mp-custom-checkout' ), 'applied_gift_cards' => array_values( $existing_cards ), 'cart' => CheckoutRouteContext::get_cart_data(), 'flow' => self::build_flow_payload() ), 409 ); } if ( function_exists( 'wc_clear_notices' ) ) { wc_clear_notices(); } $result = $integration->apply_gift_card( $code ); WC()->cart->calculate_totals(); $message = self::extract_coupon_notice_message( true ); $cards = $integration->get_applied_gift_cards(); if ( is_wp_error( $result ) ) { $error_message = $message ? $message : (string) $result->get_error_message(); do_action( 'mp_custom_checkout_log', 'error', '[gift_card] apply_failed', array( 'gift_card_code' => $code, 'error_code' => (string) $result->get_error_code(), 'message' => $error_message ) ); wp_send_json_error( array( 'code' => 'gift_card_apply_failed', 'message' => $error_message ? $error_message : __( 'Не удалось применить подарочную карту.', 'mp-custom-checkout' ), 'applied_gift_cards' => array_values( $cards ), 'cart' => CheckoutRouteContext::get_cart_data(), 'flow' => self::build_flow_payload() ), 422 ); } CheckoutSessionService::sync_discounts_from_cart(); wp_send_json_success( array( 'sub_action' => 'apply_gift_card', 'gift_card_code' => $code, 'message' => $message ? $message : __( 'Подарочная карта применена.', 'mp-custom-checkout' ), 'applied_gift_cards' => array_values( $cards ), 'cart' => CheckoutRouteContext::get_cart_data(), 'flow' => self::build_flow_payload() ) ); }

	private static function handle_remove_gift_card(): void {
		$raw_code = isset( $_POST['gift_card_code'] ) ? wc_clean( wp_unslash( $_POST['gift_card_code'] ) ) : '';
		$code     = trim( (string) $raw_code );
		if ( '' === $code ) {
			wp_send_json_error( array( 'code' => 'gift_card_empty', 'message' => __( 'Не указан код подарочной карты.', 'mp-custom-checkout' ) ), 400 );
		}
		if ( ! function_exists( 'WC' ) || ! WC()->cart instanceof \WC_Cart ) {
			wp_send_json_error( array( 'code' => 'cart_unavailable', 'message' => __( 'Корзина недоступна.', 'mp-custom-checkout' ) ), 503 );
		}
		$integration = new GiftCardIntegration();
		if ( ! $integration->is_pw_gift_cards_available() ) {
			wp_send_json_error( array( 'code' => 'pw_unavailable', 'message' => __( 'Интеграция подарочных карт недоступна.', 'mp-custom-checkout' ) ), 503 );
		}
		$applied = $integration->get_applied_gift_cards();
		if ( empty( $applied ) || ! in_array( $code, $applied, true ) ) {
			wp_send_json_error(
				array(
					'code'               => 'gift_card_not_applied',
					'message'            => __( 'Эта подарочная карта не применена к заказу.', 'mp-custom-checkout' ),
					'applied_gift_cards' => array_values( $applied ),
					'cart'               => CheckoutRouteContext::get_cart_data(),
					'flow'               => self::build_flow_payload(),
				),
				404
			);
		}
		$result = $integration->remove_gift_card( $code );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				array(
					'code'    => 'gift_card_remove_failed',
					'message' => (string) $result->get_error_message(),
					'cart'    => CheckoutRouteContext::get_cart_data(),
					'flow'    => self::build_flow_payload(),
				),
				422
			);
		}
		CheckoutSessionService::sync_discounts_from_cart();
		wp_send_json_success(
			array(
				'sub_action'         => 'remove_gift_card',
				'gift_card_code'     => $code,
				'message'            => __( 'Подарочная карта снята.', 'mp-custom-checkout' ),
				'applied_gift_cards' => array_values( $integration->get_applied_gift_cards() ),
				'cart'               => CheckoutRouteContext::get_cart_data(),
				'flow'               => self::build_flow_payload(),
			)
		);
	}
	private static function extract_coupon_notice_message( bool $clear_after = false ): string { if ( ! function_exists( 'wc_get_notices' ) ) { return ''; } $errors = wc_get_notices( 'error' ); if ( is_array( $errors ) && ! empty( $errors ) ) { $first = reset( $errors ); $text = is_array( $first ) && isset( $first['notice'] ) ? wp_strip_all_tags( (string) $first['notice'] ) : ''; if ( $clear_after && function_exists( 'wc_clear_notices' ) ) { wc_clear_notices(); } return trim( $text ); } $success = wc_get_notices( 'success' ); if ( is_array( $success ) && ! empty( $success ) ) { $first = reset( $success ); $text = is_array( $first ) && isset( $first['notice'] ) ? wp_strip_all_tags( (string) $first['notice'] ) : ''; if ( $clear_after && function_exists( 'wc_clear_notices' ) ) { wc_clear_notices(); } return trim( $text ); } if ( $clear_after && function_exists( 'wc_clear_notices' ) ) { wc_clear_notices(); } return ''; }

	/** @return array<string, mixed> */
	private static function build_flow_payload(): array {
		$flow = CheckoutSessionService::get_public_state();
		if ( empty( $flow ) ) {
			return array();
		}
		$step_manager           = new CheckoutStepManager( $flow );
		$scenario               = isset( $flow['scenario'] ) ? (string) $flow['scenario'] : '';
		$flow['current_step']   = (string) ( $step_manager->get_current_step_id() ?? '' );
		$flow['steps']          = array_values( $step_manager->get_registered_steps() );
		$flow['visible_steps']  = $step_manager->get_visible_step_ids();
		$flow['scenario_rules'] = CheckoutScenarioRules::build( $scenario );
		return $flow;
	}
}
