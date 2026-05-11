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
		// Раньше в этот список входил и STEP_RECIPIENT — защита от подмешивания «чужой» WC-доставки,
		// пока на шаге «Получатель» был адресный блок и адрес мог меняться там. Сейчас адресный блок
		// со 2-го шага убран (пользователь заполняет адрес только на шаге 1), поэтому на шаге
		// «Получатель» суппресс уже не имеет смысла: тариф либо выбран и записан в answers.step_one,
		// либо метод не требует адреса. Если оставить здесь STEP_RECIPIENT, строка «Доставка» в сводке
		// исчезает на шагах 2/3/4, как только session_shipping_price теряется/обнуляется (например,
		// при пересчёте корзины WC). Видим только итог, но не саму строку — это сбивает пользователя.
		$steps_pre_payment     = array(
			ScenarioStepRegistry::STEP_ADDRESS_DELIVERY,
		);
		$suppress_shipping_in_summary = false;
		if ( $cart->needs_shipping() ) {
			if ( ScenarioStepRegistry::SCENARIO_PICKUP === $scenario_for_shipping ) {
				$suppress_shipping_in_summary = true;
			} elseif ( ! $woocommerce_pricing && '' !== $current_step_id && in_array( $current_step_id, $steps_pre_payment, true ) && ! $session_shipping_price_chosen ) {
				// Пока покупатель на шаге 1 ещё не выбрал тариф (нет shipping_price в answers.step_one
				// и WC не посчитал rate по адресу), не подмешиваем «чужую» WC-доставку в строки и итог.
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
	 * Извлекает диапазон срока доставки в днях из мета-данных WC-ставки.
	 *
	 * Парсит распространённые ключи (period_min/max от официального плагина СДЭК и др.).
	 * Возвращает массив { min: ?int, max: ?int }. Если данных нет — оба значения null.
	 *
	 * @param array<string, string> $meta       Очищенная мета ставки (ключ => строка).
	 * @param \WC_Shipping_Rate     $rate       Сам объект ставки (для фильтра).
	 * @param string                $method_id  ID метода (cdek, post_russia и т.п.) — для фильтра.
	 *
	 * @return array{min: ?int, max: ?int}
	 */
	private static function extract_wc_shipping_rate_eta_days( array $meta, \WC_Shipping_Rate $rate, string $method_id ): array {
		$min = null;
		$max = null;

		$normalized = array();
		foreach ( $meta as $k => $v ) {
			$normalized[ strtolower( (string) $k ) ] = (string) $v;
		}

		$min_keys = array( 'period_min', '_cdek_period_min', 'min_delivery_days', 'days_min', 'delivery_min' );
		$max_keys = array( 'period_max', '_cdek_period_max', 'max_delivery_days', 'days_max', 'delivery_max' );

		foreach ( $min_keys as $mk ) {
			if ( isset( $normalized[ $mk ] ) && '' !== $normalized[ $mk ] ) {
				$parsed = self::parse_first_positive_int( $normalized[ $mk ] );
				if ( null !== $parsed ) {
					$min = $parsed;
					break;
				}
			}
		}
		foreach ( $max_keys as $mxk ) {
			if ( isset( $normalized[ $mxk ] ) && '' !== $normalized[ $mxk ] ) {
				$parsed = self::parse_first_positive_int( $normalized[ $mxk ] );
				if ( null !== $parsed ) {
					$max = $parsed;
					break;
				}
			}
		}

		if ( null === $min && null === $max ) {
			$combo_keys = array( 'delivery_days', 'period', 'days', 'eta_days' );
			foreach ( $combo_keys as $ck ) {
				if ( isset( $normalized[ $ck ] ) && '' !== $normalized[ $ck ] ) {
					$pair = self::parse_days_range_string( $normalized[ $ck ] );
					if ( null !== $pair['min'] || null !== $pair['max'] ) {
						$min = $pair['min'];
						$max = $pair['max'];
						break;
					}
				}
			}
		}

		// Многие плагины (например, официальный плагин CDEK) не пишут срок в meta, но добавляют его в label
		// ставки: "Курьером до двери (экспресс), (3-4 дней)". Если meta пуста — пробуем извлечь срок из label,
		// но ТОЛЬКО когда числа стоят рядом со словом "дн"/"day" — иначе можно зацепить вес/код/индекс.
		if ( null === $min && null === $max ) {
			$label = is_callable( array( $rate, 'get_label' ) ) ? (string) $rate->get_label() : '';
			$from_label = self::parse_days_from_label( $label );
			if ( null !== $from_label['min'] || null !== $from_label['max'] ) {
				$min = $from_label['min'];
				$max = $from_label['max'];
			}
		}

		if ( null === $min && null !== $max ) {
			$min = $max;
		}
		if ( null !== $min && null === $max ) {
			$max = $min;
		}
		if ( null !== $min && null !== $max && $min > $max ) {
			$tmp = $min;
			$min = $max;
			$max = $tmp;
		}

		$result = array(
			'min' => $min,
			'max' => $max,
		);

		/**
		 * Позволяет переопределить или дополнить парсер срока для конкретной WC-ставки.
		 *
		 * @param array{min: ?int, max: ?int} $result     Текущий результат парсинга.
		 * @param \WC_Shipping_Rate           $rate       Объект WC-ставки.
		 * @param array<string, string>       $meta       Очищенная мета ставки.
		 * @param string                      $method_id  ID метода доставки.
		 */
		$filtered = apply_filters( 'mp_custom_checkout_wc_shipping_rate_eta_days', $result, $rate, $meta, $method_id );
		if ( ! is_array( $filtered ) ) {
			return $result;
		}
		$out_min = isset( $filtered['min'] ) && is_numeric( $filtered['min'] ) ? (int) $filtered['min'] : null;
		$out_max = isset( $filtered['max'] ) && is_numeric( $filtered['max'] ) ? (int) $filtered['max'] : null;
		if ( null !== $out_min && $out_min < 1 ) {
			$out_min = null;
		}
		if ( null !== $out_max && $out_max < 1 ) {
			$out_max = null;
		}
		if ( null === $out_min && null !== $out_max ) {
			$out_min = $out_max;
		}
		if ( null !== $out_min && null === $out_max ) {
			$out_max = $out_min;
		}

		return array(
			'min' => $out_min,
			'max' => $out_max,
		);
	}

	/**
	 * Парсит первое положительное целое число из строки. Например, "3", "3 дня", "до 5".
	 *
	 * @param string $raw
	 *
	 * @return int|null
	 */
	private static function parse_first_positive_int( string $raw ) {
		if ( '' === $raw ) {
			return null;
		}
		if ( preg_match( '/\d+/', $raw, $m ) ) {
			$n = (int) $m[0];
			if ( $n >= 1 ) {
				return $n;
			}
		}
		return null;
	}

	/**
	 * Парсит диапазон дней из одной строки вида "3-5", "3—5", "3..5", "от 3 до 5" и т.п.
	 *
	 * @param string $raw
	 *
	 * @return array{min: ?int, max: ?int}
	 */
	private static function parse_days_range_string( string $raw ): array {
		$result = array(
			'min' => null,
			'max' => null,
		);
		if ( '' === $raw ) {
			return $result;
		}
		if ( preg_match_all( '/\d+/', $raw, $matches ) ) {
			$nums = array_map( 'intval', $matches[0] );
			$nums = array_values( array_filter( $nums, static function ( $n ) { return $n >= 1; } ) );
			if ( ! empty( $nums ) ) {
				$result['min'] = (int) $nums[0];
				$result['max'] = isset( $nums[1] ) ? (int) $nums[1] : $result['min'];
			}
		}
		return $result;
	}

	/**
	 * Парсит срок из подписи WC-ставки. Срабатывает ТОЛЬКО когда число(а) стоят рядом со словом
	 * "дн" (день/дня/дней/дн.) или "day(s)" — чтобы не зацепить лишние цифры (вес, коды и пр.).
	 *
	 * @param string $label
	 *
	 * @return array{min: ?int, max: ?int}
	 */
	private static function parse_days_from_label( string $label ): array {
		$result = array(
			'min' => null,
			'max' => null,
		);
		if ( '' === $label ) {
			return $result;
		}
		if ( preg_match( '/(\d+)\s*[\-–—]\s*(\d+)\s*(?:дн|day)/iu', $label, $m ) ) {
			$lo = (int) $m[1];
			$hi = (int) $m[2];
			if ( $lo >= 1 ) {
				$result['min'] = $lo;
			}
			if ( $hi >= 1 ) {
				$result['max'] = $hi;
			}
			return $result;
		}
		if ( preg_match( '/(\d+)\s*(?:дн|day)/iu', $label, $m ) ) {
			$n = (int) $m[1];
			if ( $n >= 1 ) {
				$result['min'] = $n;
				$result['max'] = $n;
			}
		}
		return $result;
	}

	/**
	 * Ключ хранения снимка ставок WC в WC_Session. Снимок обновляется на каждый
	 * `woocommerce_after_calculate_totals` (это момент, когда WC уже пересчитал packages и
	 * cart->shipping_total согласован). При сборке контекста чекаута мы читаем именно отсюда,
	 * чтобы не дёргать calculate_shipping() самостоятельно и не ломать cart_totals.
	 */
	private const WC_SHIPPING_RATES_SNAPSHOT_SESSION_KEY = 'mp_cc_wc_shipping_rates_snapshot';

	/**
	 * Регистрирует слушатель момента пересчёта корзины. Вызывается из PluginHooksRegistrar
	 * после готовности интеграции с WooCommerce.
	 */
	public static function register_shipping_snapshot_capture(): void {
		add_action( 'woocommerce_after_calculate_totals', array( __CLASS__, 'capture_wc_shipping_rates_snapshot' ), 20 );
		add_action( 'woocommerce_shipping_method_chosen', array( __CLASS__, 'capture_wc_shipping_rates_snapshot' ), 20 );
	}

	/**
	 * Callback на стандартные WC-экшены. Сохраняет в WC_Session текущий снимок ставок,
	 * не дёргая никаких пересчётов сам. WC к этому моменту уже всё посчитал.
	 */
	public static function capture_wc_shipping_rates_snapshot(): void {
		if ( ! function_exists( 'WC' ) ) {
			return;
		}
		$session = WC()->session;
		if ( ! ( $session instanceof \WC_Session ) ) {
			return;
		}
		try {
			$rows = self::collect_wc_shipping_rates_snapshot_raw();
			if ( ! empty( $rows ) && is_callable( array( $session, 'set' ) ) ) {
				$session->set(
					self::WC_SHIPPING_RATES_SNAPSHOT_SESSION_KEY,
					array(
						'time'  => time(),
						'rates' => $rows,
					)
				);
			}
		} catch ( \Throwable $e ) {
			return;
		}
	}

	/**
	 * Плоский список ставок WC для текущего адреса корзины.
	 * Сначала пытается прочитать снимок из WC_Session (он обновляется на woocommerce_after_calculate_totals).
	 * Если снимка нет — пробуем прочитать пакеты «как есть» в текущем процессе.
	 *
	 * @return array<int, array{id: string, label: string, cost: float, method_id: string, meta: array<string, string>, eta_days: array{min: ?int, max: ?int}}>
	 */
	private static function collect_wc_shipping_rates_snapshot(): array {
		if ( function_exists( 'WC' ) && WC()->session instanceof \WC_Session ) {
			$cached = WC()->session->get( self::WC_SHIPPING_RATES_SNAPSHOT_SESSION_KEY );
			if ( is_array( $cached ) && isset( $cached['rates'] ) && is_array( $cached['rates'] ) && ! empty( $cached['rates'] ) ) {
				return $cached['rates'];
			}
		}
		return self::collect_wc_shipping_rates_snapshot_raw();
	}

	/**
	 * Чистое чтение packages WC без принудительных пересчётов и без чтения сессионного кеша.
	 * Если packages пуст в текущем процессе — вернём пустой массив (это нормально для AJAX до синка).
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function collect_wc_shipping_rates_snapshot_raw(): array {
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
				$eta_days   = self::extract_wc_shipping_rate_eta_days( $meta_clean, $rate, $method_id );
				$row        = array(
					'id'         => $id,
					'label'      => wp_strip_all_tags( (string) $rate->get_label() ),
					'cost'       => (float) wc_format_decimal( max( 0.0, $cost ), $decimals ),
					'method_id'  => $method_id,
					'meta'       => $meta_clean,
					'eta_days'   => $eta_days,
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
