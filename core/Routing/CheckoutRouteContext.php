<?php
/**
 * Контекст запроса (язык, мультиязычные плагины, базовый URL).
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout\Routing;

use MP\CustomCheckout\Checkout\Routing\CheckoutPermalinkCompatibility;
use MP\CustomCheckout\Checkout\Hooks\CheckoutAjaxHooks;
use MP\CustomCheckout\Checkout\Routing\CheckoutSessionService;
use MP\CustomCheckout\Integrations\WooCommerce\GiftCardIntegration;
use MP\CustomCheckout\Integrations\WooCommerce\WcCustomerShippingSync;
use MP\CustomCheckout\Settings\FeatureFlagResolver;
use MP\CustomCheckout\Settings\ScenarioStepRegistry;

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
			'home_url'          => home_url( '/' ),
			'exit_landing_url'  => CheckoutReturnPaths::get_exit_landing_url(),
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

		if ( function_exists( 'WC' ) && WC() ) {
			$payment_fields                     = CheckoutAjaxHooks::get_payment_fields_for_context();
			$context['payment_fields_html']     = isset( $payment_fields['payment_fields_html'] ) ? (string) $payment_fields['payment_fields_html'] : '';
			$context['payment_fields_gateway']  = isset( $payment_fields['payment_fields_gateway'] ) ? (string) $payment_fields['payment_fields_gateway'] : '';
		} else {
			$context['payment_fields_html']    = '';
			$context['payment_fields_gateway'] = '';
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
			'items'               => array(),
			'wc_shipping_rates'   => array(),
			'summary'             => array(
				'items_count' => 0,
				'subtotal'    => '',
				'shipping'    => '',
				'tax'         => '',
				'total'       => '',
				'discount'    => '',
				'applied_coupons' => array(),
				'coupon_lines' => array(),
				'applied_gift_cards' => array(),
				'gift_card_total' => '',
				'gift_card_lines' => array(),
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
		$shipping_total                   = (float) $cart->get_shipping_total() + (float) $cart->get_shipping_tax();
		$cart_shipping_total              = $shipping_total;
		$flow_for_totals      = CheckoutSessionService::get_public_state();
		$current_step_id      = isset( $flow_for_totals['current_step'] ) ? sanitize_key( (string) $flow_for_totals['current_step'] ) : '';
		$scenario_for_shipping = isset( $flow_for_totals['scenario'] ) ? CheckoutScenarioRules::sanitize_scenario( (string) $flow_for_totals['scenario'] ) : '';
		$answers_for_totals   = isset( $flow_for_totals['answers'] ) && is_array( $flow_for_totals['answers'] ) ? $flow_for_totals['answers'] : array();
		$step_one_answers     = isset( $answers_for_totals['step_one'] ) && is_array( $answers_for_totals['step_one'] ) ? $answers_for_totals['step_one'] : array();
		$date_answers         = isset( $answers_for_totals['date_conditions'] ) && is_array( $answers_for_totals['date_conditions'] ) ? $answers_for_totals['date_conditions'] : array();
		// Как на фронте mergeDateConditionsFromFlowAnswers: база step_one, date_conditions перекрывает.
		$delivery_answers = array_replace( $step_one_answers, $date_answers );
		$scenario_for_shipping = CheckoutScenarioRules::elevate_scenario_if_pickup_but_carrier_method_selected( $scenario_for_shipping, $delivery_answers );
		// Числовой "0" из каталога (ещё без тарифа / без wc_rate_id) не должен затирать фактическую доставку WC.
		$session_shipping_price_value = null;
		if ( isset( $delivery_answers['shipping_price'] ) && is_numeric( $delivery_answers['shipping_price'] ) ) {
			$session_shipping_price_value = max( 0.0, (float) $delivery_answers['shipping_price'] );
		}
		$woocommerce_pricing = WcCustomerShippingSync::is_woocommerce_pricing_mode();
		if ( ! $woocommerce_pricing && null !== $session_shipping_price_value && $session_shipping_price_value > 0.0 ) {
			$shipping_total = $session_shipping_price_value;
		}
		$requires_address_for_shipping = true;
		if ( array_key_exists( 'shipping_requires_address', $delivery_answers ) ) {
			$requires_address_for_shipping = filter_var( $delivery_answers['shipping_requires_address'], FILTER_VALIDATE_BOOLEAN );
		}
		$shipping_method_chosen = '' !== trim( (string) ( $delivery_answers['shipping_method_id'] ?? '' ) );
		// Почта/курьер с адресом: в answers часто shipping_price=0 до синка с фронта, но WC уже пересчитал пакеты — показываем сумму из корзины.
		$wc_address_shipping_ready = $shipping_method_chosen && $requires_address_for_shipping && $cart_shipping_total > 0.0;
		$session_shipping_price_chosen = ( null !== $session_shipping_price_value && $session_shipping_price_value > 0.0 )
			|| (
				null !== $session_shipping_price_value
				&& 0.0 === $session_shipping_price_value
				&& $shipping_method_chosen
				&& ! $requires_address_for_shipping
			)
			|| $wc_address_shipping_ready;
		$steps_pre_payment     = array(
			ScenarioStepRegistry::STEP_ADDRESS_DELIVERY,
			ScenarioStepRegistry::STEP_RECIPIENT,
		);
		$suppress_shipping_in_summary = false;
		if ( $cart->needs_shipping() ) {
			if ( ScenarioStepRegistry::SCENARIO_PICKUP === $scenario_for_shipping ) {
				$suppress_shipping_in_summary = true;
			} elseif ( ! $woocommerce_pricing && '' !== $current_step_id && in_array( $current_step_id, $steps_pre_payment, true ) && ! $session_shipping_price_chosen ) {
				// Пока покупатель не выбрал тариф на шаге 1, не подмешиваем «чужую» WC-доставку в строки и итог.
				// После выбора цена лежит в answers.step_one — показываем и включаем в total.
				$suppress_shipping_in_summary = true;
			}
		}
		if ( $suppress_shipping_in_summary && $shipping_total > 0 ) {
			$shipping_total = 0.0;
		}
		$result['summary']['shipping_total'] = $shipping_total;
		$result['summary']['shipping']    = $shipping_total > 0 ? (string) wc_price( $shipping_total ) : '';
		if ( $suppress_shipping_in_summary ) {
			$result['summary']['shipping_deferred'] = true;
		}
		$result['summary']['fee_lines']   = array();
		foreach ( $cart->get_fees() as $fee ) {
			$fee_total = isset( $fee->total ) ? (float) $fee->total : 0.0;
			if ( $fee_total <= 0 ) {
				continue;
			}
			$name = isset( $fee->name ) ? (string) $fee->name : '';
			$result['summary']['fee_lines'][] = array(
				'label'  => '' !== $name ? $name : __( 'Сбор', 'mp-custom-checkout' ),
				'amount' => (string) wc_price( $fee_total ),
			);
		}
		$total_tax_display = (float) $cart->get_total_tax();
		$total_edit        = (float) $cart->get_total( 'edit' );
		if ( $suppress_shipping_in_summary ) {
			$ship_tax = (float) $cart->get_shipping_tax();
			$ship_amt = (float) $cart->get_shipping_total();
			$total_tax_display = max( 0.0, $total_tax_display - $ship_tax );
			$total_edit        = max( 0.0, $total_edit - $ship_tax - $ship_amt );
		} elseif ( ! $woocommerce_pricing && null !== $session_shipping_price_value && $session_shipping_price_value > 0.0 ) {
			$total_edit = max( 0.0, $total_edit - $cart_shipping_total + $shipping_total );
		}
		$result['summary']['tax']   = (string) wc_price( $total_tax_display );
		$result['summary']['total'] = (string) wc_price( $total_edit );
		$result['summary']['discount']    = (string) wc_price( (float) $cart->get_discount_total() );
		$result['summary']['applied_coupons'] = array_values( $cart->get_applied_coupons() );
		foreach ( $result['summary']['applied_coupons'] as $coupon_code ) {
			$amount = (float) $cart->get_coupon_discount_amount( (string) $coupon_code, false );
			$result['summary']['coupon_lines'][] = array(
				'code'   => (string) $coupon_code,
				'amount' => (string) wc_price( $amount ),
			);
		}
		$gift_card_total = 0.0;
		// Pimwick PW Gift Cards: уменьшает $cart->total в woocommerce_after_calculate_totals, без отрицательных fee.
		if ( property_exists( $cart, 'pwgc_total_gift_cards_redeemed' ) && (float) $cart->pwgc_total_gift_cards_redeemed > 0 ) {
			$gift_card_total = (float) $cart->pwgc_total_gift_cards_redeemed;
			$result['summary']['gift_card_lines'][] = array(
				'label'  => __( 'Подарочная карта', 'mp-custom-checkout' ),
				'amount' => (string) wc_price( $gift_card_total ),
			);
		} else {
			foreach ( $cart->get_fees() as $fee ) {
				$name  = isset( $fee->name ) ? (string) $fee->name : '';
				$total = isset( $fee->total ) ? (float) $fee->total : 0.0;
				if ( $total >= 0 ) {
					continue;
				}
				$gift_card_total += abs( $total );
				$result['summary']['gift_card_lines'][] = array(
					'label'  => '' !== $name ? $name : __( 'Скидка', 'mp-custom-checkout' ),
					'amount' => (string) wc_price( abs( $total ) ),
				);
			}
		}
		$result['summary']['gift_card_total'] = (string) wc_price( $gift_card_total );
		$flow    = $flow_for_totals;
		$answers = isset( $flow['answers'] ) && is_array( $flow['answers'] ) ? $flow['answers'] : array();
		$session_discounts = isset( $answers['discounts'] ) && is_array( $answers['discounts'] ) ? $answers['discounts'] : array();
		$integration = new GiftCardIntegration();
		if ( $integration->is_pw_gift_cards_available() ) {
			$pw_cards = $integration->get_applied_gift_cards();
			if ( ! empty( $pw_cards ) ) {
				$result['summary']['applied_gift_cards'] = $pw_cards;
			}
		}
		if ( empty( $result['summary']['applied_gift_cards'] ) && isset( $session_discounts['gift_card'] ) && is_array( $session_discounts['gift_card'] ) ) {
			$result['summary']['applied_gift_cards'] = array_values( array_map( 'strval', $session_discounts['gift_card'] ) );
		}
		$result['summary']['catalog_url'] = CheckoutReturnPaths::get_exit_landing_url();
		$result['wc_shipping_rates']       = self::collect_wc_shipping_rates_snapshot();

		return $result;
	}

	/**
	 * Мета ставки доставки WC (часто сюда плагины СДЭК кладут код тарифа из API — см. расчёт в интеграции WC, не дублируем apidoc.cdek.ru).
	 *
	 * @return array<string, string>
	 */
	private static function wc_shipping_rate_meta_for_snapshot( \WC_Shipping_Rate $rate ): array {
		$out = array();
		if ( ! is_callable( array( $rate, 'get_meta_data' ) ) ) {
			return $out;
		}
		foreach ( (array) $rate->get_meta_data() as $meta_row ) {
			if ( ! $meta_row instanceof \WC_Meta_Data ) {
				continue;
			}
			$data = $meta_row->get_data();
			$key  = isset( $data['key'] ) ? sanitize_key( (string) $data['key'] ) : '';
			if ( '' === $key ) {
				continue;
			}
			$val = isset( $data['value'] ) ? $data['value'] : null;
			if ( is_array( $val ) || is_object( $val ) ) {
				$val = wp_json_encode( $val, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
			}
			$val = is_scalar( $val ) ? (string) $val : '';
			if ( strlen( $val ) > 400 ) {
				$val = substr( $val, 0, 400 );
			}
			$out[ $key ] = $val;
		}

		return $out;
	}

	/**
	 * Плоский список ставок WC для текущего адреса корзины (после calculate_totals / синка сессии).
	 *
	 * @return array<int, array{id: string, label: string, cost: float, method_id: string, meta: array<string, string>}>
	 */
	private static function collect_wc_shipping_rates_snapshot(): array {
		if ( ! function_exists( 'WC' ) || ! WC()->cart instanceof \WC_Cart ) {
			return array();
		}
		$cart = WC()->cart;
		if ( ! $cart->needs_shipping() ) {
			return array();
		}
		$packages = WC()->shipping()->get_packages();
		$out      = array();
		foreach ( (array) $packages as $package ) {
			if ( ! is_array( $package ) ) {
				continue;
			}
			$rates = isset( $package['rates'] ) && is_array( $package['rates'] ) ? $package['rates'] : array();
			foreach ( $rates as $rate_id => $rate ) {
				if ( ! $rate instanceof \WC_Shipping_Rate ) {
					continue;
				}
				$id = is_string( $rate_id ) ? $rate_id : (string) $rate->get_id();
				$cost = (float) $rate->get_cost();
				foreach ( (array) $rate->get_taxes() as $tax_amt ) {
					$cost += (float) $tax_amt;
				}
				$decimals   = function_exists( 'wc_get_price_decimals' ) ? (int) wc_get_price_decimals() : 2;
				$method_id  = is_callable( array( $rate, 'get_method_id' ) ) ? (string) $rate->get_method_id() : '';
				$meta_clean = self::wc_shipping_rate_meta_for_snapshot( $rate );
				$row        = array(
					'id'         => $id,
					'label'      => wp_strip_all_tags( (string) $rate->get_label() ),
					'cost'       => (float) wc_format_decimal( max( 0.0, $cost ), $decimals ),
					'method_id'  => $method_id,
					'meta'       => $meta_clean,
				);
				/**
				 * Одна ставка в снимке (расширение под конкретный плагин СДЭК / другое).
				 *
				 * @param array<string, mixed> $row
				 */
				$out[] = apply_filters( 'mp_custom_checkout_wc_shipping_rate_snapshot_row', $row, $rate, $package );
			}
		}

		/**
		 * Позволяет теме/плагину отфильтровать или дополнить список ставок для UI checkout.
		 *
		 * @param array<int, array<string, mixed>> $out
		 * @param \WC_Cart                         $cart
		 */
		return apply_filters( 'mp_custom_checkout_wc_shipping_rates_snapshot', $out, $cart );
	}
}
