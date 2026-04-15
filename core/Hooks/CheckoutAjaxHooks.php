<?php
/**
 * AJAX endpoint'ы checkout (авторизованные и гости).
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout\Hooks;

use MP\CustomCheckout\DependencyFailureGuard;
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

		if ( 'session_get_state' === $sub_action ) {
			wp_send_json_success(
				array(
					'sub_action' => $sub_action,
					'flow'       => CheckoutSessionService::get_flow(),
				)
			);
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
}
