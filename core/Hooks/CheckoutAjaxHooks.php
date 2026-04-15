<?php
/**
 * AJAX endpoint'ы checkout (авторизованные и гости).
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout\Hooks;

use MP\CustomCheckout\DependencyFailureGuard;
use MP\CustomCheckout\Routing\CheckoutRouteContext;
use MP\CustomCheckout\Routing\CheckoutSessionService;
use MP\CustomCheckout\Routing\CheckoutStepManager;

defined( 'ABSPATH' ) || exit;

/**
 * Class CheckoutAjaxHooks
 */
final class CheckoutAjaxHooks {

	public const ACTION = 'mp_cc_checkout';

	/**
	 * Регистрация wp_ajax_*.
	 */
	public static function register(): void {
		add_action( 'wp_ajax_' . self::ACTION, array( __CLASS__, 'handle' ) );
		add_action( 'wp_ajax_nopriv_' . self::ACTION, array( __CLASS__, 'handle' ) );
	}

	/**
	 * Общая точка входа AJAX (логика будет расширена).
	 */
	public static function handle(): void {
		if ( ! DependencyFailureGuard::is_woocommerce_integration_ready() ) {
			wp_send_json_error(
				array( 'message' => __( 'WooCommerce недоступен.', 'mp-custom-checkout' ) ),
				503
			);
		}

		check_ajax_referer( 'mp_cc_checkout', 'nonce' );

		$sub_action = isset( $_POST['sub_action'] ) ? sanitize_key( wp_unslash( $_POST['sub_action'] ) ) : '';

		if ( self::handle_session_sub_action( $sub_action ) ) {
			return;
		}

		/**
		 * Обработка поддействий checkout AJAX (подписки реализуют сценарии).
		 *
		 * @param string $sub_action Поддействие.
		 */
		do_action( 'mp_custom_checkout_ajax_request', $sub_action );

		wp_send_json_success( array( 'sub_action' => $sub_action ) );
	}

	/**
	 * Сохранение состояния checkout-flow между шагами.
	 */
	private static function handle_session_sub_action( string $sub_action ): bool {
		if ( self::is_session_sub_action( $sub_action ) && ! self::validate_context_id() ) {
			wp_send_json_error(
				array( 'code' => 'stale_context', 'message' => __( 'Сессия checkout устарела. Обновите страницу.', 'mp-custom-checkout' ) ),
				409
			);
		}

		if ( 'session_set_step' === $sub_action ) {
			$step_id = isset( $_POST['step_id'] ) ? sanitize_key( wp_unslash( $_POST['step_id'] ) ) : '';
			if ( '' === $step_id ) {
				wp_send_json_error(
					array( 'code' => 'invalid_step_id', 'message' => __( 'Не указан шаг checkout.', 'mp-custom-checkout' ) ),
					400
				);
			}

			$manager = new CheckoutStepManager();
			if ( ! $manager->can_navigate_to( $step_id ) ) {
				wp_send_json_error(
					array( 'code' => 'invalid_step_navigation', 'message' => __( 'Переход на указанный шаг недоступен.', 'mp-custom-checkout' ) ),
					400
				);
			}

			CheckoutSessionService::set_current_step( $step_id );
			wp_send_json_success(
				array(
					'sub_action'   => $sub_action,
					'current_step' => $step_id,
				)
			);
		}

		if ( 'session_set_answers' === $sub_action ) {
			$step_id = isset( $_POST['step_id'] ) ? sanitize_key( wp_unslash( $_POST['step_id'] ) ) : '';
			$answers = isset( $_POST['answers'] ) && is_array( $_POST['answers'] )
				? wp_unslash( $_POST['answers'] )
				: array();
			if ( '' === $step_id ) {
				wp_send_json_error(
					array( 'code' => 'invalid_step_id', 'message' => __( 'Не указан шаг checkout.', 'mp-custom-checkout' ) ),
					400
				);
			}

			CheckoutSessionService::set_step_answers( $step_id, is_array( $answers ) ? $answers : array() );
			wp_send_json_success(
				array(
					'sub_action' => $sub_action,
					'step_id'    => $step_id,
				)
			);
		}

		if ( 'session_set_scenario' === $sub_action ) {
			$scenario = isset( $_POST['scenario'] ) ? sanitize_key( wp_unslash( $_POST['scenario'] ) ) : '';
			if ( '' === $scenario ) {
				wp_send_json_error(
					array( 'code' => 'invalid_scenario', 'message' => __( 'Не указан сценарий оформления.', 'mp-custom-checkout' ) ),
					400
				);
			}

			CheckoutSessionService::set_scenario( $scenario );
			wp_send_json_success(
				array(
					'sub_action' => $sub_action,
					'scenario'   => $scenario,
				)
			);
		}

		if ( 'session_get_state' === $sub_action ) {
			wp_send_json_success(
				array(
					'sub_action' => $sub_action,
					'flow'       => CheckoutSessionService::get_public_state(),
					'cart'       => CheckoutRouteContext::get_cart_data(),
				)
			);
		}

		if ( 'update_quantity' === $sub_action ) {
			self::handle_update_quantity();
		}

		if ( 'session_abandon' === $sub_action ) {
			CheckoutSessionService::clear_on_abandoned_flow();
			wp_send_json_success(
				array(
					'sub_action' => $sub_action,
					'cleared'    => true,
				)
			);
		}

		return false;
	}

	private static function is_session_sub_action( string $sub_action ): bool {
		return in_array(
			$sub_action,
			array( 'session_set_step', 'session_set_answers', 'session_set_scenario', 'session_get_state', 'session_abandon', 'update_quantity' ),
			true
		);
	}

	private static function validate_context_id(): bool {
		$posted_context = isset( $_POST['context_id'] ) ? sanitize_text_field( wp_unslash( $_POST['context_id'] ) ) : '';
		$flow           = CheckoutSessionService::get_public_state();
		if ( empty( $flow ) ) {
			return true;
		}

		return CheckoutSessionService::validate_context_id( $flow, $posted_context );
	}

	private static function handle_update_quantity(): void {
		$item_key = isset( $_POST['item_key'] ) ? wc_clean( wp_unslash( $_POST['item_key'] ) ) : '';
		$qty_raw  = isset( $_POST['quantity'] ) ? wp_unslash( $_POST['quantity'] ) : null;
		$qty      = is_numeric( $qty_raw ) ? (int) $qty_raw : 0;

		if ( '' === $item_key || $qty <= 0 ) {
			wp_send_json_error(
				array( 'code' => 'invalid_quantity_payload', 'message' => __( 'Некорректные данные количества.', 'mp-custom-checkout' ) ),
				400
			);
		}

		if ( ! function_exists( 'WC' ) || ! WC()->cart instanceof \WC_Cart ) {
			wp_send_json_error(
				array( 'code' => 'cart_unavailable', 'message' => __( 'Корзина недоступна.', 'mp-custom-checkout' ) ),
				503
			);
		}

		$cart = WC()->cart;
		$item = $cart->get_cart_item( $item_key );
		if ( ! is_array( $item ) || empty( $item['data'] ) || ! $item['data'] instanceof \WC_Product ) {
			wp_send_json_error(
				array( 'code' => 'cart_item_not_found', 'message' => __( 'Позиция корзины не найдена.', 'mp-custom-checkout' ) ),
				404
			);
		}

		/** @var \WC_Product $product */
		$product = $item['data'];
		$min_qty = max( 1, (int) $product->get_min_purchase_quantity() );
		$max_qty = (int) $product->get_max_purchase_quantity();
		if ( $max_qty <= 0 ) {
			$max_qty = 9999;
		}

		if ( $qty < $min_qty || $qty > $max_qty ) {
			wp_send_json_error(
				array(
					'code'    => 'quantity_out_of_bounds',
					'message' => sprintf(
						/* translators: 1: min qty, 2: max qty */
						__( 'Допустимое количество: от %1$d до %2$d.', 'mp-custom-checkout' ),
						$min_qty,
						$max_qty
					),
					'min'     => $min_qty,
					'max'     => $max_qty,
				),
				400
			);
		}

		$result = $cart->set_quantity( $item_key, $qty, true );
		if ( false === $result ) {
			do_action(
				'mp_custom_checkout_log',
				'error',
				'[cart_quantity] update_failed',
				array( 'item_key' => $item_key, 'quantity' => $qty )
			);
			wp_send_json_error(
				array( 'code' => 'update_failed', 'message' => __( 'Не удалось обновить количество.', 'mp-custom-checkout' ) ),
				500
			);
		}

		$updated_item = $cart->get_cart_item( $item_key );
		$line_subtotal = '';
		if ( is_array( $updated_item ) && isset( $updated_item['data'] ) && $updated_item['data'] instanceof \WC_Product ) {
			$line_subtotal = $cart->get_product_subtotal( $updated_item['data'], (int) $qty );
		}

		wp_send_json_success(
			array(
				'sub_action' => 'update_quantity',
				'item'       => array(
					'key'           => $item_key,
					'quantity'      => $qty,
					'line_subtotal' => (string) $line_subtotal,
				),
				'cart'       => CheckoutRouteContext::get_cart_data(),
				'flow'       => CheckoutSessionService::get_public_state(),
			)
		);
	}
}
