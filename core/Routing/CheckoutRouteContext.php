<?php
/**
 * Контекст запроса (язык, мультиязычные плагины, базовый URL).
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout\Routing;

use MP\CustomCheckout\Settings\FeatureFlagResolver;

defined( 'ABSPATH' ) || exit;

/**
 * Class CheckoutRouteContext
 */
final class CheckoutRouteContext {

	/**
	 * Данные для шаблона и AJAX.
	 *
	 * @return array<string, mixed>
	 */
	public static function collect(): array {
		$context = array(
			'home_url'        => home_url( '/' ),
			'site_locale'     => get_locale(),
			'is_admin'        => is_admin(),
			'is_plain_permalinks' => CheckoutPermalinkCompatibility::is_plain_permalinks(),
			'feature_flags'   => FeatureFlagResolver::all(),
			'cart'            => self::get_cart_data(),
		);

		$flow = CheckoutSessionService::get_public_state();
		if ( ! empty( $flow ) ) {
			$step_manager = new CheckoutStepManager( $flow );
			$scenario     = isset( $flow['scenario'] ) ? (string) $flow['scenario'] : '';
			$scenario     = CheckoutScenarioRules::sanitize_scenario( $scenario );
			$context['checkout_flow'] = array(
				'context_id'   => isset( $flow['context_id'] ) ? (string) $flow['context_id'] : '',
				'current_step' => (string) ( $step_manager->get_current_step_id() ?? '' ),
				'scenario'     => $scenario,
				'answers'      => isset( $flow['answers'] ) && is_array( $flow['answers'] ) ? $flow['answers'] : array(),
				'snapshot'     => isset( $flow['snapshot'] ) && is_array( $flow['snapshot'] ) ? $flow['snapshot'] : array(),
				'expires_at'   => isset( $flow['expires_at'] ) ? (int) $flow['expires_at'] : 0,
				'steps'        => array_values( $step_manager->get_registered_steps() ),
				'visible_steps' => $step_manager->get_visible_step_ids(),
				'scenario_rules' => CheckoutScenarioRules::build( $scenario ),
			);
		}

		$lang = self::detect_current_language();
		if ( null !== $lang ) {
			$context['language'] = $lang;
		}

		$context['is_multilingual'] = null !== $lang;

		return (array) apply_filters( 'mp_custom_checkout_route_context', $context );
	}

	/**
	 * Текущий код языка (WPML, Polylang) или null.
	 */
	private static function detect_current_language(): ?string {
		if ( function_exists( 'pll_current_language' ) ) {
			$lang = pll_current_language( 'slug' );
			return is_string( $lang ) && '' !== $lang ? $lang : null;
		}

		if ( defined( 'ICL_LANGUAGE_CODE' ) && is_string( ICL_LANGUAGE_CODE ) && '' !== ICL_LANGUAGE_CODE ) {
			return ICL_LANGUAGE_CODE;
		}

		$wpml = apply_filters( 'wpml_current_language', null );
		if ( is_string( $wpml ) && '' !== $wpml ) {
			return $wpml;
		}

		return null;
	}

	/**
	 * Снимок корзины для шага 1 (позиции + summary).
	 *
	 * @return array<string, mixed>
	 */
	public static function get_cart_data(): array {
		$result = array(
			'items'    => array(),
			'summary'  => array(
				'items_count' => 0,
				'subtotal'    => '',
				'catalog_url' => '',
			),
		);

		if ( ! function_exists( 'WC' ) || ! WC()->cart instanceof \WC_Cart ) {
			return $result;
		}

		$cart = WC()->cart;
		foreach ( (array) $cart->get_cart() as $cart_item_key => $cart_item ) {
			if ( ! is_array( $cart_item ) ) {
				continue;
			}
			$product = isset( $cart_item['data'] ) && $cart_item['data'] instanceof \WC_Product ? $cart_item['data'] : null;
			if ( ! $product ) {
				continue;
			}

			$variation_text = '';
			if ( function_exists( 'wc_get_formatted_cart_item_data' ) ) {
				$variation_text = trim( wp_strip_all_tags( wc_get_formatted_cart_item_data( $cart_item, true ) ) );
			}

			$name = (string) $product->get_name();
			if ( '' === $name ) {
				$name = __( 'Товар', 'mp-custom-checkout' );
			}

			$image_url = '';
			$image_id  = (int) $product->get_image_id();
			if ( $image_id > 0 ) {
				$url = wp_get_attachment_image_url( $image_id, 'woocommerce_thumbnail' );
				$image_url = is_string( $url ) ? $url : '';
			}

			$qty = isset( $cart_item['quantity'] ) ? max( 0, (int) $cart_item['quantity'] ) : 0;
			$line_subtotal = '';
			if ( function_exists( 'WC' ) && WC()->cart ) {
				$line_subtotal = WC()->cart->get_product_subtotal( $product, $qty );
			}

			$max_qty = (int) $product->get_max_purchase_quantity();
			if ( $max_qty <= 0 ) {
				$max_qty = 9999;
			}

			$result['items'][] = array(
				'key'            => (string) $cart_item_key,
				'product_id'     => isset( $cart_item['product_id'] ) ? (int) $cart_item['product_id'] : 0,
				'variation_id'   => isset( $cart_item['variation_id'] ) ? (int) $cart_item['variation_id'] : 0,
				'name'           => $name,
				'price_html'     => $product->get_price_html(),
				'sku'            => (string) $product->get_sku(),
				'variation_text' => $variation_text,
				'image_url'      => $image_url,
				'quantity'       => $qty,
				'min_quantity'   => max( 1, (int) $product->get_min_purchase_quantity() ),
				'max_quantity'   => $max_qty,
				'line_subtotal'  => (string) $line_subtotal,
			);
		}

		$result['summary']['items_count'] = (int) $cart->get_cart_contents_count();
		$result['summary']['subtotal']    = (string) $cart->get_cart_subtotal();
		$catalog_url                      = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : '';
		$result['summary']['catalog_url'] = is_string( $catalog_url ) && '' !== $catalog_url ? $catalog_url : home_url( '/' );

		return $result;
	}
}
