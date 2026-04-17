<?php
/**
 * Интеграция с плагином PW Gift Cards (Pimwick Plugins).
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout\Integrations\WooCommerce;

defined( 'ABSPATH' ) || exit;

/**
 * Class GiftCardIntegration
 */
final class GiftCardIntegration {

	/**
	 * Основной класс плагина PW Gift Cards (если используется стандартная сборка).
	 */
	private const PW_GIFT_CARDS_CLASS = 'PW_Gift_Cards';

	/**
	 * Плагин PW Gift Cards активен и класс доступен.
	 */
	public function is_pw_gift_cards_available(): bool {
		return class_exists( self::PW_GIFT_CARDS_CLASS, false );
	}

	/**
	 * Применить подарочную карту через нативные методы PW Gift Cards.
	 *
	 * @return true|\WP_Error
	 */
	public function apply_gift_card( string $code ) {
		$gift_code = trim( (string) $code );
		if ( '' === $gift_code ) {
			return new \WP_Error( 'mp_cc_gift_card_empty', __( 'Введите код подарочной карты.', 'mp-custom-checkout' ) );
		}
		if ( ! $this->is_pw_gift_cards_available() ) {
			return new \WP_Error( 'mp_cc_pw_unavailable', __( 'Интеграция PW Gift Cards недоступна.', 'mp-custom-checkout' ) );
		}

		// Бесплатная версия PW: списание идёт через сессию, а не через «cart API» объекта PW_Gift_Cards.
		global $pw_gift_cards_redeeming;
		if ( isset( $pw_gift_cards_redeeming ) && is_object( $pw_gift_cards_redeeming ) && method_exists( $pw_gift_cards_redeeming, 'add_gift_card_to_session' ) ) {
			$session_result = $pw_gift_cards_redeeming->add_gift_card_to_session( $gift_code );
			if ( true === $session_result ) {
				return true;
			}
			if ( is_string( $session_result ) && '' !== $session_result ) {
				return new \WP_Error( 'mp_cc_pw_apply_failed', $session_result );
			}
		}

		$service = $this->resolve_pw_service();
		if ( ! $service ) {
			return new \WP_Error( 'mp_cc_pw_service_unavailable', __( 'Не удалось получить сервис PW Gift Cards.', 'mp-custom-checkout' ) );
		}

		$cart_api = $this->resolve_cart_api( $service );
		if ( ! $cart_api ) {
			return new \WP_Error( 'mp_cc_pw_cart_api_unavailable', __( 'Не найден API корзины PW Gift Cards.', 'mp-custom-checkout' ) );
		}

		$methods = array( 'apply_gift_card', 'redeem_gift_card', 'add_gift_card_to_cart', 'add_gift_card', 'apply' );
		foreach ( $methods as $method ) {
			if ( is_object( $cart_api ) && method_exists( $cart_api, $method ) ) {
				$result = $cart_api->{$method}( $gift_code );
				if ( false === $result ) {
					return new \WP_Error( 'mp_cc_pw_apply_failed', __( 'Не удалось применить подарочную карту.', 'mp-custom-checkout' ) );
				}
				return true;
			}
		}

		return new \WP_Error( 'mp_cc_pw_apply_method_not_found', __( 'Не найден метод применения подарочной карты в PW Gift Cards.', 'mp-custom-checkout' ) );
	}

	/**
	 * @return array<int, string>
	 */
	public function get_applied_gift_cards(): array {
		if ( ! $this->is_pw_gift_cards_available() ) {
			return array();
		}
		$from_session = $this->get_applied_gift_cards_from_pw_session();
		if ( ! empty( $from_session ) ) {
			return $from_session;
		}

		$service = $this->resolve_pw_service();
		$cart_api = $service ? $this->resolve_cart_api( $service ) : null;
		if ( ! $cart_api ) {
			return array();
		}

		$methods = array( 'get_applied_gift_cards', 'get_gift_cards', 'get_applied', 'get_cards' );
		foreach ( $methods as $method ) {
			if ( is_object( $cart_api ) && method_exists( $cart_api, $method ) ) {
				$list = $cart_api->{$method}();
				if ( is_array( $list ) ) {
					return array_values( array_filter( array_map( 'strval', $list ) ) );
				}
			}
		}
		return array();
	}

	/**
	 * @return array<int, string>
	 */
	private function get_applied_gift_cards_from_pw_session(): array {
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			return array();
		}
		$session_key = defined( 'PWGC_SESSION_KEY' ) ? PWGC_SESSION_KEY : 'pw-gift-card-data';
		$session_data = (array) WC()->session->get( $session_key );
		if ( ! isset( $session_data['gift_cards'] ) || ! is_array( $session_data['gift_cards'] ) ) {
			return array();
		}
		return array_values( array_filter( array_map( 'strval', array_keys( $session_data['gift_cards'] ) ) ) );
	}

	/**
	 * Снять подарочную карту с корзины (PW WooCommerce Gift Cards).
	 *
	 * @return true|\WP_Error
	 */
	public function remove_gift_card( string $code ) {
		$gift_code = trim( (string) $code );
		if ( '' === $gift_code ) {
			return new \WP_Error( 'mp_cc_gift_card_empty', __( 'Не указан код подарочной карты.', 'mp-custom-checkout' ) );
		}
		if ( ! $this->is_pw_gift_cards_available() ) {
			return new \WP_Error( 'mp_cc_pw_unavailable', __( 'Интеграция PW Gift Cards недоступна.', 'mp-custom-checkout' ) );
		}

		global $pw_gift_cards_redeeming;
		if ( isset( $pw_gift_cards_redeeming ) && is_object( $pw_gift_cards_redeeming ) && method_exists( $pw_gift_cards_redeeming, 'remove_gift_card_from_session' ) ) {
			$pw_gift_cards_redeeming->remove_gift_card_from_session( $gift_code );
			if ( function_exists( 'WC' ) && WC()->cart instanceof \WC_Cart ) {
				WC()->cart->calculate_totals();
			}
			return true;
		}

		$filtered = apply_filters( 'mp_custom_checkout_remove_pw_gift_card', null, $gift_code );
		if ( is_wp_error( $filtered ) ) {
			return $filtered;
		}
		if ( true === $filtered ) {
			return true;
		}

		return new \WP_Error( 'mp_cc_pw_remove_unavailable', __( 'Не удалось снять подарочную карту.', 'mp-custom-checkout' ) );
	}

	/**
	 * Удаляет все применённые подарочные карты из сессии PW и пересчитывает корзину.
	 * Нужно при выходе из checkout, чтобы при следующем заходе не «висели» старые коды и скрытые списания.
	 */
	public function clear_all_applied_gift_cards(): void {
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			return;
		}
		$session_key  = defined( 'PWGC_SESSION_KEY' ) ? PWGC_SESSION_KEY : 'pw-gift-card-data';
		$session_data = (array) WC()->session->get( $session_key );
		if ( isset( $session_data['gift_cards'] ) ) {
			unset( $session_data['gift_cards'] );
		}
		WC()->session->set( $session_key, $session_data );

		if ( function_exists( 'WC' ) && WC()->cart instanceof \WC_Cart ) {
			WC()->cart->calculate_totals();
		}
	}

	/**
	 * @return object|null
	 */
	private function resolve_pw_service() {
		$class = self::PW_GIFT_CARDS_CLASS;
		if ( ! class_exists( $class, false ) ) {
			return null;
		}
		if ( method_exists( $class, 'get_instance' ) ) {
			$instance = $class::get_instance();
			if ( is_object( $instance ) ) {
				return $instance;
			}
		}
		if ( isset( $GLOBALS['pw_gift_cards'] ) && is_object( $GLOBALS['pw_gift_cards'] ) ) {
			return $GLOBALS['pw_gift_cards'];
		}
		return null;
	}

	/**
	 * @param object $service
	 * @return object|null
	 */
	private function resolve_cart_api( $service ) {
		if ( isset( $service->cart ) && is_object( $service->cart ) ) {
			return $service->cart;
		}
		if ( method_exists( $service, 'cart' ) ) {
			$cart = $service->cart();
			if ( is_object( $cart ) ) {
				return $cart;
			}
		}
		if ( method_exists( $service, 'get_cart' ) ) {
			$cart = $service->get_cart();
			if ( is_object( $cart ) ) {
				return $cart;
			}
		}
		return null;
	}
}
