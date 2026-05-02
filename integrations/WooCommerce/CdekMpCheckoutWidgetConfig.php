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
			'apiKey'         => '',
			'lang'           => 'rus',
			'default_city'   => '',
			'postcode'       => '',
			'offices_json'   => '[]',
			'map_auto_close' => false,
			'saver_url'      => '',
			'service_path'   => '',
			'goods'          => array(),
			'labels'         => self::modal_labels(),
		);

		if ( ! class_exists( '\Cdek\ShippingMethod', false ) ) {
			return $base;
		}

		try {
			$shipping = \Cdek\ShippingMethod::factory();
		} catch ( \Throwable $e ) {
			return array_merge( $base, array( 'reason' => 'cdek_factory_failed' ) );
		}

		$api_key = isset( $shipping->yandex_map_api_key ) ? trim( (string) $shipping->yandex_map_api_key ) : '';
		if ( '' === $api_key ) {
			return array_merge( $base, array( 'reason' => 'yandex_api_key_empty' ) );
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
			$service_path = self::resolve_default_service_path();
		}

		$map_ready = '' !== $service_path;
		$fallback    = ! $map_ready;

		return array(
			'enabled'        => true,
			'map_ready'      => $map_ready,
			'fallback'       => $fallback,
			'reason'         => $map_ready ? '' : 'service_path_missing',
			'apiKey'         => $api_key,
			'lang'           => $lang,
			'default_city'   => $city,
			'postcode'       => $postcode,
			'offices_json'   => $offices_json,
			'map_auto_close' => ! empty( $shipping->map_auto_close ),
			'saver_url'      => $saver,
			'service_path'   => $service_path,
			'goods'          => $goods,
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
			'map_unavailable_title'  => __( 'Список пунктов', 'mp-custom-checkout' ),
			'map_config_error'       => __( 'Карта недоступна: проверьте ключ карты и service.php плагина СДЭК.', 'mp-custom-checkout' ),
			'map_init_failed'        => __( 'Не удалось открыть карту.', 'mp-custom-checkout' ),
			'map_pick_failed'        => __( 'Не удалось получить код пункта.', 'mp-custom-checkout' ),
			'pick_required'          => __( 'Выберите пункт из списка.', 'mp-custom-checkout' ),
			'list_empty'             => __( 'Нет доступных точек.', 'mp-custom-checkout' ),
		);
	}

	/**
	 * URL прокси service.php из установленного плагина CDEK (виджет 3.x требует servicePath).
	 */
	private static function resolve_default_service_path(): string {
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
