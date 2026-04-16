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
