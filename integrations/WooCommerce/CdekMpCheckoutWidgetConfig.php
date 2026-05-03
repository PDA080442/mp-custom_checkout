<?php
/**
 * Конфиг виджета ПВЗ СДЭК для кастомного checkout (§29.2).
 * Читает настройки из официального плагина CDEKDelivery (namespace Cdek), без дублирования ключей в MP.
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout\Integrations\WooCommerce;

defined( 'ABSPATH' ) || exit;

final class CdekMpCheckoutWidgetConfig {

	/**
	 * @param array<string, mixed>|null $checkout_flow Ключ checkout_flow из контекста маршрута.
	 * @return array<string, mixed>
	 */
	public static function build_for_frontend( ?array $checkout_flow ): array {
		$base = array(
			'enabled'        => false,
			'map_ready'      => false,
			'fallback'       => true,
			'reason'         => 'cdek_unavailable',
			'reason_hint'    => '',
			'apiKey'         => '',
			'lang'           => 'rus',
			'default_city'   => '',
			'postcode'       => '',
			'offices_json'   => '[]',
			'map_auto_close' => false,
			'saver_url'      => '',
			'service_path'   => '',
			'goods'          => array(),
			'debug'          => false,
			'labels'         => self::modal_labels(),
		);

		if ( ! class_exists( '\Cdek\ShippingMethod', false ) ) {
			return array_merge(
				$base,
				array(
					'reason_hint' => self::reason_hint_for_customers( 'cdek_unavailable' ),
				)
			);
		}

		try {
			$shipping = \Cdek\ShippingMethod::factory();
		} catch ( \Throwable $e ) {
			return array_merge(
				$base,
				array(
					'reason'      => 'cdek_factory_failed',
					'reason_hint' => self::reason_hint_for_customers( 'cdek_factory_failed' ),
				)
			);
		}

		$api_key = isset( $shipping->yandex_map_api_key ) ? trim( (string) $shipping->yandex_map_api_key ) : '';
		if ( '' === $api_key ) {
			return array_merge(
				$base,
				array(
					'reason'      => 'yandex_api_key_empty',
					'reason_hint' => self::reason_hint_for_customers( 'yandex_api_key_empty' ),
				)
			);
		}

		$locale = function_exists( 'get_user_locale' ) ? (string) get_user_locale() : '';
		$lang   = ( 0 === mb_strpos( $locale, 'en' ) ) ? 'eng' : 'rus';

		$city     = '';
		$postcode = '';
		if ( is_array( $checkout_flow ) && isset( $checkout_flow['answers'] ) && is_array( $checkout_flow['answers'] ) ) {
			$answers = $checkout_flow['answers'];
			$s1      = isset( $answers['step_one'] ) && is_array( $answers['step_one'] ) ? $answers['step_one'] : array();
			$contact = isset( $answers['contact_billing'] ) && is_array( $answers['contact_billing'] ) ? $answers['contact_billing'] : array();
			$city    = trim( (string) ( $s1['city'] ?? $s1['shipping_city'] ?? $contact['city'] ?? $contact['shipping_city'] ?? '' ) );
			$postcode = trim( (string) ( $s1['postcode'] ?? $s1['shipping_postcode'] ?? $contact['postcode'] ?? $contact['shipping_postcode'] ?? '' ) );
		}

		if ( '' === $city && function_exists( 'WC' ) && WC()->customer ) {
			$cust = WC()->customer;
			$city = trim( (string) ( $cust->get_shipping_city() ?: $cust->get_billing_city() ) );
		}
		if ( '' === $postcode && function_exists( 'WC' ) && WC()->customer ) {
			$cust = WC()->customer;
			$postcode = trim( (string) ( $cust->get_shipping_postcode() ?: $cust->get_billing_postcode() ) );
		}

		$offices_json = '[]';
		if ( class_exists( '\Cdek\CdekApi', false ) && '' !== $city ) {
			try {
				$api  = new \Cdek\CdekApi();
				$code = $api->cityCodeGet( $city, '' !== $postcode ? $postcode : null );
				if ( null !== $code ) {
					$raw = $api->officeListRaw( $code );
					if ( is_string( $raw ) && '' !== $raw ) {
						$offices_json = $raw;
					}
				}
			} catch ( \Throwable $e ) {
				$offices_json = '[]';
			}
		}

		$saver = '';
		if ( function_exists( 'WC' ) && class_exists( '\WC_AJAX', false ) && class_exists( '\Cdek\Config', false ) ) {
			$saver = \WC_AJAX::get_endpoint( \Cdek\Config::DELIVERY_NAME . '_save-office' );
		}

		$goods = self::default_goods_from_cart();

		$service_path = (string) apply_filters( 'mp_custom_checkout_cdek_widget_service_path', '', $shipping, $checkout_flow );
		if ( '' === $service_path ) {
			$service_path = self::resolve_optional_service_path();
		}

		// Карта доступна при ключе Яндекса; официальный фронт плагина (`cdek-checkout-map.js`) вызывает виджет без servicePath.
		$map_ready = '' !== $api_key;
		$fallback  = ! $map_ready;

		/**
		 * Включить verbose-логи виджета СДЭК в консоли (передаётся в `CDEKWidget` как `debug`).
		 *
		 * @param bool                           $debug         По умолчанию false.
		 * @param \WC_Shipping_Method|false|null $shipping      Экземпляр способа СДЭК или false.
		 * @param array<string, mixed>           $checkout_flow Контекст потока checkout (если передан вызывающим кодом).
		 */
		$debug_flag = (bool) apply_filters( 'mp_custom_checkout_cdek_widget_debug', false, $shipping, $checkout_flow );

		return array(
			'enabled'        => true,
			'map_ready'      => $map_ready,
			'fallback'       => $fallback,
			'reason'         => '',
			'reason_hint'    => '',
			'apiKey'         => $api_key,
			'lang'           => $lang,
			'default_city'   => $city,
			'postcode'       => $postcode,
			'offices_json'   => $offices_json,
			'map_auto_close' => ! empty( $shipping->map_auto_close ),
			'saver_url'      => $saver,
			'service_path'   => $service_path,
			'goods'          => $goods,
			'debug'          => $debug_flag,
			'labels'         => self::modal_labels(),
		);
	}

	/**
	 * @return array<string, string>
	 */
	private static function modal_labels(): array {
		return array(
			'close'                  => __( 'Закрыть', 'mp-custom-checkout' ),
			'confirm'                => __( 'Выбрать', 'mp-custom-checkout' ),
			'list_title'             => __( 'Выбор пункта', 'mp-custom-checkout' ),
			'map_title'              => __( 'Пункт СДЭК на карте', 'mp-custom-checkout' ),
			'loading_map'            => __( 'Загружаем карту…', 'mp-custom-checkout' ),
			'map_unavailable'        => __( 'Карта временно недоступна, выберите пункт из списка ниже.', 'mp-custom-checkout' ),
			'map_unavailable_title'  => __( 'Список ПВЗ СДЭК (карта недоступна)', 'mp-custom-checkout' ),
			'pvz_list_empty_title'   => __( 'Выбор пункта СДЭК', 'mp-custom-checkout' ),
			'pvz_list_empty_no_city' => __( 'Укажите город получателя в форме — после этого здесь появятся ближайшие ПВЗ СДЭК.', 'mp-custom-checkout' ),
			'pvz_list_empty_no_offices' => __( 'В выбранном городе нет ПВЗ СДЭК. Выберите другой способ доставки.', 'mp-custom-checkout' ),
			'pvz_no_offices'         => __( 'Карта ПВЗ временно недоступна. Выберите другой способ доставки.', 'mp-custom-checkout' ),
			'map_config_error'       => __( 'Карта недоступна: проверьте ключ API Яндекс.Карт в настройках доставки СДЭК.', 'mp-custom-checkout' ),
			'map_init_failed'        => __( 'Не удалось открыть карту.', 'mp-custom-checkout' ),
			'map_pick_failed'        => __( 'Не удалось получить код пункта.', 'mp-custom-checkout' ),
			'pick_required'          => __( 'Выберите пункт из списка.', 'mp-custom-checkout' ),
			'list_empty'             => __( 'Нет доступных точек.', 'mp-custom-checkout' ),
		);
	}

	/**
	 * Опциональный URL прокси service.php (если есть в сборке плагина). Виджет на эталонном checkout СДЭК работает без него.
	 */
	private static function resolve_optional_service_path(): string {
		if ( ! class_exists( '\Cdek\Loader', false ) ) {
			return '';
		}
		foreach ( array( 'build/service.php', 'dist/service.php' ) as $rel ) {
			$path = \Cdek\Loader::getPluginPath( $rel );
			if ( is_readable( $path ) ) {
				$url = \Cdek\Loader::getPluginUrl( $rel );
				return is_string( $url ) ? esc_url_raw( $url ) : '';
			}
		}

		return '';
	}

	/**
	 * Короткий текст для покупателя (без машинных кодов). Код причины остаётся в `reason` для логов.
	 */
	private static function reason_hint_for_customers( string $reason ): string {
		switch ( $reason ) {
			case 'yandex_api_key_empty':
				return __( 'В настройках доставки СДЭК в WooCommerce укажите ключ API Яндекс.Карт.', 'mp-custom-checkout' );
			case 'cdek_factory_failed':
				return __( 'Проверьте настройки способа доставки СДЭК в WooCommerce.', 'mp-custom-checkout' );
			case 'cdek_unavailable':
			default:
				return __( 'Установите и активируйте официальный плагин CDEKDelivery для WooCommerce.', 'mp-custom-checkout' );
		}
	}

	/**
	 * @return array<int, array{width:float|int,length:float|int,height:float|int,weight:float|int}>
	 */
	private static function default_goods_from_cart(): array {
		$weight_g = 1000;
		$l        = 10;
		$w        = 10;
		$h        = 10;
		if ( function_exists( 'WC' ) && WC()->cart instanceof \WC_Cart ) {
			$cart = WC()->cart;
			$kg   = (float) $cart->get_cart_contents_weight();
			if ( $kg > 0 ) {
				$weight_g = max( 100, (int) round( $kg * 1000 ) );
			}
		}

		return array(
			array(
				'length' => $l,
				'width'  => $w,
				'height' => $h,
				'weight' => $weight_g,
			),
		);
	}
}
