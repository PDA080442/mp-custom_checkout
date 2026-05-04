<?php
/**
 * AJAX endpoint'ы checkout (авторизованные и гости).
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout\Checkout\Hooks;

use MP\CustomCheckout\DependencyFailureGuard;
use MP\CustomCheckout\Hooks\CheckoutRouteHooks;
use MP\CustomCheckout\Integrations\WooCommerce\CdekWcSessionBridge;
use MP\CustomCheckout\Integrations\WooCommerce\GiftCardIntegration;
use MP\CustomCheckout\Integrations\WooCommerce\WcCustomerShippingSync;
use MP\CustomCheckout\Routing\CheckoutDateAvailabilityEngine;
use MP\CustomCheckout\Routing\CheckoutRouteContext;
use MP\CustomCheckout\Routing\CheckoutScenarioRules;
use MP\CustomCheckout\Routing\CheckoutSuccessRouteConfig;
use MP\CustomCheckout\Checkout\Routing\CheckoutSessionService;
use MP\CustomCheckout\Routing\CheckoutStepManager;
use MP\CustomCheckout\Settings\DefaultFeatureFlagsRegistry;
use MP\CustomCheckout\Settings\FeatureFlagResolver;
use MP\CustomCheckout\Settings\OptionKeys;
use MP\CustomCheckout\Settings\SafeSettingsResolver;
use MP\CustomCheckout\Settings\ScenarioStepRegistry;

defined( 'ABSPATH' ) || exit;

final class CheckoutAjaxHooks {

	public const ACTION = 'mp_cc_checkout';

	private const CDEK_COUNTRY_OFFICES_TRANSIENT = 'mp_cc_cdek_offices_country';

	private const CDEK_COUNTRY_OFFICES_BACKUP_TRANSIENT = 'mp_cc_cdek_offices_country_backup';

	/**
	 * Устаревшая option-резервная копия (до v3.4 backup лежал в `wp_options`).
	 * Сохраняем имя только для одноразовой миграции/очистки.
	 */
	private const CDEK_COUNTRY_OFFICES_BACKUP_OPTION_LEGACY = 'mp_cc_cdek_offices_country_backup';

	public static function register(): void {
		add_action( 'wp_ajax_' . self::ACTION, array( __CLASS__, 'handle' ) );
		add_action( 'wp_ajax_nopriv_' . self::ACTION, array( __CLASS__, 'handle' ) );
	}

	public static function handle(): void {
		if ( ! DependencyFailureGuard::is_woocommerce_integration_ready() ) {
			wp_send_json_error( array( 'message' => __( 'WooCommerce недоступен.', 'mp-custom-checkout' ) ), 503 );
		}
		if ( function_exists( 'wc_load_cart' ) ) {
			try {
				wc_load_cart();
			} catch ( \Throwable $cart_load_e ) {
				do_action(
					'mp_custom_checkout_log',
					'warning',
					'[ajax] wc_load_cart_failed',
					array(
						'source'          => 'ajax',
						'event_type'      => 'wc_load_cart_failed',
						'exception_class' => get_class( $cart_load_e ),
						'exception_msg'   => substr( (string) $cart_load_e->getMessage(), 0, 200 ),
					)
				);
			}
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
			$current_step_id = $manager->get_current_step_id();
			$visible         = $manager->get_visible_step_ids();
			$target_idx      = array_search( $step_id, $visible, true );
			$current_idx     = null !== $current_step_id ? array_search( $current_step_id, $visible, true ) : false;
			if (
				'address_delivery' === $current_step_id
				&& false !== $current_idx
				&& false !== $target_idx
				&& (int) $target_idx > (int) $current_idx
			) {
				self::assert_pvz_has_office_or_fail( is_array( $flow ) ? $flow : array(), 'address_delivery' );
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
			// recipient-only + skip_wc_resync=1: только запись в flow без calculate_totals (редкий путь).
			// Иначе при address_delivery + merge_contact_billing[] контакт пишется в том же запросе перед step_one — один WC sync.
			$skip_wc_resync = isset( $_POST['skip_wc_resync'] ) && '1' === (string) wp_unslash( (string) $_POST['skip_wc_resync'] );
			if ( $skip_wc_resync && 'recipient' !== $step_id ) {
				$skip_wc_resync = false;
			}
			if ( '' === $step_id ) {
				wp_send_json_error( array( 'code' => 'invalid_step_id', 'message' => __( 'Не указан шаг checkout.', 'mp-custom-checkout' ) ), 400 );
			}
			$answers = self::sanitize_payload_shape( is_array( $answers ) ? $answers : array(), 4, 80 );
			$merge_contact_billing = array();
			if ( 'address_delivery' === $step_id && isset( $_POST['merge_contact_billing'] ) && is_array( $_POST['merge_contact_billing'] ) ) {
				$merge_contact_billing = self::sanitize_payload_shape( wp_unslash( $_POST['merge_contact_billing'] ), 4, 80 );
			}
			if ( 'address_delivery' === $step_id && ! empty( $merge_contact_billing ) ) {
				CheckoutSessionService::set_step_answers( 'recipient', $merge_contact_billing );
			}
			if ( in_array( $step_id, array( 'address_delivery', 'date', 'conditions' ), true ) ) {
				$flow          = CheckoutSessionService::get_flow();
				$existing_date = array();
				if ( is_array( $flow ) && isset( $flow['answers']['date_conditions'] ) && is_array( $flow['answers']['date_conditions'] ) ) {
					$existing_date = $flow['answers']['date_conditions'];
				}
				if ( array_key_exists( 'cdek_office_code', $existing_date ) ) {
					$had_legacy_office = '' !== trim( (string) ( $existing_date['cdek_office_code'] ?? '' ) );
					unset( $existing_date['cdek_office_code'] );
					if ( $had_legacy_office ) {
						do_action(
							'mp_custom_checkout_log',
							'warning',
							'[pvz] existing_date_office_dropped_pre_merge',
							array(
								'step_id'   => $step_id,
								'had_value' => true,
							)
						);
					}
				}
				// Частичный payload (напр. только с шага «условия») дополняем сохранённым date_conditions.
				$answers = array_replace_recursive( $existing_date, $answers );
				// Дата выбирается на отдельном шаге (если включён). Для address_delivery валидируем только доставку.
				if ( in_array( $step_id, array( 'date', 'conditions' ), true ) || ! empty( $answers['selected_date'] ) ) {
					if ( ! self::validate_date_answers_payload( $answers ) ) {
						$message = SafeSettingsResolver::get( 'step_3.copy.errors.invalid_date', __( 'Выбранная дата недоступна. Обновите шаг и выберите другую дату.', 'mp-custom-checkout' ) );
						$message = is_string( $message ) && '' !== trim( $message ) ? $message : __( 'Выбранная дата недоступна. Обновите шаг и выберите другую дату.', 'mp-custom-checkout' );
						wp_send_json_error( array( 'code' => 'invalid_date_selection', 'message' => $message ), 422 );
					}
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
			if ( ! $skip_wc_resync ) {
				WcCustomerShippingSync::after_session_set_answers( $step_id );
			}
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
			WcCustomerShippingSync::after_session_set_scenario();
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
		if ( 'cdek_get_offices' === $sub_action ) {
			self::handle_cdek_get_offices();
		}
		if ( 'cdek_set_office' === $sub_action ) {
			self::handle_cdek_set_office();
		}
		if ( 'session_abandon' === $sub_action ) {
			CheckoutSessionService::clear_on_abandoned_flow();
			wp_send_json_success( array( 'sub_action' => $sub_action, 'cleared' => true ) );
		}
		return false;
	}

	private static function is_session_sub_action( string $sub_action ): bool {
		return in_array( $sub_action, array( 'session_set_step', 'session_set_answers', 'session_set_scenario', 'session_get_state', 'session_abandon', 'update_quantity', 'remove_item', 'validation_log', 'apply_coupon', 'remove_coupon', 'apply_gift_card', 'remove_gift_card', 'cdek_get_offices', 'cdek_set_office', 'set_payment_gateway', 'gateway_render_diagnostics', 'submit_payment', 'client_error_log', 'ajax_error_log' ), true );
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
		$available = self::get_available_payment_gateways_in_checkout_context( $pm );
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
		CheckoutSessionService::set_step_answers( 'payment', $contact );
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
	 * Возвращает список офисов СДЭК для города (JSON-массив для `officesRaw` виджета).
	 * Не меняет flow; синхронизация с эталонным `update_checkout` официального плагина.
	 *
	 * Режим `mode=country`: все ПВЗ РФ для карты (кэш transient + резерв в option при сбое API).
	 */
	private static function handle_cdek_get_offices(): void {
		$mode = isset( $_POST['mode'] ) ? sanitize_key( wp_unslash( (string) $_POST['mode'] ) ) : '';

		if ( 'country' === $mode ) {
			self::handle_cdek_get_offices_country_mode();
			return;
		}

		$city     = isset( $_POST['city'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['city'] ) ) : '';
		$postcode = isset( $_POST['postcode'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['postcode'] ) ) : '';

		if ( '' === $city ) {
			wp_send_json_success(
				array(
					'sub_action'      => 'cdek_get_offices',
					'offices_raw'     => array(),
					'offices_count'   => 0,
					'city'            => '',
					'postcode'        => $postcode,
				)
			);
		}

		if ( ! self::ensure_cdek_classes_loaded() ) {
			do_action(
				'mp_custom_checkout_log',
				'warning',
				'[pvz] cdek_api_unavailable',
				array(
					'source'     => 'ajax',
					'event_type' => 'cdek_api_unavailable',
					'mode'       => 'city',
				)
			);
			wp_send_json_success(
				array(
					'sub_action'    => 'cdek_get_offices',
					'offices_raw'   => array(),
					'offices_count' => 0,
					'city'          => $city,
					'postcode'      => $postcode,
					'fetch_error'   => true,
					'reason'        => 'cdek_api_unavailable',
				)
			);
		}

		try {
			$api    = new \Cdek\CdekApi();
			$parsed = array();
			$code   = $api->cityCodeGet( $city, '' !== $postcode ? $postcode : null );
			if ( null !== $code ) {
				$raw = $api->officeListRaw( $code );
				if ( is_string( $raw ) && '' !== $raw ) {
					$decoded = json_decode( $raw, true );
					$parsed  = is_array( $decoded ) ? $decoded : array();
				}
			}
			wp_send_json_success(
				array(
					'sub_action'    => 'cdek_get_offices',
					'offices_raw'   => $parsed,
					'offices_count' => count( $parsed ),
					'city'          => $city,
					'postcode'      => $postcode,
				)
			);
		} catch ( \Throwable $e ) {
			do_action(
				'mp_custom_checkout_log',
				'warning',
				'[pvz] cdek_get_offices_failed',
				array(
					'source'          => 'ajax',
					'event_type'      => 'cdek_get_offices_failed',
					'exception_class' => get_class( $e ),
					'city_fp'         => substr( $city, 0, 40 ),
				)
			);
			wp_send_json_error(
				array(
					'code'    => 'cdek_offices_failed',
					'message' => __( 'Не удалось загрузить список пунктов СДЭК.', 'mp-custom-checkout' ),
				),
				500
			);
		}
	}

	/**
	 * AJAX: все ПВЗ РФ для нативной карты СДЭК (transient + один запрос к /deliverypoints).
	 *
	 * Контракт ответа на пустые/ошибочные данные — `success: true, offices_raw: [], fetch_error: bool`,
	 * чтобы клиент мог уйти в city-based fallback без 500.
	 */
	private static function handle_cdek_get_offices_country_mode(): void {
		if ( ! self::ensure_cdek_classes_loaded() ) {
			do_action(
				'mp_custom_checkout_log',
				'warning',
				'[pvz] cdek_api_unavailable',
				array(
					'source'     => 'ajax',
					'event_type' => 'cdek_api_unavailable',
					'mode'       => 'country',
				)
			);
			$stale = self::get_country_offices_backup();
			if ( count( $stale ) > 0 ) {
				wp_send_json_success(
					array(
						'sub_action'    => 'cdek_get_offices',
						'mode'          => 'country',
						'offices_raw'   => $stale,
						'offices_count' => count( $stale ),
						'city'          => '',
						'postcode'      => '',
						'from_stale'    => true,
						'fetch_error'   => true,
						'reason'        => 'cdek_api_unavailable',
					)
				);
			}
			wp_send_json_success(
				array(
					'sub_action'    => 'cdek_get_offices',
					'mode'          => 'country',
					'offices_raw'   => array(),
					'offices_count' => 0,
					'city'          => '',
					'postcode'      => '',
					'fetch_error'   => true,
					'reason'        => 'cdek_api_unavailable',
				)
			);
		}

		$ttl = (int) apply_filters( 'mp_custom_checkout_cdek_country_offices_ttl', 12 * HOUR_IN_SECONDS );
		if ( $ttl < 60 ) {
			$ttl = 12 * HOUR_IN_SECONDS;
		}

		$cached = get_transient( self::CDEK_COUNTRY_OFFICES_TRANSIENT );
		if ( is_array( $cached ) && count( $cached ) > 0 ) {
			wp_send_json_success(
				array(
					'sub_action'    => 'cdek_get_offices',
					'mode'          => 'country',
					'offices_raw'   => $cached,
					'offices_count' => count( $cached ),
					'city'          => '',
					'postcode'      => '',
					'from_cache'    => true,
				)
			);
		}

		try {
			$result = self::fetch_country_offices_from_cdek_api();
			$all    = $result['offices'];
			$n      = count( $all );

			do_action(
				'mp_custom_checkout_log',
				'info',
				'[pvz] cdek_get_offices_country_ok',
				array(
					'source'      => 'ajax',
					'event_type'  => 'cdek_get_offices_country_ok',
					'count'       => $n,
					'body_bytes'  => $result['body_bytes'],
				)
			);

			if ( $n > 0 ) {
				set_transient( self::CDEK_COUNTRY_OFFICES_TRANSIENT, $all, $ttl );
				set_transient( self::CDEK_COUNTRY_OFFICES_BACKUP_TRANSIENT, $all, 30 * DAY_IN_SECONDS );
				if ( false !== get_option( self::CDEK_COUNTRY_OFFICES_BACKUP_OPTION_LEGACY, false ) ) {
					delete_option( self::CDEK_COUNTRY_OFFICES_BACKUP_OPTION_LEGACY );
				}
				wp_send_json_success(
					array(
						'sub_action'    => 'cdek_get_offices',
						'mode'          => 'country',
						'offices_raw'   => $all,
						'offices_count' => $n,
						'city'          => '',
						'postcode'      => '',
					)
				);
			}

			$stale = self::get_country_offices_backup();
			if ( count( $stale ) > 0 ) {
				wp_send_json_success(
					array(
						'sub_action'    => 'cdek_get_offices',
						'mode'          => 'country',
						'offices_raw'   => $stale,
						'offices_count' => count( $stale ),
						'city'          => '',
						'postcode'      => '',
						'from_stale'    => true,
						'fetch_error'   => true,
					)
				);
			}

			wp_send_json_success(
				array(
					'sub_action'    => 'cdek_get_offices',
					'mode'          => 'country',
					'offices_raw'   => array(),
					'offices_count' => 0,
					'city'          => '',
					'postcode'      => '',
					'fetch_error'   => true,
					'reason'        => 'empty_response',
				)
			);
		} catch ( \Throwable $e ) {
			do_action(
				'mp_custom_checkout_log',
				'warning',
				'[pvz] cdek_get_offices_country_failed',
				array(
					'source'          => 'ajax',
					'event_type'      => 'cdek_get_offices_country_failed',
					'exception_class' => get_class( $e ),
					'exception_msg'   => substr( (string) $e->getMessage(), 0, 200 ),
				)
			);
			$stale = self::get_country_offices_backup();
			if ( count( $stale ) > 0 ) {
				wp_send_json_success(
					array(
						'sub_action'    => 'cdek_get_offices',
						'mode'          => 'country',
						'offices_raw'   => $stale,
						'offices_count' => count( $stale ),
						'city'          => '',
						'postcode'      => '',
						'from_stale'    => true,
						'fetch_error'   => true,
					)
				);
				return;
			}
			wp_send_json_success(
				array(
					'sub_action'    => 'cdek_get_offices',
					'mode'          => 'country',
					'offices_raw'   => array(),
					'offices_count' => 0,
					'city'          => '',
					'postcode'      => '',
					'fetch_error'   => true,
					'reason'        => 'api_exception',
				)
			);
		}
	}

	/**
	 * Загружает все ПВЗ РФ из CDEK API v2 (один запрос к `/v2/deliverypoints`).
	 *
	 * Параметры подтверждены тремя независимыми SDK (cdek-it/sdk2.0, AntistressStore/cdek-sdk-v2,
	 * cdek-simple-api): `country_code` — строка ISO 3166-1 alpha-2; пагинация (`size`/`page`)
	 * у этого эндпоинта не поддерживается (есть только у `/location/cities` и `/location/regions`).
	 *
	 * @return array{offices: array<int, mixed>, body_bytes: int}
	 *
	 * @throws \Throwable
	 */
	private static function fetch_country_offices_from_cdek_api(): array {
		if ( function_exists( 'wp_raise_memory_limit' ) ) {
			wp_raise_memory_limit( 'admin' );
		}

		$shipping = \Cdek\ShippingMethod::factory();
		if ( ! $shipping->test_mode ) {
			$api_url = \Cdek\Config::API_URL;
		} else {
			/** @noinspection GlobalVariableUsageInspection */
			$api_url = isset( $_ENV['CDEK_REST_API'] ) ? (string) $_ENV['CDEK_REST_API'] : \Cdek\Config::TEST_API_URL;
		}

		$token_storage = new \Cdek\Helpers\LegacyTokenStorage();
		$token         = $token_storage->getToken();

		$response = \Cdek\Transport\HttpClient::sendJsonRequest(
			"{$api_url}deliverypoints",
			'GET',
			$token,
			array(
				'country_code' => 'RU',
				'type'         => 'ALL',
				'lang'         => 'rus',
			)
		);

		$body       = (string) $response->body();
		$body_bytes = strlen( $body );
		$offices    = self::decode_cdek_deliverypoints_page( $body );

		return array(
			'offices'    => $offices,
			'body_bytes' => $body_bytes,
		);
	}

	/**
	 * Гарантирует, что классы плагина СДЭК (`\Cdek\CdekApi`, `\Cdek\Transport\HttpClient`)
	 * подключены. Сначала пробует обычный autoload (`class_exists` без второго аргумента),
	 * затем при необходимости форсирует загрузку через активные плагины WP.
	 *
	 * Корень проблемы на проде: `class_exists(..., false)` без autoload возвращает false,
	 * пока плагин CDEK не подгружен лениво — сервер отдавал 503, фронт уходил в fallback.
	 */
	private static function ensure_cdek_classes_loaded(): bool {
		if ( class_exists( '\\Cdek\\CdekApi' ) && class_exists( '\\Cdek\\Transport\\HttpClient' ) ) {
			return true;
		}

		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$candidates = array();
		if ( function_exists( 'wp_get_active_and_valid_plugins' ) ) {
			$candidates = wp_get_active_and_valid_plugins();
		}
		if ( empty( $candidates ) && defined( 'WP_PLUGIN_DIR' ) ) {
			foreach ( array( 'cdekdelivery', 'cdek-wordpress-plugin', 'cdek-delivery' ) as $slug ) {
				$dir = WP_PLUGIN_DIR . '/' . $slug;
				if ( is_dir( $dir ) ) {
					$entry = $dir . '/' . $slug . '.php';
					if ( file_exists( $entry ) ) {
						$candidates[] = $entry;
					}
				}
			}
		}

		foreach ( $candidates as $plugin_file ) {
			if ( ! is_string( $plugin_file ) || '' === $plugin_file ) {
				continue;
			}
			if ( false === stripos( $plugin_file, 'cdek' ) ) {
				continue;
			}
			if ( ! file_exists( $plugin_file ) ) {
				continue;
			}
			try {
				include_once $plugin_file;
			} catch ( \Throwable $e ) {
				do_action(
					'mp_custom_checkout_log',
					'warning',
					'[pvz] cdek_plugin_include_failed',
					array(
						'source'          => 'ajax',
						'event_type'      => 'cdek_plugin_include_failed',
						'plugin_file'     => $plugin_file,
						'exception_class' => get_class( $e ),
						'exception_msg'   => substr( (string) $e->getMessage(), 0, 200 ),
					)
				);
			}
			if ( class_exists( '\\Cdek\\CdekApi' ) && class_exists( '\\Cdek\\Transport\\HttpClient' ) ) {
				return true;
			}
		}

		return class_exists( '\\Cdek\\CdekApi' ) && class_exists( '\\Cdek\\Transport\\HttpClient' );
	}

	/**
	 * Возвращает транзиентный backup; если его нет — пытается мигрировать со старой option и удаляет её.
	 *
	 * @return array<int, mixed>
	 */
	private static function get_country_offices_backup(): array {
		$transient_backup = get_transient( self::CDEK_COUNTRY_OFFICES_BACKUP_TRANSIENT );
		if ( is_array( $transient_backup ) && count( $transient_backup ) > 0 ) {
			return $transient_backup;
		}

		$legacy = get_option( self::CDEK_COUNTRY_OFFICES_BACKUP_OPTION_LEGACY, false );
		if ( is_array( $legacy ) && count( $legacy ) > 0 ) {
			set_transient( self::CDEK_COUNTRY_OFFICES_BACKUP_TRANSIENT, $legacy, 30 * DAY_IN_SECONDS );
			delete_option( self::CDEK_COUNTRY_OFFICES_BACKUP_OPTION_LEGACY );
			return $legacy;
		}

		return array();
	}

	/**
	 * @return array<int, mixed>
	 */
	private static function decode_cdek_deliverypoints_page( string $body ): array {
		$decoded = json_decode( $body, true );
		if ( ! is_array( $decoded ) ) {
			return array();
		}
		if ( array() === $decoded ) {
			return array();
		}
		$keys = array_keys( $decoded );
		$is_list = $keys === range( 0, count( $decoded ) - 1 );
		if ( $is_list ) {
			return $decoded;
		}
		foreach ( array( 'entity', 'deliverypoints', 'items', 'data' ) as $k ) {
			if ( isset( $decoded[ $k ] ) && is_array( $decoded[ $k ] ) ) {
				$inner = $decoded[ $k ];
				$ik    = array_keys( $inner );
				if ( $ik === range( 0, count( $inner ) - 1 ) ) {
					return $inner;
				}
			}
		}

		return array();
	}

	/**
	 * Сохраняет код ПВЗ СДЭК в flow (`answers.step_one.cdek_office_code`) и в WC-сессии плагина (`official_cdek_office_code`).
	 *
	 * Контракт полей и жизненный цикл: docs/pvz-data-contract.md (§29.1).
	 */
	private static function handle_cdek_set_office(): void {
		$flow          = CheckoutSessionService::get_flow();
		$step_one      = isset( $flow['answers']['step_one'] ) && is_array( $flow['answers']['step_one'] ) ? $flow['answers']['step_one'] : array();
		$step_one_before = $step_one;
		$code          = isset( $_POST['office_code'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['office_code'] ) ) : '';
		$posted_ctx    = isset( $_POST['context_id'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['context_id'] ) ) : '';

		if ( '' !== $code && ! self::is_valid_cdek_office_code_format( $code ) ) {
			$office_fp = substr( $code, 0, 4 ) . ':' . (string) strlen( $code );
			do_action(
				'mp_custom_checkout_log',
				'warning',
				'[pvz] office_save_failed',
				array(
					'source'             => 'ajax',
					'event_type'         => 'pvz_office_save_failed',
					'context_id_posted'  => $posted_ctx,
					'office_fp'          => $office_fp,
					'reason'             => 'invalid_office_code_format',
				)
			);
			wp_send_json_error(
				array(
					'code'    => 'invalid_office_code',
					'message' => __( 'Некорректный код пункта выдачи.', 'mp-custom-checkout' ),
				),
				400
			);
		}

		$office_details_post = array();
		if ( isset( $_POST['office_details'] ) && is_array( $_POST['office_details'] ) ) {
			$office_details_post = self::sanitize_cdek_office_details_from_post( wp_unslash( $_POST['office_details'] ) );
		}

		if ( '' === $code ) {
			// Явная пустая строка — иначе array_replace в set_step_answers оставит старый код (ключ из unset отсутствует в payload).
			$step_one['cdek_office_code'] = '';
			$step_one['cdek_office']     = array();
		} else {
			$step_one['cdek_office_code'] = $code;
			if ( ! empty( $office_details_post ) ) {
				$office_details_post['code'] = $code;
				$step_one['cdek_office']     = $office_details_post;
			}
			// Если office_details не прислали — не трогаем ключ cdek_office: merge сохранит прежнее значение (§29.1).
		}
		CheckoutSessionService::set_step_answers( ScenarioStepRegistry::STEP_ADDRESS_DELIVERY, $step_one );
		$flow_ctx = CheckoutSessionService::get_flow();
		$delivery = CdekWcSessionBridge::get_merged_delivery_answers( is_array( $flow_ctx ) ? $flow_ctx : array() );
		$office_fp  = '' === $code ? 'cleared' : ( substr( $code, 0, 4 ) . ':' . (string) strlen( $code ) );
		do_action(
			'mp_custom_checkout_log',
			'info',
			'[pvz] office_session_update',
			array(
				'source'              => 'ajax',
				'event_type'          => 'pvz_office_saved',
				'context_id_posted'   => $posted_ctx,
				'context_id_flow'     => is_array( $flow_ctx ) && isset( $flow_ctx['context_id'] ) ? (string) $flow_ctx['context_id'] : '',
				'shipping_method_id'  => isset( $delivery['shipping_method_id'] ) ? (string) $delivery['shipping_method_id'] : '',
				'office_fp'           => $office_fp,
			)
		);
		$wc_sync_warning = '';
		try {
			WcCustomerShippingSync::after_session_set_answers( ScenarioStepRegistry::STEP_ADDRESS_DELIVERY );
		} catch ( \Throwable $e ) {
			// Код ПВЗ уже сохранён в session/flow — это главный side-effect этого AJAX.
			// Падение WC-sync (recalculate_totals, shipping-плагины, customer->save) логируем как warning,
			// но НЕ откатываем сохранение и НЕ возвращаем 500: иначе пользователь получит "не удалось сохранить"
			// при том, что код фактически записан и при следующем шаге будет подхвачен.
			$flow_after      = CheckoutSessionService::get_flow();
			$wc_sync_warning = (string) $e->getMessage();
			do_action(
				'mp_custom_checkout_log',
				'warning',
				'[pvz] office_save_wc_sync_failed_soft',
				array(
					'source'           => 'ajax',
					'event_type'       => 'pvz_office_wc_sync_soft_fail',
					'exception_class'  => get_class( $e ),
					'exception_msg'    => substr( $wc_sync_warning, 0, 300 ),
					'office_fp'        => $office_fp,
					'context_id_flow'  => is_array( $flow_after ) && isset( $flow_after['context_id'] ) ? (string) $flow_after['context_id'] : '',
				)
			);
		}

		// build_flow_payload / get_cart_data берут актуальное состояние session — даже если sync упал,
		// они отдадут корректный flow с уже сохранённым cdek_office_code; их собственное падение
		// (например, из-за внешнего плагина в фильтрах) ловим тут, чтобы не возвращать 500 на
		// уже фактически выполненное сохранение кода ПВЗ. На фронте `if (d.flow)` тогда упадёт
		// в ветку, где код просто записывается локально (`state.frontendStore.fulfillment.date.cdek_office_code = c`).
		$response = array(
			'sub_action' => 'cdek_set_office',
		);
		try {
			$response['flow'] = self::build_flow_payload();
		} catch ( \Throwable $flow_e ) {
			do_action(
				'mp_custom_checkout_log',
				'warning',
				'[pvz] office_save_flow_payload_failed',
				array(
					'source'          => 'ajax',
					'event_type'      => 'pvz_office_flow_payload_failed',
					'exception_class' => get_class( $flow_e ),
					'exception_msg'   => substr( (string) $flow_e->getMessage(), 0, 300 ),
				)
			);
		}
		try {
			$response['cart'] = CheckoutRouteContext::get_cart_data();
		} catch ( \Throwable $cart_e ) {
			do_action(
				'mp_custom_checkout_log',
				'warning',
				'[pvz] office_save_cart_payload_failed',
				array(
					'source'          => 'ajax',
					'event_type'      => 'pvz_office_cart_payload_failed',
					'exception_class' => get_class( $cart_e ),
					'exception_msg'   => substr( (string) $cart_e->getMessage(), 0, 300 ),
				)
			);
		}
		if ( '' !== $wc_sync_warning ) {
			$response['wc_sync_soft_fail'] = true;
		}
		wp_send_json_success( $response );
	}

	/**
	 * Санитизация деталей ПВЗ из POST для записи в answers.step_one.cdek_office.
	 *
	 * @param array<string, mixed> $raw
	 * @return array<string, string>
	 */
	private static function sanitize_cdek_office_details_from_post( array $raw ): array {
		$allowed = array( 'code', 'name', 'address', 'city', 'postal_code', 'region', 'country_code' );
		$out     = array();
		foreach ( $allowed as $key ) {
			if ( ! array_key_exists( $key, $raw ) ) {
				continue;
			}
			$val = sanitize_text_field( (string) $raw[ $key ] );
			if ( 'postal_code' === $key ) {
				$val = substr( $val, 0, 16 );
			}
			if ( '' !== $val ) {
				$out[ $key ] = $val;
			}
		}

		return $out;
	}

	/**
	 * Мягкая проверка формата кода ПВЗ СДЭК (§29.3): длина 1–32, безопасный charset.
	 *
	 * Реальные коды СДЭК встречаются в формате `MSK1234`, `KRR12.A1`, `kras-321` —
	 * допускаем буквы (любого регистра), цифры, `_`, `-`, `.`, чтобы не блокировать
	 * валидный код от свежей версии CDEKWidget на 400.
	 */
	private static function is_valid_cdek_office_code_format( string $code ): bool {
		$len = strlen( $code );
		if ( $len < 1 || $len > 32 ) {
			return false;
		}

		return (bool) preg_match( '/^[A-Za-z0-9_.\-]+$/', $code );
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
		$available = self::get_available_payment_gateways_in_checkout_context( $pm );
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
		self::assert_pvz_has_office_or_fail( is_array( $flow ) ? $flow : array(), 'confirm' );
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
		$available = self::get_available_payment_gateways_in_checkout_context( $pm );
		if ( ! isset( $available[ $gateway ] ) ) {
			wp_send_json_error( array( 'code' => 'gateway_not_available', 'message' => __( 'Выбранный способ оплаты недоступен.', 'mp-custom-checkout' ) ), 422 );
		}
		if ( ! self::is_contact_phone_acceptable( $contact ) ) {
			wp_send_json_error(
				array(
					'code'    => 'invalid_phone',
					'message' => __( 'Проверьте номер телефона: для выбранной страны укажите нужное количество цифр без кода страны.', 'mp-custom-checkout' ),
				),
				422
			);
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

	/**
	 * Проверка длины национальной части телефона по настройкам шага 4 (совпадает с фронтенд-валидацией).
	 *
	 * @param array<string, mixed> $contact
	 */
	private static function is_contact_phone_acceptable( array $contact ): bool {
		$merged = SafeSettingsResolver::get_merged();
		$s4     = isset( $merged[ OptionKeys::SECTION_STEP_4 ] ) && is_array( $merged[ OptionKeys::SECTION_STEP_4 ] )
			? $merged[ OptionKeys::SECTION_STEP_4 ]
			: array();
		$block  = isset( $s4['contact_block'] ) && is_array( $s4['contact_block'] ) ? $s4['contact_block'] : array();
		$vis    = isset( $block['field_visibility']['phone'] ) ? (bool) $block['field_visibility']['phone'] : true;
		if ( ! $vis ) {
			return true;
		}
		$req = isset( $block['field_required']['phone'] ) ? (bool) $block['field_required']['phone'] : true;

		$codes = isset( $block['phone_country_codes'] ) && is_array( $block['phone_country_codes'] ) ? $block['phone_country_codes'] : array();
		$iso   = isset( $contact['phone_country_iso'] ) ? strtoupper( sanitize_text_field( (string) $contact['phone_country_iso'] ) ) : '';
		if ( 2 !== strlen( $iso ) || 1 !== preg_match( '/^[A-Z]{2}$/', $iso ) ) {
			$iso = 'RU';
		}
		$need = 10;
		$dial = '+7';
		foreach ( $codes as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$row_iso = isset( $row['iso'] ) ? strtoupper( (string) $row['iso'] ) : '';
			if ( $row_iso === $iso ) {
				$need = isset( $row['national_digits'] ) ? max( 1, min( 15, (int) $row['national_digits'] ) ) : 10;
				$dial = isset( $row['dial'] ) ? (string) $row['dial'] : '+7';
				break;
			}
		}
		$constraints = isset( $block['validation_constraints'] ) && is_array( $block['validation_constraints'] )
			? $block['validation_constraints']
			: array();
		$override = isset( $constraints['phone_digits_override'] ) ? (int) $constraints['phone_digits_override'] : 0;
		if ( $override > 0 ) {
			$need = max( 1, min( 20, $override ) );
		}

		$nat = isset( $contact['billing_phone_national'] ) ? preg_replace( '/\D+/', '', (string) $contact['billing_phone_national'] ) : '';
		if ( '' === $nat && ! empty( $contact['billing_phone'] ) ) {
			$full        = preg_replace( '/\D+/', '', (string) $contact['billing_phone'] );
			$dial_digits = preg_replace( '/\D+/', '', $dial );
			if ( '' !== $dial_digits && 0 === strpos( $full, $dial_digits ) ) {
				$nat = substr( $full, strlen( $dial_digits ) );
			}
		}

		if ( '' === $nat ) {
			return ! $req;
		}

		return strlen( $nat ) === $need;
	}

	/**
	 * Переносит выбранные в сессии WC линии доставки с корзины на заказ (в т.ч. meta ставки для СДЭК).
	 */
	private static function copy_cart_shipping_to_order( \WC_Order $order ): void {
		if ( ! function_exists( 'WC' ) || ! WC()->shipping() ) {
			return;
		}
		$session = WC()->session;
		if ( ! $session instanceof \WC_Session ) {
			return;
		}
		$cdek_lines_added = 0;
		$packages = WC()->shipping()->get_packages();
		$chosen   = (array) $session->get( 'chosen_shipping_methods', array() );
		foreach ( $packages as $pkg_key => $package ) {
			if ( ! is_array( $package ) ) {
				continue;
			}
			$rates   = isset( $package['rates'] ) && is_array( $package['rates'] ) ? $package['rates'] : array();
			$pkg_idx = (int) $pkg_key;
			$rate_id = isset( $chosen[ $pkg_idx ] ) ? (string) $chosen[ $pkg_idx ] : '';
			if ( '' === $rate_id ) {
				continue;
			}
			if ( empty( $rates[ $rate_id ] ) || ! $rates[ $rate_id ] instanceof \WC_Shipping_Rate ) {
				continue;
			}
			$rate = $rates[ $rate_id ];
			$item = new \WC_Order_Item_Shipping();
			$item->set_props(
				array(
					'method_title' => $rate->get_label(),
					'method_id'    => $rate->get_method_id(),
					'instance_id'  => $rate->get_instance_id(),
					'total'        => wc_format_decimal( $rate->get_cost(), wc_get_price_decimals() ),
				)
			);
			$taxes = $rate->get_taxes();
			if ( is_array( $taxes ) && ! empty( $taxes ) ) {
				$item->set_taxes( array( 'total' => $taxes ) );
			}
			foreach ( $rate->get_meta_data() as $meta_obj ) {
				if ( $meta_obj instanceof \WC_Meta_Data ) {
					$data = $meta_obj->get_data();
					if ( isset( $data['key'] ) && '' !== (string) $data['key'] ) {
						$item->add_meta_data( (string) $data['key'], $data['value'] ?? '', true );
					}
				}
			}
			$order->add_item( $item );
			if ( 0 === strpos( (string) $rate_id, CdekWcSessionBridge::OFFICIAL_CDEK_PREFIX ) ) {
				$cdek_lines_added++;
				$office_meta_present = false;
				foreach ( $rate->get_meta_data() as $m ) {
					if ( ! $m instanceof \WC_Meta_Data ) {
						continue;
					}
					$k = (string) ( $m->get_data()['key'] ?? '' );
					if ( false !== strpos( $k, 'office_code' ) ) {
						$office_meta_present = true;
						break;
					}
				}
				do_action(
					'mp_custom_checkout_log',
					'info',
					'[pvz] order_shipping_line_persisted',
					array(
						'order_id'            => (int) $order->get_id(),
						'rate_id'             => (string) $rate_id,
						'instance_id'         => (string) $rate->get_instance_id(),
						'office_meta_present' => $office_meta_present,
					)
				);
			}
		}
		if ( 0 === $cdek_lines_added ) {
			$flow     = CheckoutSessionService::get_flow();
			$delivery = CdekWcSessionBridge::get_merged_delivery_answers( is_array( $flow ) ? $flow : array() );
			$ship_mid = isset( $delivery['shipping_method_id'] ) ? sanitize_key( (string) $delivery['shipping_method_id'] ) : '';
			if ( 'pvz' === $ship_mid ) {
				$tariff = isset( $delivery['shipping_tariff_id'] ) ? sanitize_key( (string) $delivery['shipping_tariff_id'] ) : '';
				do_action(
					'mp_custom_checkout_log',
					'warning',
					'[pvz] order_shipping_line_missing',
					array(
						'phase'           => 'copy',
						'order_id'        => (int) $order->get_id(),
						'expected_method' => 'pvz',
						'expected_tariff' => $tariff,
					)
				);
			}
		}
	}

	private static function create_order_from_cart_and_answers( array $contact, string $gateway ): ?\WC_Order {
		if ( ! function_exists( 'wc_create_order' ) || ! function_exists( 'WC' ) || ! WC()->cart instanceof \WC_Cart ) {
			return null;
		}
		WcCustomerShippingSync::before_create_order_from_cart();
		$flow_for_guard = CheckoutSessionService::get_flow();
		self::assert_pvz_rate_available_or_fail( is_array( $flow_for_guard ) ? $flow_for_guard : array() );
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
		self::copy_cart_shipping_to_order( $order );
		$order->set_payment_method( $gateway );
		$order->set_billing_first_name( isset( $contact['billing_first_name'] ) ? (string) $contact['billing_first_name'] : '' );
		$order->set_billing_last_name( isset( $contact['billing_last_name'] ) ? (string) $contact['billing_last_name'] : '' );
		$order->set_billing_email( isset( $contact['billing_email'] ) ? (string) $contact['billing_email'] : '' );
		$order->set_billing_phone( isset( $contact['billing_phone'] ) ? (string) $contact['billing_phone'] : '' );
		$billing_country = isset( $contact['country'] ) ? sanitize_text_field( (string) $contact['country'] ) : '';
		$order->set_billing_country( OrderMetaHooks::normalize_billing_country_value( $billing_country ) );
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

	/**
	 * Для кастомного checkout-route временно возвращаем is_checkout()=true,
	 * иначе часть шлюзов (особенно внешних) отфильтровываются как «не checkout контекст».
	 *
	 * @param \WC_Payment_Gateways $pm
	 * @return array<string, mixed>
	 */
	private static function get_available_payment_gateways_in_checkout_context( \WC_Payment_Gateways $pm ): array {
		// На странице кастомного checkout is_checkout_route() === true, и WooCommerce видит «как на checkout».
		// Запросы set_payment_gateway / submit_payment идут через admin-ajax.php: там is_checkout_route() === false,
		// и часть шлюзов (Robokassa и др.) отваливается из get_available_payment_gateways() из‑за проверок is_checkout().
		$force_checkout = self::should_force_wc_checkout_for_gateway_resolution();
		if ( $force_checkout ) {
			add_filter( 'woocommerce_is_checkout', '__return_true', PHP_INT_MAX );
		}
		try {
			$available = $pm->get_available_payment_gateways();
		} finally {
			if ( $force_checkout ) {
				remove_filter( 'woocommerce_is_checkout', '__return_true', PHP_INT_MAX );
			}
		}
		return is_array( $available ) ? $available : array();
	}

	/**
	 * Нужно ли подставить is_checkout()=true при разрешении списка шлюзов (как на нативном checkout).
	 */
	private static function should_force_wc_checkout_for_gateway_resolution(): bool {
		if ( CheckoutRouteHooks::is_checkout_route() ) {
			return true;
		}
		if ( ! function_exists( 'wp_doing_ajax' ) || ! wp_doing_ajax() ) {
			return false;
		}
		$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( (string) $_REQUEST['action'] ) ) : '';
		return self::ACTION === $action;
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

	/**
	 * §29.4: блокируем переход / оплату при выборе ПВЗ без кода офиса CDEK.
	 *
	 * @param array<string, mixed> $flow Raw flow from CheckoutSessionService::get_flow().
	 */
	private static function assert_pvz_has_office_or_fail( array $flow, string $log_step_id ): void {
		$delivery = CdekWcSessionBridge::get_merged_delivery_answers( $flow );
		$method   = isset( $delivery['shipping_method_id'] ) ? sanitize_key( (string) $delivery['shipping_method_id'] ) : '';
		if ( 'pvz' !== $method ) {
			return;
		}
		if ( ! FeatureFlagResolver::is_enabled( DefaultFeatureFlagsRegistry::FLAG_PVZ_OFFICE_REQUIRED, true ) ) {
			$ctx = isset( $_POST['context_id'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['context_id'] ) ) : '';
			do_action(
				'mp_custom_checkout_log',
				'info',
				'[pvz] office_required_flag_off_skip_validation',
				array(
					'step_id'    => sanitize_key( $log_step_id ),
					'context_id' => $ctx,
				)
			);
			return;
		}
		$office = isset( $delivery['cdek_office_code'] ) ? trim( (string) $delivery['cdek_office_code'] ) : '';
		if ( '' !== $office ) {
			return;
		}
		$ctx = isset( $_POST['context_id'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['context_id'] ) ) : '';
		do_action(
			'mp_custom_checkout_log',
			'warning',
			'[validation] step_failed',
			array(
				'step_id'             => sanitize_key( $log_step_id ),
				'errors'              => array( 'cdek_office_code' => 'pvz_required' ),
				'context_id'          => $ctx,
				'shipping_method_id'  => isset( $delivery['shipping_method_id'] ) ? (string) $delivery['shipping_method_id'] : '',
				'shipping_tariff_id'  => isset( $delivery['shipping_tariff_id'] ) ? (string) $delivery['shipping_tariff_id'] : '',
				'scenario'            => isset( $flow['scenario'] ) ? (string) $flow['scenario'] : '',
			)
		);
		wp_send_json_error(
			array(
				'code'    => 'pvz_required',
				'message' => __( 'Выберите пункт выдачи (ПВЗ), чтобы продолжить.', 'mp-custom-checkout' ),
			),
			422
		);
	}

	/**
	 * §29.6: метод pvz требует выбранную в сессии ставку official_cdek:* из пакета 0 (иначе заказ без shipping line).
	 *
	 * @param array<string, mixed> $flow Raw flow from CheckoutSessionService::get_flow().
	 */
	private static function assert_pvz_rate_available_or_fail( array $flow ): void {
		$delivery = CdekWcSessionBridge::get_merged_delivery_answers( $flow );
		$method   = isset( $delivery['shipping_method_id'] ) ? sanitize_key( (string) $delivery['shipping_method_id'] ) : '';
		if ( 'pvz' !== $method ) {
			return;
		}
		if ( ! function_exists( 'WC' ) || ! WC()->shipping() || ! WC()->session instanceof \WC_Session ) {
			do_action(
				'mp_custom_checkout_log',
				'warning',
				'[pvz] order_shipping_line_missing',
				array(
					'phase'   => 'guard',
					'step_id' => 'confirm',
					'reason'  => 'wc_session_or_shipping_unavailable',
				)
			);
			wp_send_json_error(
				array(
					'code'    => 'pvz_rate_unavailable',
					'message' => __( 'Ставка доставки ПВЗ недоступна. Обновите страницу или выберите другой способ доставки.', 'mp-custom-checkout' ),
				),
				422
			);
		}
		$tariff           = sanitize_key( (string) ( $delivery['shipping_tariff_id'] ?? '' ) );
		$expected_rate_id = CdekWcSessionBridge::resolve_wc_rate_id_from_catalog( $method, $tariff );
		$chosen           = (array) WC()->session->get( 'chosen_shipping_methods', array() );
		$picked           = isset( $chosen[0] ) ? (string) $chosen[0] : '';
		$packages         = WC()->shipping()->get_packages();
		$package0         = isset( $packages[0] ) && is_array( $packages[0] ) ? $packages[0] : array();
		$rates            = isset( $package0['rates'] ) && is_array( $package0['rates'] ) ? $package0['rates'] : array();
		$prefix           = CdekWcSessionBridge::OFFICIAL_CDEK_PREFIX;
		$picked_ok        = '' !== $picked && 0 === strpos( $picked, $prefix ) && isset( $rates[ $picked ] );
		if ( $picked_ok ) {
			return;
		}
		$rate_keys = array_keys( $rates );
		do_action(
			'mp_custom_checkout_log',
			'warning',
			'[pvz] order_shipping_line_missing',
			array(
				'phase'              => 'guard',
				'step_id'            => 'confirm',
				'expected_rate_id'   => $expected_rate_id,
				'chosen_method_id'   => $picked,
				'rate_id_count'      => count( $rate_keys ),
				'rate_id_sample'     => array_slice( array_map( 'strval', $rate_keys ), 0, 15 ),
			)
		);
		wp_send_json_error(
			array(
				'code'    => 'pvz_rate_unavailable',
				'message' => __( 'Ставка доставки ПВЗ недоступна. Обновите страницу или выберите другой способ доставки.', 'mp-custom-checkout' ),
			),
			422
		);
	}

	/** @param array<string, mixed> $answers */
	private static function validate_shipping_answers_payload( array $answers ): bool {
		if ( ! FeatureFlagResolver::is_enabled( DefaultFeatureFlagsRegistry::FLAG_CHECKOUT_UI_V2, false ) ) {
			return true;
		}
		$method_id = isset( $answers['shipping_method_id'] ) ? sanitize_key( (string) $answers['shipping_method_id'] ) : '';
		if ( '' === $method_id ) {
			// Пустой черновик шага 1 (сброс метода, автосейв только адреса) — не считаем ошибкой каталога.
			return true;
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
				'pickup'               => array( 'tariffs' => array(), 'visibility_scenarios' => array( 'pickup', 'krasnoyarsk_delivery', 'other_city_delivery' ) ),
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
