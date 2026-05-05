<?php
/**
 * Меню и экран настроек плагина в админке WordPress.
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout\Admin\Hooks;

use MP\CustomCheckout\Admin\Config\AdminTabRegistry;
use MP\CustomCheckout\Diagnostics\CheckoutHealthChecks;
use MP\CustomCheckout\Logging\CheckoutLogStore;
use MP\CustomCheckout\Settings\AdminSectionsRegistry;
use MP\CustomCheckout\Settings\MotionSettingsResolver;
use MP\CustomCheckout\Settings\OptionKeys;
use MP\CustomCheckout\Settings\SafeSettingsResolver;
use MP\CustomCheckout\Settings\SettingsMigrationManager;

defined( 'ABSPATH' ) || exit;

final class AdminMenuHooks {
	public const PAGE_SLUG      = 'mp-custom-checkout';
	public const OPTION_GROUP   = 'mp_custom_checkout_settings_group';
	public const NAV_LAYOUT_KEY = 'mp_cc_admin_nav_layout';

	public static function register(): void {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_init', array( __CLASS__, 'handle_logs_actions' ) );
		add_action( 'admin_init', array( __CLASS__, 'handle_config_actions' ) );
	}

	public static function register_menu(): void {
		add_menu_page(
			__( 'MP Checkout', 'mp-custom-checkout' ),
			__( 'MP Checkout', 'mp-custom-checkout' ),
			'manage_options',
			self::PAGE_SLUG,
			array( __CLASS__, 'render_page' ),
			'dashicons-cart',
			56
		);
	}

	public static function register_settings(): void {
		register_setting(
			self::OPTION_GROUP,
			OptionKeys::MAIN,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize_settings' ),
				'default'           => SafeSettingsResolver::get_defaults_tree(),
			)
		);
	}

	/**
	 * @param mixed $value
	 * @return array<string, mixed>
	 */
	public static function sanitize_settings( $value ): array {
		$incoming = is_array( $value ) ? $value : array();
		$defaults = SafeSettingsResolver::get_defaults_tree();
		/*
		 * Экран настроек отдаёт в POST только поля активной вкладки. sanitize_by_shape() иначе
		 * подставляет дефолты из $defaults для каждого отсутствующего верхнего ключа — и при сохранении,
		 * например, только шага 1 в БД перезаписывались бы step_4 (купон, контакты), delivery и т.д.
		 */
		$stored = get_option( OptionKeys::MAIN, array() );
		$stored = is_array( $stored ) ? $stored : array();
		$merged  = array_replace_recursive( $stored, $incoming );
		$sanitized = self::sanitize_by_shape( $merged, $defaults, '' );
		$sanitized = self::normalize_delivery_settings( $sanitized );
		return self::normalize_motion_settings( $sanitized );
	}

	/**
	 * @param array<string, mixed> $settings
	 * @return array<string, mixed>
	 */
	private static function normalize_motion_settings( array $settings ): array {
		if ( ! isset( $settings[ OptionKeys::SECTION_MOTION ] ) || ! is_array( $settings[ OptionKeys::SECTION_MOTION ] ) ) {
			return $settings;
		}
		$defaults = SafeSettingsResolver::get_defaults_tree();
		$def_m    = isset( $defaults[ OptionKeys::SECTION_MOTION ] ) && is_array( $defaults[ OptionKeys::SECTION_MOTION ] )
			? $defaults[ OptionKeys::SECTION_MOTION ]
			: array();
		$merged   = array_replace_recursive( $def_m, $settings[ OptionKeys::SECTION_MOTION ] );
		$settings[ OptionKeys::SECTION_MOTION ] = MotionSettingsResolver::sanitize_section( $merged );
		return $settings;
	}

	/**
	 * @param array<string, mixed> $settings
	 * @return array<string, mixed>
	 */
	private static function normalize_delivery_settings( array $settings ): array {
		if ( ! isset( $settings[ OptionKeys::SECTION_DELIVERY ] ) || ! is_array( $settings[ OptionKeys::SECTION_DELIVERY ] ) ) {
			return $settings;
		}
		$delivery = $settings[ OptionKeys::SECTION_DELIVERY ];
		$catalog  = isset( $delivery['shipping_catalog'] ) && is_array( $delivery['shipping_catalog'] ) ? $delivery['shipping_catalog'] : array();
		$methods  = isset( $catalog['methods'] ) && is_array( $catalog['methods'] ) ? $catalog['methods'] : array();
		foreach ( $methods as $method_id => $method ) {
			if ( ! is_array( $method ) ) {
				unset( $methods[ $method_id ] );
				continue;
			}
			$method['price'] = isset( $method['price'] ) ? max( 0, (int) $method['price'] ) : 0;
			$method['eta']   = isset( $method['eta'] ) ? sanitize_text_field( (string) $method['eta'] ) : '';
			if ( isset( $method['wc_rate_id'] ) ) {
				$method['wc_rate_id'] = sanitize_text_field( (string) $method['wc_rate_id'] );
			}
			if ( isset( $method['tariffs'] ) && is_array( $method['tariffs'] ) ) {
				foreach ( $method['tariffs'] as $tariff_id => $tariff ) {
					if ( ! is_array( $tariff ) ) {
						unset( $method['tariffs'][ $tariff_id ] );
						continue;
					}
					$tariff['price'] = isset( $tariff['price'] ) ? max( 0, (int) $tariff['price'] ) : 0;
					$tariff['eta']   = isset( $tariff['eta'] ) ? sanitize_text_field( (string) $tariff['eta'] ) : '';
					if ( isset( $tariff['wc_rate_id'] ) ) {
						$tariff['wc_rate_id'] = sanitize_text_field( (string) $tariff['wc_rate_id'] );
					}
					$method['tariffs'][ $tariff_id ] = $tariff;
				}
			}
			$methods[ $method_id ] = $method;
		}
		$sort = isset( $catalog['sort_order'] ) && is_array( $catalog['sort_order'] ) ? $catalog['sort_order'] : array();
		$valid_sort = array();
		foreach ( $sort as $candidate ) {
			$id = sanitize_key( (string) $candidate );
			if ( '' === $id || ! isset( $methods[ $id ] ) ) {
				continue;
			}
			$valid_sort[] = $id;
		}
		foreach ( array_keys( $methods ) as $method_id ) {
			if ( ! in_array( $method_id, $valid_sort, true ) ) {
				$valid_sort[] = $method_id;
			}
		}
		$catalog['methods'] = $methods;
		$catalog['sort_order'] = $valid_sort;
		$delivery['shipping_catalog'] = $catalog;

		$allowed_modes = array( 'catalog', 'woocommerce', 'hybrid' );
		$pricing_raw   = isset( $delivery['pricing_mode'] ) ? sanitize_key( (string) $delivery['pricing_mode'] ) : 'catalog';
		$delivery['pricing_mode'] = in_array( $pricing_raw, $allowed_modes, true ) ? $pricing_raw : 'catalog';

		$wc_int = isset( $delivery['wc_integration'] ) && is_array( $delivery['wc_integration'] ) ? $delivery['wc_integration'] : array();
		$wc_int['respect_chosen_shipping_methods'] = ! empty( $wc_int['respect_chosen_shipping_methods'] );
		$delivery['wc_integration']                 = $wc_int;

		$settings[ OptionKeys::SECTION_DELIVERY ] = $delivery;
		return $settings;
	}

	/**
	 * Толщина рамки кружка шага: как на фронте (px/rem/em/vw/% или число → px).
	 */
	private static function sanitize_progress_step_index_border_width( string $raw, string $fallback ): string {
		$v = trim( wp_strip_all_tags( $raw ) );
		$v = str_replace( array( ';', '{', '}', "\n", "\r", "\t" ), '', $v );
		if ( '' === $v ) {
			return $fallback;
		}
		if ( preg_match( '/^\d+(?:\.\d+)?$/', $v ) ) {
			return $v . 'px';
		}
		if ( preg_match( '/^\d+(?:\.\d+)?(px|rem|em|vw|%)$/', $v ) ) {
			return $v;
		}
		return $fallback;
	}

	/**
	 * @param array<string, mixed> $incoming
	 * @param array<string, mixed> $shape
	 * @return array<string, mixed>
	 */
	private static function sanitize_by_shape( array $incoming, array $shape, string $path ): array {
		$result = array();
		foreach ( $shape as $key => $default_value ) {
			$raw = array_key_exists( $key, $incoming ) ? $incoming[ $key ] : $default_value;
			$node_path = '' === $path ? (string) $key : $path . '.' . (string) $key;
			if ( is_array( $default_value ) ) {
				if ( self::is_list_array( $default_value ) ) {
					$first_template = $default_value[0] ?? null;
					if ( is_array( $first_template ) && ! self::is_list_array( $first_template ) ) {
						$raw_list = is_array( $raw ) ? array_values( $raw ) : array();
						$out_rows = array();
						foreach ( $raw_list as $idx => $item ) {
							$item_array = is_array( $item ) ? $item : array();
							$iso_guess  = isset( $item_array['iso'] ) ? strtoupper( sanitize_text_field( (string) $item_array['iso'] ) ) : '';
							$row_shape  = $first_template;
							foreach ( $default_value as $def_row ) {
								if ( ! is_array( $def_row ) ) {
									continue;
								}
								$def_iso = isset( $def_row['iso'] ) ? strtoupper( (string) $def_row['iso'] ) : '';
								if ( '' !== $iso_guess && $def_iso === $iso_guess ) {
									$row_shape = $def_row;
									break;
								}
							}
							$out_rows[] = self::sanitize_by_shape( $item_array, $row_shape, $node_path . '[' . (string) $idx . ']' );
						}
						$result[ $key ] = $out_rows;
						continue;
					}
					if ( is_array( $raw ) ) {
						$list = array_values( array_map( 'sanitize_text_field', array_map( 'strval', $raw ) ) );
					} else {
						$list = array_filter( array_map( 'trim', explode( ',', sanitize_text_field( (string) $raw ) ) ) );
					}
					$result[ $key ] = array_values( $list );
					continue;
				}
				$raw_array       = is_array( $raw ) ? $raw : array();
				$result[ $key ]  = self::sanitize_by_shape( $raw_array, $default_value, $node_path );
				continue;
			}
			if ( is_bool( $default_value ) ) {
				$result[ $key ] = ! empty( $raw );
				continue;
			}
			if ( is_int( $default_value ) ) {
				$next = (int) $raw;
				if ( false !== strpos( $node_path, 'columns' ) ) {
					$next = max( 1, min( 4, $next ) );
				}
				if ( false !== strpos( $node_path, 'max' ) || false !== strpos( $node_path, 'min' ) ) {
					$next = max( 0, $next );
				}
				$result[ $key ] = $next;
				continue;
			}
			if ( is_float( $default_value ) ) {
				$result[ $key ] = (float) $raw;
				continue;
			}
			$string_raw = (string) $raw;
			if ( false !== strpos( $node_path, 'route_slug' ) ) {
				$result[ $key ] = sanitize_title( $string_raw );
				continue;
			}
			if ( false !== strpos( $node_path, 'url' ) ) {
				$result[ $key ] = esc_url_raw( $string_raw );
				continue;
			}
			if ( false !== strpos( $node_path, 'accent_color' ) || false !== strpos( $node_path, '.ui_tokens.' ) ) {
				$color = sanitize_hex_color( $string_raw );
				$result[ $key ] = $color ? $color : (string) $default_value;
				continue;
			}
			if ( false !== strpos( $node_path, 'styles.progress_step_index.border_width' ) ) {
				$result[ $key ] = self::sanitize_progress_step_index_border_width( $string_raw, (string) $default_value );
				continue;
			}
			if ( false !== strpos( $node_path, 'styles.progress_step_index.' ) ) {
				$norm  = trim( $string_raw );
				if ( '' !== $norm && '#' !== $norm[0] && ( preg_match( '/^[0-9a-fA-F]{3}$/', $norm ) || preg_match( '/^[0-9a-fA-F]{6}$/', $norm ) ) ) {
					$norm = '#' . $norm;
				}
				$color = sanitize_hex_color( $norm );
				$result[ $key ] = $color ? $color : (string) $default_value;
				continue;
			}
			if ( false !== strpos( $node_path, 'icon' ) ) {
				$result[ $key ] = sanitize_html_class( $string_raw );
				continue;
			}
			if ( false !== strpos( $node_path, 'help_style' ) ) {
				$style = sanitize_key( $string_raw );
				$result[ $key ] = in_array( $style, array( 'soft', 'outline', 'solid' ), true ) ? $style : 'soft';
				continue;
			}
			$result[ $key ] = sanitize_textarea_field( $string_raw );
		}
		return $result;
	}

	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$defaults = SafeSettingsResolver::get_defaults_tree();
		$stored   = get_option( OptionKeys::MAIN, array() );
		$stored   = is_array( $stored ) ? $stored : array();
		$settings = array_replace_recursive( $defaults, $stored );
		$branding = isset( $settings[ OptionKeys::SECTION_GENERAL ]['admin_branding'] ) && is_array( $settings[ OptionKeys::SECTION_GENERAL ]['admin_branding'] )
			? $settings[ OptionKeys::SECTION_GENERAL ]['admin_branding']
			: array();
		$brand_title       = isset( $branding['title'] ) ? (string) $branding['title'] : __( 'MP Custom Checkout — Настройки', 'mp-custom-checkout' );
		$brand_description = isset( $branding['description'] ) ? (string) $branding['description'] : __( 'Единый экран управления сценариями checkout, текстами, валидацией и визуальным поведением шагов.', 'mp-custom-checkout' );
		$brand_onboarding  = isset( $branding['onboarding'] ) ? (string) $branding['onboarding'] : '';
		$brand_icon        = isset( $branding['icon'] ) ? sanitize_html_class( (string) $branding['icon'] ) : 'dashicons-cart';
		$brand_accent      = isset( $branding['accent_color'] ) ? sanitize_hex_color( (string) $branding['accent_color'] ) : '#2271b1';
		$help_style        = isset( $branding['help_style'] ) ? sanitize_key( (string) $branding['help_style'] ) : 'soft';
		$tokens            = isset( $branding['ui_tokens'] ) && is_array( $branding['ui_tokens'] ) ? $branding['ui_tokens'] : array();
		$preview_enabled   = ! isset( $branding['preview_enabled'] ) || ! empty( $branding['preview_enabled'] );
		$token_bg          = isset( $tokens['bg'] ) ? sanitize_hex_color( (string) $tokens['bg'] ) : '#ffffff';
		$token_surface     = isset( $tokens['surface'] ) ? sanitize_hex_color( (string) $tokens['surface'] ) : '#fcfcfc';
		$token_border      = isset( $tokens['border'] ) ? sanitize_hex_color( (string) $tokens['border'] ) : '#dcdcde';
		$token_text        = isset( $tokens['text'] ) ? sanitize_hex_color( (string) $tokens['text'] ) : '#1f2328';
		$token_muted       = isset( $tokens['muted'] ) ? sanitize_hex_color( (string) $tokens['muted'] ) : '#4b5563';
		$token_risk_bg     = isset( $tokens['risk_bg'] ) ? sanitize_hex_color( (string) $tokens['risk_bg'] ) : '#fff7f7';
		$token_risk_border = isset( $tokens['risk_border'] ) ? sanitize_hex_color( (string) $tokens['risk_border'] ) : '#fca5a5';
		$inline_vars       = '--mp-cc-admin-accent:' . ( $brand_accent ?: '#2271b1' ) . ';'
			. '--mp-cc-admin-bg:' . ( $token_bg ?: '#ffffff' ) . ';'
			. '--mp-cc-admin-surface:' . ( $token_surface ?: '#fcfcfc' ) . ';'
			. '--mp-cc-admin-border:' . ( $token_border ?: '#dcdcde' ) . ';'
			. '--mp-cc-admin-text:' . ( $token_text ?: '#1f2328' ) . ';'
			. '--mp-cc-admin-muted:' . ( $token_muted ?: '#4b5563' ) . ';'
			. '--mp-cc-admin-risk-bg:' . ( $token_risk_bg ?: '#fff7f7' ) . ';'
			. '--mp-cc-admin-risk-border:' . ( $token_risk_border ?: '#fca5a5' ) . ';';
		$sections   = AdminSectionsRegistry::sections();
		$tabs       = AdminTabRegistry::tabs();
		$tab_ids    = array();
		foreach ( $tabs as $tab_meta ) {
			$tab_ids[] = isset( $tab_meta['id'] ) ? (string) $tab_meta['id'] : '';
		}
		$active_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( (string) $_GET['tab'] ) ) : '';
		if ( '' === $active_tab || ! in_array( $active_tab, $tab_ids, true ) ) {
			$active_tab = isset( $tab_ids[0] ) ? (string) $tab_ids[0] : OptionKeys::SECTION_GENERAL;
		}
		$layout = isset( $_GET['nav_layout'] ) ? sanitize_key( wp_unslash( (string) $_GET['nav_layout'] ) ) : '';
		if ( ! in_array( $layout, array( 'top', 'side' ), true ) ) {
			$layout = 'top';
		}
		?>
		<div class="wrap mp-cc-admin-shell mp-cc-admin-shell--<?php echo esc_attr( $layout ); ?> mp-cc-admin-shell--help-<?php echo esc_attr( $help_style ); ?>" style="<?php echo esc_attr( $inline_vars ); ?>">
			<h1 class="mp-cc-admin-shell__brand-title"><span class="dashicons <?php echo esc_attr( $brand_icon ); ?>" aria-hidden="true"></span> <?php echo esc_html( $brand_title ); ?></h1>
			<p class="description">
				<?php echo esc_html( $brand_description ); ?>
			</p>
			<?php if ( '' !== trim( $brand_onboarding ) ) : ?>
				<p class="mp-cc-admin-shell__tab-onboarding"><strong><?php esc_html_e( 'Onboarding:', 'mp-custom-checkout' ); ?></strong> <?php echo esc_html( $brand_onboarding ); ?></p>
			<?php endif; ?>
			<?php if ( $preview_enabled ) : ?>
				<div class="mp-cc-admin-shell__branding-preview" aria-label="<?php esc_attr_e( 'Превью брендирования админки', 'mp-custom-checkout' ); ?>">
					<span class="dashicons <?php echo esc_attr( $brand_icon ); ?>" aria-hidden="true"></span>
					<div>
						<strong><?php echo esc_html( $brand_title ); ?></strong>
						<p><?php echo esc_html( $brand_description ); ?></p>
					</div>
				</div>
			<?php endif; ?>
			<?php if ( isset( $_GET['settings-updated'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Настройки успешно сохранены.', 'mp-custom-checkout' ); ?></p></div>
			<?php endif; ?>

			<div class="mp-cc-admin-shell__layout-toggle">
				<a class="button button-small<?php echo 'top' === $layout ? ' button-primary' : ''; ?>" href="<?php echo esc_url( add_query_arg( array( 'page' => self::PAGE_SLUG, 'tab' => $active_tab, 'nav_layout' => 'top' ), admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Верхняя навигация', 'mp-custom-checkout' ); ?></a>
				<a class="button button-small<?php echo 'side' === $layout ? ' button-primary' : ''; ?>" href="<?php echo esc_url( add_query_arg( array( 'page' => self::PAGE_SLUG, 'tab' => $active_tab, 'nav_layout' => 'side' ), admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Боковая навигация', 'mp-custom-checkout' ); ?></a>
			</div>
			<div class="mp-cc-admin-shell__search" data-mp-cc-search-root="1">
				<input type="search" class="regular-text" placeholder="<?php echo esc_attr__( 'Поиск по настройкам (ключ, label, helper, path)...', 'mp-custom-checkout' ); ?>" data-mp-cc-settings-search="1" />
				<div class="mp-cc-admin-shell__filters" data-mp-cc-settings-filters="1">
					<button type="button" class="button button-small is-active" data-filter="all">Все</button>
					<button type="button" class="button button-small" data-filter="content">тексты</button>
					<button type="button" class="button button-small" data-filter="fields">поля</button>
					<button type="button" class="button button-small" data-filter="styles">стили</button>
					<button type="button" class="button button-small" data-filter="validation">валидация</button>
					<button type="button" class="button button-small" data-filter="logic">логика</button>
					<button type="button" class="button button-small" data-filter="mobile">mobile</button>
				</div>
				<p class="description" data-mp-cc-search-status="1"></p>
			</div>

			<div class="mp-cc-admin-shell__grid">
				<nav class="mp-cc-admin-shell__tabs" aria-label="<?php esc_attr_e( 'Навигация разделов', 'mp-custom-checkout' ); ?>">
					<?php foreach ( $tabs as $tab_meta ) : ?>
						<?php
						$section_id = isset( $tab_meta['id'] ) ? (string) $tab_meta['id'] : '';
						$label = isset( $tab_meta['label'] ) ? (string) $tab_meta['label'] : (string) $section_id;
						$url   = add_query_arg(
							array(
								'page'       => self::PAGE_SLUG,
								'tab'        => $section_id,
								'nav_layout' => $layout,
							),
							admin_url( 'admin.php' )
						);
						?>
						<a class="mp-cc-admin-shell__tab<?php echo (string) $section_id === $active_tab ? ' is-active' : ''; ?>" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $label ); ?></a>
					<?php endforeach; ?>
				</nav>

				<div class="mp-cc-admin-shell__content">
					<?php if ( OptionKeys::SECTION_LOGS === $active_tab ) : ?>
						<div data-mp-cc-admin-settings-form="1">
							<?php self::render_tab_fields( (string) $active_tab, $settings, $tabs ); ?>
						</div>
					<?php else : ?>
						<form method="post" action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>" data-mp-cc-admin-settings-form="1">
							<?php settings_fields( self::OPTION_GROUP ); ?>
							<?php self::render_tab_fields( (string) $active_tab, $settings, $tabs ); ?>
							<p class="submit">
								<button type="submit" class="button button-primary"><?php esc_html_e( 'Сохранить настройки', 'mp-custom-checkout' ); ?></button>
							</p>
						</form>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<script>
			(function () {
				var form = document.querySelector('[data-mp-cc-admin-settings-form="1"]');
				if (!form) { return; }
				var searchInput = document.querySelector('[data-mp-cc-settings-search="1"]');
				var filtersRoot = document.querySelector('[data-mp-cc-settings-filters="1"]');
				var status = document.querySelector('[data-mp-cc-search-status="1"]');
				var isDirty = false;
				var activeFilter = 'all';
				var installCheckoutWidthPresets = function () {
					var field = form.querySelector('.mp-cc-admin-shell__field[data-setting-path="general.checkout_layout.max_width"]');
					if (!field) { return; }
					var input = field.querySelector('input[type="text"], input[type="number"]');
					if (!input) { return; }
					if (field.querySelector('[data-mp-cc-width-presets="1"]')) { return; }
					var presets = [
						{ label: 'Компакт', value: '1140px' },
						{ label: 'Стандарт', value: '1280px' },
						{ label: 'Шире', value: '1360px' },
						{ label: 'Широкий', value: '1440px' },
						{ label: 'Full', value: '94%' }
					];
					var toolbar = document.createElement('div');
					toolbar.setAttribute('data-mp-cc-width-presets', '1');
					toolbar.style.display = 'flex';
					toolbar.style.flexWrap = 'wrap';
					toolbar.style.gap = '8px';
					toolbar.style.marginTop = '8px';
					toolbar.style.marginBottom = '2px';
					var current = String(input.value || '').trim();
					presets.forEach(function (preset) {
						var btn = document.createElement('button');
						btn.type = 'button';
						btn.className = 'button button-small' + (current === preset.value ? ' button-primary' : '');
						btn.textContent = preset.label;
						btn.setAttribute('data-width-value', preset.value);
						toolbar.appendChild(btn);
					});
					var hint = document.createElement('small');
					hint.className = 'description';
					hint.textContent = 'Быстрые пресеты + ручной ввод (например: 1320px, 82rem, 94%).';
					hint.style.display = 'block';
					hint.style.marginTop = '6px';
					field.appendChild(toolbar);
					field.appendChild(hint);
					var syncPresetButtons = function () {
						var val = String(input.value || '').trim();
						Array.prototype.forEach.call(toolbar.querySelectorAll('button[data-width-value]'), function (btn) {
							btn.classList.toggle('button-primary', String(btn.getAttribute('data-width-value') || '') === val);
						});
					};
					toolbar.addEventListener('click', function (event) {
						var btn = event.target && event.target.closest('button[data-width-value]');
						if (!btn) { return; }
						var nextValue = String(btn.getAttribute('data-width-value') || '').trim();
						if (!nextValue) { return; }
						input.value = nextValue;
						input.dispatchEvent(new Event('input', { bubbles: true }));
						input.dispatchEvent(new Event('change', { bubbles: true }));
						syncPresetButtons();
					});
					input.addEventListener('input', syncPresetButtons);
					input.addEventListener('change', syncPresetButtons);
				};
				var installGiftBarStylePresets = function () {
					var field = form.querySelector('.mp-cc-admin-shell__field[data-setting-path="step_4.payment_block.card_styles.gift_bar_style"]');
					if (!field) { return; }
					var input = field.querySelector('input[type="text"]');
					if (!input) { return; }
					if (field.querySelector('[data-mp-cc-giftbar-presets="1"]')) { return; }
					var presets = [
						{ label: 'Seal Inline', value: 'seal-inline' }
					];
					var toolbar = document.createElement('div');
					toolbar.setAttribute('data-mp-cc-giftbar-presets', '1');
					toolbar.style.display = 'flex';
					toolbar.style.flexWrap = 'wrap';
					toolbar.style.gap = '8px';
					toolbar.style.marginTop = '8px';
					toolbar.style.marginBottom = '2px';
					var current = String(input.value || '').trim().toLowerCase() || 'seal-inline';
					presets.forEach(function (preset) {
						var btn = document.createElement('button');
						btn.type = 'button';
						btn.className = 'button button-small' + (current === preset.value ? ' button-primary' : '');
						btn.textContent = preset.label;
						btn.setAttribute('data-giftbar-value', preset.value);
						toolbar.appendChild(btn);
					});
					var hint = document.createElement('small');
					hint.className = 'description';
					hint.textContent = 'Единственный поддерживаемый вариант: seal-inline.';
					hint.style.display = 'block';
					hint.style.marginTop = '6px';
					field.appendChild(toolbar);
					field.appendChild(hint);
					var syncPresetButtons = function () {
						var val = String(input.value || '').trim().toLowerCase();
						Array.prototype.forEach.call(toolbar.querySelectorAll('button[data-giftbar-value]'), function (btn) {
							btn.classList.toggle('button-primary', String(btn.getAttribute('data-giftbar-value') || '') === val);
						});
					};
					toolbar.addEventListener('click', function (event) {
						var btn = event.target && event.target.closest('button[data-giftbar-value]');
						if (!btn) { return; }
						var nextValue = String(btn.getAttribute('data-giftbar-value') || '').trim();
						if (!nextValue) { return; }
						input.value = nextValue;
						input.dispatchEvent(new Event('input', { bubbles: true }));
						input.dispatchEvent(new Event('change', { bubbles: true }));
						syncPresetButtons();
					});
					input.addEventListener('input', syncPresetButtons);
					input.addEventListener('change', syncPresetButtons);
				};
				var getScopePass = function (node) {
					if (activeFilter === 'all') { return true; }
					var scopes = String(node.getAttribute('data-setting-filters') || '');
					return scopes.split(',').indexOf(activeFilter) >= 0;
				};
				var applySearch = function () {
					var q = searchInput ? String(searchInput.value || '').toLowerCase().trim() : '';
					var rows = Array.prototype.slice.call(form.querySelectorAll('.mp-cc-admin-shell__field'));
					var visible = 0;
					rows.forEach(function (row) {
						var text = String(row.textContent || '').toLowerCase();
						var passQuery = !q || text.indexOf(q) >= 0;
						var passScope = getScopePass(row);
						var show = passQuery && passScope;
						row.style.display = show ? '' : 'none';
						if (show) { visible += 1; }
					});
					if (status) {
						status.textContent = 'Найдено настроек: ' + visible;
					}
				};
				var onBeforeUnload = function (event) {
					if (!isDirty) { return; }
					event.preventDefault();
					event.returnValue = '';
				};
				form.addEventListener('change', function () { isDirty = true; });
				form.addEventListener('input', function () { isDirty = true; });
				form.addEventListener('submit', function () { isDirty = false; });
				if (searchInput) {
					searchInput.addEventListener('input', applySearch);
				}
				if (filtersRoot) {
					filtersRoot.addEventListener('click', function (event) {
						var btn = event.target && event.target.closest('[data-filter]');
						if (!btn) { return; }
						var next = String(btn.getAttribute('data-filter') || 'all');
						activeFilter = next || 'all';
						Array.prototype.forEach.call(filtersRoot.querySelectorAll('[data-filter]'), function (x) {
							x.classList.toggle('is-active', x === btn);
						});
						applySearch();
					});
				}
				installCheckoutWidthPresets();
				installGiftBarStylePresets();
				applySearch();
				window.addEventListener('beforeunload', onBeforeUnload);
			})();
		</script>
		<?php
	}

	/**
	 * @param array<string, mixed> $settings
	 * @param array<string, array<string, mixed>> $tabs
	 */
	private static function render_tab_fields( string $tab_id, array $settings, array $tabs ): void {
		if ( OptionKeys::SECTION_LOGS === $tab_id ) {
			self::render_logs_tab( $tab_id, $tabs );
			return;
		}
		$name_prefix_tab = $tab_id;
		$path_tab        = $tab_id;
		$section_value = isset( $settings[ $tab_id ] ) ? $settings[ $tab_id ] : array();
		$section_value = is_array( $section_value ) ? $section_value : array();
		$defaults_tree   = SafeSettingsResolver::get_defaults_tree();
		$defaults_for_tab = isset( $defaults_tree[ $tab_id ] ) && is_array( $defaults_tree[ $tab_id ] ) ? $defaults_tree[ $tab_id ] : array();
		if ( ! empty( $defaults_for_tab ) ) {
			$section_value = array_replace_recursive( $defaults_for_tab, $section_value );
		}
		// По смыслу UI: настройки Получателя/Адреса показываем на вкладке шага 2
		// (исторически эти блоки хранятся в step_4).
		if ( OptionKeys::SECTION_STEP_2 === $tab_id ) {
			$s4 = isset( $settings[ OptionKeys::SECTION_STEP_4 ] ) && is_array( $settings[ OptionKeys::SECTION_STEP_4 ] )
				? $settings[ OptionKeys::SECTION_STEP_4 ]
				: array();
			$def4 = isset( $defaults_tree[ OptionKeys::SECTION_STEP_4 ] ) && is_array( $defaults_tree[ OptionKeys::SECTION_STEP_4 ] )
				? $defaults_tree[ OptionKeys::SECTION_STEP_4 ]
				: array();
			if ( ! empty( $def4 ) ) {
				$s4 = array_replace_recursive( $def4, $s4 );
			}
			$section_value = array(
				'contact_block' => isset( $s4['contact_block'] ) && is_array( $s4['contact_block'] ) ? $s4['contact_block'] : array(),
				'address_block' => isset( $s4['address_block'] ) && is_array( $s4['address_block'] ) ? $s4['address_block'] : array(),
				'address_geo'   => isset( $s4['address_geo'] ) && is_array( $s4['address_geo'] ) ? $s4['address_geo'] : array(),
			);
			$name_prefix_tab = OptionKeys::SECTION_STEP_4;
			$path_tab        = OptionKeys::SECTION_STEP_4;
		}
		// На вкладке шага 4 скрываем эти блоки, чтобы не дублировать.
		if ( OptionKeys::SECTION_STEP_4 === $tab_id ) {
			unset( $section_value['contact_block'], $section_value['address_block'], $section_value['address_geo'] );
		}
		$title = isset( AdminSectionsRegistry::sections()[ $tab_id ]['label'] ) ? (string) AdminSectionsRegistry::sections()[ $tab_id ]['label'] : $tab_id;
		$description = isset( $tabs[ $tab_id ]['description'] ) ? (string) $tabs[ $tab_id ]['description'] : '';
		$onboarding = isset( $tabs[ $tab_id ]['onboarding'] ) ? (string) $tabs[ $tab_id ]['onboarding'] : '';
		echo '<h2>' . esc_html( $title ) . '</h2>';
		if ( '' !== $description ) {
			echo '<p class="mp-cc-admin-shell__tab-description">' . esc_html( $description ) . '</p>';
		}
		if ( '' !== $onboarding ) {
			echo '<p class="mp-cc-admin-shell__tab-onboarding"><strong>' . esc_html__( 'Onboarding:', 'mp-custom-checkout' ) . '</strong> ' . esc_html( $onboarding ) . '</p>';
		}
		echo '<div class="mp-cc-admin-shell__fields">';
		self::render_field_group( OptionKeys::MAIN . '[' . $name_prefix_tab . ']', $section_value, $path_tab );
		if ( OptionKeys::SECTION_MOTION === $tab_id ) {
			self::render_motion_admin_preview_block();
		}
		self::render_supplemental_groups_for_tab( $tab_id, $settings );
		echo '</div>';
	}

	/**
	 * @param array<string, array<string, mixed>> $tabs
	 */
	private static function render_logs_tab( string $tab_id, array $tabs ): void {
		$title = isset( AdminSectionsRegistry::sections()[ $tab_id ]['label'] ) ? (string) AdminSectionsRegistry::sections()[ $tab_id ]['label'] : $tab_id;
		$description = isset( $tabs[ $tab_id ]['description'] ) ? (string) $tabs[ $tab_id ]['description'] : '';
		echo '<h2>' . esc_html( $title ) . '</h2>';
		if ( '' !== $description ) {
			echo '<p class="mp-cc-admin-shell__tab-description">' . esc_html( $description ) . '</p>';
		}
		$filters = self::read_log_filters();
		$rows = CheckoutLogStore::query( $filters );
		echo '<div class="mp-cc-admin-shell__fields">';
		echo '<div class="mp-cc-admin-shell__fieldset">';
		echo '<p><strong>' . esc_html__( 'Фильтры логов', 'mp-custom-checkout' ) . '</strong></p>';
		echo '<form method="get">';
		echo '<input type="hidden" name="page" value="' . esc_attr( self::PAGE_SLUG ) . '" />';
		echo '<input type="hidden" name="tab" value="' . esc_attr( OptionKeys::SECTION_LOGS ) . '" />';
		echo '<label class="mp-cc-admin-shell__field"><span class="mp-cc-admin-shell__field-label">Level</span><input type="text" name="mp_cc_log_level" value="' . esc_attr( $filters['level'] ) . '" /></label>';
		echo '<label class="mp-cc-admin-shell__field"><span class="mp-cc-admin-shell__field-label">Source</span><input type="text" name="mp_cc_log_source" value="' . esc_attr( $filters['source'] ) . '" /></label>';
		echo '<label class="mp-cc-admin-shell__field"><span class="mp-cc-admin-shell__field-label">Channel</span><input type="text" name="mp_cc_log_channel" value="' . esc_attr( $filters['channel'] ) . '" /></label>';
		echo '<label class="mp-cc-admin-shell__field"><span class="mp-cc-admin-shell__field-label">Event</span><input type="text" name="mp_cc_log_event_type" value="' . esc_attr( $filters['event_type'] ) . '" /></label>';
		echo '<label class="mp-cc-admin-shell__field"><span class="mp-cc-admin-shell__field-label">Search</span><input type="text" name="mp_cc_log_search" value="' . esc_attr( $filters['search'] ) . '" /></label>';
		submit_button( __( 'Применить фильтры', 'mp-custom-checkout' ), 'secondary', '', false );
		echo '</form>';
		echo '</div>';
		echo '<div class="mp-cc-admin-shell__fieldset">';
		echo '<p><strong>' . esc_html__( 'Инструменты эксплуатации', 'mp-custom-checkout' ) . '</strong></p>';
		echo '<form method="post">';
		wp_nonce_field( 'mp_cc_logs_actions', 'mp_cc_logs_nonce' );
		echo '<input type="hidden" name="mp_cc_logs_action" value="export_json" />';
		echo '<input type="hidden" name="mp_cc_log_level" value="' . esc_attr( $filters['level'] ) . '" />';
		echo '<input type="hidden" name="mp_cc_log_source" value="' . esc_attr( $filters['source'] ) . '" />';
		echo '<input type="hidden" name="mp_cc_log_channel" value="' . esc_attr( $filters['channel'] ) . '" />';
		echo '<input type="hidden" name="mp_cc_log_event_type" value="' . esc_attr( $filters['event_type'] ) . '" />';
		echo '<input type="hidden" name="mp_cc_log_search" value="' . esc_attr( $filters['search'] ) . '" />';
		submit_button( __( 'Экспорт JSON', 'mp-custom-checkout' ), 'secondary', '', false );
		echo '</form>';
		echo '<form method="post" style="margin-top:8px">';
		wp_nonce_field( 'mp_cc_logs_actions', 'mp_cc_logs_nonce' );
		echo '<input type="hidden" name="mp_cc_logs_action" value="clear_logs" />';
		submit_button( __( 'Очистить логи', 'mp-custom-checkout' ), 'delete', '', false );
		echo '</form>';
		echo '</div>';
		echo '<div class="mp-cc-admin-shell__fieldset">';
		echo '<p><strong>' . sprintf( esc_html__( 'Записей: %d', 'mp-custom-checkout' ), count( $rows ) ) . '</strong></p>';
		foreach ( $rows as $idx => $row ) {
			$time = isset( $row['timestamp'] ) ? (string) $row['timestamp'] : '';
			$level = isset( $row['level'] ) ? (string) $row['level'] : '';
			$source = isset( $row['source'] ) ? (string) $row['source'] : '';
			$message = isset( $row['message'] ) ? (string) $row['message'] : '';
			$event = isset( $row['event_type'] ) ? (string) $row['event_type'] : '';
			$channel = isset( $row['channel'] ) ? (string) $row['channel'] : '';
			$context = isset( $row['context'] ) && is_array( $row['context'] ) ? $row['context'] : array();
			echo '<details class="mp-cc-admin-shell__fieldset"' . ( 0 === $idx ? ' open' : '' ) . '>';
			echo '<summary><span>[' . esc_html( $level ) . '] ' . esc_html( $message ) . '</span><em class="mp-cc-admin-shell__type-badge mp-cc-admin-shell__type-badge--logic">' . esc_html( $source ) . '</em></summary>';
			echo '<p><code>' . esc_html( $time ) . '</code> | <code>' . esc_html( $event ) . '</code> | <code>' . esc_html( $channel ) . '</code></p>';
			echo '<p><strong>' . esc_html__( 'Контекст', 'mp-custom-checkout' ) . '</strong></p>';
			echo '<textarea class="large-text code" rows="6" readonly>' . esc_textarea( wp_json_encode( $context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT ) ?: '{}' ) . '</textarea>';
			echo '</details>';
		}
		echo '</div>';
		echo '</div>';
	}

	public static function handle_logs_actions(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( 'POST' !== strtoupper( (string) ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) ) {
			return;
		}
		$action = isset( $_POST['mp_cc_logs_action'] ) ? sanitize_key( wp_unslash( (string) $_POST['mp_cc_logs_action'] ) ) : '';
		if ( '' === $action ) {
			return;
		}
		$page = isset( $_REQUEST['page'] ) ? sanitize_key( wp_unslash( (string) $_REQUEST['page'] ) ) : '';
		if ( self::PAGE_SLUG !== $page ) {
			return;
		}
		$tab = isset( $_REQUEST['tab'] ) ? sanitize_key( wp_unslash( (string) $_REQUEST['tab'] ) ) : '';
		if ( OptionKeys::SECTION_LOGS !== $tab ) {
			return;
		}
		check_admin_referer( 'mp_cc_logs_actions', 'mp_cc_logs_nonce' );
		if ( 'clear_logs' === $action ) {
			CheckoutLogStore::clear();
			wp_safe_redirect( add_query_arg( array( 'page' => self::PAGE_SLUG, 'tab' => OptionKeys::SECTION_LOGS, 'logs_cleared' => '1' ), admin_url( 'admin.php' ) ) );
			exit;
		}
		if ( 'export_json' === $action ) {
			$rows = CheckoutLogStore::query( self::read_log_filters( true ) );
			nocache_headers();
			header( 'Content-Type: application/json; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename="mp-cc-logs-' . gmdate( 'Ymd-His' ) . '.json"' );
			echo wp_json_encode( $rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT );
			exit;
		}
	}

	/**
	 * @return array<string, string>
	 */
	private static function read_log_filters( bool $prefer_post = false ): array {
		$src = $prefer_post ? $_POST : $_GET;
		return array(
			'level'      => isset( $src['mp_cc_log_level'] ) ? sanitize_key( wp_unslash( (string) $src['mp_cc_log_level'] ) ) : '',
			'source'     => isset( $src['mp_cc_log_source'] ) ? sanitize_key( wp_unslash( (string) $src['mp_cc_log_source'] ) ) : '',
			'channel'    => isset( $src['mp_cc_log_channel'] ) ? sanitize_key( wp_unslash( (string) $src['mp_cc_log_channel'] ) ) : '',
			'event_type' => isset( $src['mp_cc_log_event_type'] ) ? sanitize_key( wp_unslash( (string) $src['mp_cc_log_event_type'] ) ) : '',
			'search'     => isset( $src['mp_cc_log_search'] ) ? sanitize_text_field( wp_unslash( (string) $src['mp_cc_log_search'] ) ) : '',
		);
	}

	/**
	 * Рендерит дополнительные конфигурационные блоки, которые хранятся в virtual-ключах.
	 *
	 * @param array<string, mixed> $settings
	 */
	private static function render_supplemental_groups_for_tab( string $tab_id, array $settings ): void {
		if ( OptionKeys::SECTION_SERVICE === $tab_id ) {
			self::render_config_io_block();
			self::render_supplemental_group(
				__( 'Service Flags', 'mp-custom-checkout' ),
				OptionKeys::MAIN . '[' . OptionKeys::KEY_FEATURE_FLAGS . ']',
				isset( $settings[ OptionKeys::KEY_FEATURE_FLAGS ] ) ? $settings[ OptionKeys::KEY_FEATURE_FLAGS ] : array(),
				OptionKeys::KEY_FEATURE_FLAGS,
				'logic'
			);
			self::render_supplemental_group(
				__( 'Step Registry Map', 'mp-custom-checkout' ),
				OptionKeys::MAIN . '[' . OptionKeys::KEY_REGISTRY . ']',
				isset( $settings[ OptionKeys::KEY_REGISTRY ] ) ? $settings[ OptionKeys::KEY_REGISTRY ] : array(),
				OptionKeys::KEY_REGISTRY,
				'logic'
			);
			self::render_health_checks_group();
		}
	}

	/**
	 * Блок «Конфигурация: экспорт и импорт» во вкладке «Служебное».
	 */
	private static function render_config_io_block(): void {
		$import_messages = array(
			'no_file' => __( 'Файл не выбран.', 'mp-custom-checkout' ),
			'upload'  => __( 'Ошибка загрузки файла.', 'mp-custom-checkout' ),
			'size'    => __( 'Файл слишком большой (>5 МБ) или пустой.', 'mp-custom-checkout' ),
			'read'    => __( 'Не удалось прочитать файл.', 'mp-custom-checkout' ),
			'json'    => __( 'Файл не является валидным JSON.', 'mp-custom-checkout' ),
			'format'  => __( 'Файл не похож на конфиг MP Custom Checkout (поле «format» не совпадает).', 'mp-custom-checkout' ),
			'empty'   => __( 'В файле нет настроек для импорта.', 'mp-custom-checkout' ),
		);

		echo '<details class="mp-cc-admin-shell__fieldset" open>';
		echo '<summary><span>' . esc_html__( 'Конфигурация: экспорт и импорт', 'mp-custom-checkout' ) . '</span><em class="mp-cc-admin-shell__type-badge mp-cc-admin-shell__type-badge--logic">' . esc_html__( 'перенос', 'mp-custom-checkout' ) . '</em></summary>';
		echo '<p class="description">' . esc_html__( 'Скачайте JSON со всеми настройками плагина и загрузите его на другом сайте, чтобы перенести конфигурацию целиком.', 'mp-custom-checkout' ) . '</p>';

		if ( isset( $_GET['mp_cc_config_imported'] ) ) {
			echo '<div class="notice notice-success inline"><p>' . esc_html__( 'Конфигурация успешно импортирована. Все настройки плагина заменены значениями из файла.', 'mp-custom-checkout' ) . '</p></div>';
		}
		if ( isset( $_GET['mp_cc_config_exported'] ) ) {
			// На случай блокировки скачивания, отдельная ветка обычно не нужна — экспорт сам отдаёт файл.
			echo '<div class="notice notice-success inline"><p>' . esc_html__( 'Экспорт выполнен.', 'mp-custom-checkout' ) . '</p></div>';
		}
		if ( isset( $_GET['mp_cc_config_import_error'] ) ) {
			$code    = sanitize_key( wp_unslash( (string) $_GET['mp_cc_config_import_error'] ) );
			$message = isset( $import_messages[ $code ] ) ? $import_messages[ $code ] : __( 'Не удалось импортировать конфиг.', 'mp-custom-checkout' );
			echo '<div class="notice notice-error inline"><p>' . esc_html( $message ) . '</p></div>';
		}

		echo '<div class="mp-cc-admin-shell__fieldset" style="margin-bottom:12px">';
		echo '<p><strong>' . esc_html__( 'Экспорт', 'mp-custom-checkout' ) . '</strong></p>';
		echo '<p class="description">' . esc_html(
			sprintf(
				/* translators: %s: schema version */
				__( 'Сохранит все настройки в JSON-файл (версия схемы %s). Файл можно загрузить на другом сайте для воспроизведения конфигурации.', 'mp-custom-checkout' ),
				OptionKeys::SETTINGS_SCHEMA_VERSION
			)
		) . '</p>';
		echo '<form method="post">';
		wp_nonce_field( 'mp_cc_config_actions', 'mp_cc_config_nonce' );
		echo '<input type="hidden" name="mp_cc_config_action" value="export_config" />';
		submit_button( __( 'Скачать конфиг (JSON)', 'mp-custom-checkout' ), 'secondary', '', false );
		echo '</form>';
		echo '</div>';

		echo '<div class="mp-cc-admin-shell__fieldset">';
		echo '<p><strong>' . esc_html__( 'Импорт', 'mp-custom-checkout' ) . '</strong></p>';
		echo '<p class="description">' . esc_html__( 'Импорт ПОЛНОСТЬЮ перезапишет настройки плагина значениями из файла. Перед импортом рекомендуется сделать экспорт текущей конфигурации.', 'mp-custom-checkout' ) . '</p>';
		$confirm_text = __( 'Импорт перезапишет ВСЕ настройки плагина. Продолжить?', 'mp-custom-checkout' );
		echo '<form method="post" enctype="multipart/form-data" onsubmit="return confirm(\'' . esc_js( $confirm_text ) . '\');">';
		wp_nonce_field( 'mp_cc_config_actions', 'mp_cc_config_nonce' );
		echo '<input type="hidden" name="mp_cc_config_action" value="import_config" />';
		echo '<p><label class="mp-cc-admin-shell__field"><span class="mp-cc-admin-shell__field-label">' . esc_html__( 'JSON-файл с настройками', 'mp-custom-checkout' ) . '</span><input type="file" name="mp_cc_config_file" accept="application/json,.json" required /></label></p>';
		submit_button( __( 'Импортировать конфиг', 'mp-custom-checkout' ), 'primary', '', false );
		echo '</form>';
		echo '</div>';

		echo '</details>';
	}

	/**
	 * Обработчик POST-действий экспорта/импорта конфигурации.
	 */
	public static function handle_config_actions(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( 'POST' !== strtoupper( (string) ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) ) {
			return;
		}
		$action = isset( $_POST['mp_cc_config_action'] ) ? sanitize_key( wp_unslash( (string) $_POST['mp_cc_config_action'] ) ) : '';
		if ( 'export_config' !== $action && 'import_config' !== $action ) {
			return;
		}
		$page = isset( $_REQUEST['page'] ) ? sanitize_key( wp_unslash( (string) $_REQUEST['page'] ) ) : '';
		if ( self::PAGE_SLUG !== $page ) {
			return;
		}
		check_admin_referer( 'mp_cc_config_actions', 'mp_cc_config_nonce' );

		if ( 'export_config' === $action ) {
			self::do_export_config();
			return;
		}
		if ( 'import_config' === $action ) {
			self::do_import_config();
		}
	}

	private static function do_export_config(): void {
		$settings = get_option( OptionKeys::MAIN, array() );
		$settings = is_array( $settings ) ? $settings : array();

		$payload = array(
			'format'         => 'mp-custom-checkout/config',
			'format_version' => 1,
			'schema_version' => OptionKeys::SETTINGS_SCHEMA_VERSION,
			'plugin_version' => defined( 'MP_CUSTOM_CHECKOUT_VERSION' ) ? (string) MP_CUSTOM_CHECKOUT_VERSION : '',
			'exported_at'    => gmdate( 'c' ),
			'site_url'       => (string) get_site_url(),
			'settings'       => $settings,
		);

		$json = wp_json_encode( $payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT );
		if ( ! is_string( $json ) ) {
			$json = '{}';
		}

		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="mp-cc-config-' . gmdate( 'Ymd-His' ) . '.json"' );
		header( 'Content-Length: ' . (string) strlen( $json ) );
		echo $json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	private static function do_import_config(): void {
		$tab = isset( $_REQUEST['tab'] ) ? sanitize_key( wp_unslash( (string) $_REQUEST['tab'] ) ) : OptionKeys::SECTION_SERVICE;
		if ( '' === $tab ) {
			$tab = OptionKeys::SECTION_SERVICE;
		}
		$redirect_base = add_query_arg(
			array(
				'page' => self::PAGE_SLUG,
				'tab'  => $tab,
			),
			admin_url( 'admin.php' )
		);

		$fail = static function ( string $code ) use ( $redirect_base ): void {
			wp_safe_redirect( add_query_arg( 'mp_cc_config_import_error', $code, $redirect_base ) );
			exit;
		};

		if ( ! isset( $_FILES['mp_cc_config_file'] ) || ! is_array( $_FILES['mp_cc_config_file'] ) ) {
			$fail( 'no_file' );
		}
		$file = $_FILES['mp_cc_config_file'];
		$err  = isset( $file['error'] ) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;
		if ( UPLOAD_ERR_NO_FILE === $err ) {
			$fail( 'no_file' );
		}
		if ( UPLOAD_ERR_OK !== $err ) {
			$fail( 'upload' );
		}
		$tmp_name = isset( $file['tmp_name'] ) ? (string) $file['tmp_name'] : '';
		if ( '' === $tmp_name || ! is_uploaded_file( $tmp_name ) ) {
			$fail( 'upload' );
		}
		$size = isset( $file['size'] ) ? (int) $file['size'] : 0;
		if ( $size <= 0 || $size > 5 * 1024 * 1024 ) {
			$fail( 'size' );
		}

		$contents = file_get_contents( $tmp_name );
		if ( false === $contents || '' === $contents ) {
			$fail( 'read' );
		}

		$decoded = json_decode( $contents, true );
		if ( ! is_array( $decoded ) ) {
			$fail( 'json' );
		}

		$format = isset( $decoded['format'] ) ? (string) $decoded['format'] : '';
		if ( 'mp-custom-checkout/config' !== $format ) {
			$fail( 'format' );
		}

		$incoming = isset( $decoded['settings'] ) && is_array( $decoded['settings'] ) ? $decoded['settings'] : array();
		if ( empty( $incoming ) ) {
			$fail( 'empty' );
		}

		$sanitized = self::sanitize_settings( $incoming );

		update_option( OptionKeys::MAIN, $sanitized, false );

		$imported_version = isset( $decoded['schema_version'] ) ? (string) $decoded['schema_version'] : '0';
		if ( '' === $imported_version ) {
			$imported_version = '0';
		}
		update_option( OptionKeys::DB_VERSION, $imported_version, false );

		SettingsMigrationManager::maybe_migrate();
		SafeSettingsResolver::clear_cache();

		wp_safe_redirect( add_query_arg( 'mp_cc_config_imported', '1', $redirect_base ) );
		exit;
	}

	private static function render_motion_admin_preview_block(): void {
		$presets = MotionSettingsResolver::duration_presets_desktop_ms();
		$json     = wp_json_encode( $presets, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		if ( ! is_string( $json ) ) {
			$json = '{}';
		}
		echo '<div class="mp-cc-admin-motion-preview" data-mp-cc-motion-preview="1" data-motion-duration-presets="' . esc_attr( $json ) . '">';
		echo '<details class="mp-cc-admin-shell__fieldset" open>';
		echo '<summary><span>' . esc_html__( 'Live preview анимаций', 'mp-custom-checkout' ) . '</span><em class="mp-cc-admin-shell__type-badge mp-cc-admin-shell__type-badge--styles">' . esc_html__( 'стили', 'mp-custom-checkout' ) . '</em></summary>';
		echo '<p class="description">' . esc_html__( 'Значения подхватываются из полей формы выше (включая mobile). Кнопки пресетов заполняют только длительности desktop; «Проиграть» не сохраняет настройки.', 'mp-custom-checkout' ) . '</p>';
		echo '<div class="mp-cc-admin-motion-preview__toolbar">';
		echo '<span class="mp-cc-admin-motion-preview__label">' . esc_html__( 'Пресеты desktop (мс)', 'mp-custom-checkout' ) . '</span>';
		echo '<button type="button" class="button button-small" data-mp-cc-motion-preset="fast">' . esc_html__( 'Быстро', 'mp-custom-checkout' ) . '</button>';
		echo '<button type="button" class="button button-small" data-mp-cc-motion-preset="balanced">' . esc_html__( 'Сбалансировано', 'mp-custom-checkout' ) . '</button>';
		echo '<button type="button" class="button button-small" data-mp-cc-motion-preset="smooth">' . esc_html__( 'Плавно', 'mp-custom-checkout' ) . '</button>';
		echo '<button type="button" class="button button-primary" data-mp-cc-motion-play="1">' . esc_html__( 'Проиграть', 'mp-custom-checkout' ) . '</button>';
		echo '</div>';
		echo '<div class="mp-cc-admin-motion-preview__stage" data-mp-cc-motion-stage="1">';
		echo '<div class="mp-cc-admin-motion-preview__col mp-cc-admin-motion-preview__col--rail">';
		echo '<div class="mp-cc-admin-motion-preview__rail-track" aria-hidden="true"></div>';
		echo '<div class="mp-cc-admin-motion-preview__rail-fill" data-mp-cc-motion-rail-fill="1"></div>';
		echo '</div>';
		echo '<div class="mp-cc-admin-motion-preview__col mp-cc-admin-motion-preview__col--main">';
		echo '<div class="mp-cc-admin-motion-preview__panel" data-mp-cc-motion-panel="1"><span class="mp-cc-admin-motion-preview__panel-title">' . esc_html__( 'Шаг', 'mp-custom-checkout' ) . '</span></div>';
		echo '<div class="mp-cc-admin-motion-preview__amount"><span class="mp-cc-admin-motion-preview__amount-label">' . esc_html__( 'Итого', 'mp-custom-checkout' ) . '</span> ';
		echo '<span class="mp-cc-admin-motion-preview__amount-val" data-mp-cc-motion-amount="1">12 900 ₽</span></div>';
		echo '</div>';
		echo '</div>';
		echo '<p class="mp-cc-admin-motion-preview__hint description">' . esc_html__( 'ease_profile: snappy | balanced | smooth | custom (при custom используются поля ease.standard / ease.emphasized).', 'mp-custom-checkout' ) . '</p>';
		echo '</details></div>';
	}

	private static function render_health_checks_group(): void {
		$checks = CheckoutHealthChecks::collect();
		$summary = CheckoutHealthChecks::summary();
		if ( empty( $checks ) ) {
			return;
		}
		echo '<details class="mp-cc-admin-shell__fieldset" open>';
		echo '<summary><span>' . esc_html__( 'Checkout Health Checks', 'mp-custom-checkout' ) . '</span><em class="mp-cc-admin-shell__type-badge mp-cc-admin-shell__type-badge--validation">' . esc_html__( 'сервис', 'mp-custom-checkout' ) . '</em></summary>';
		echo '<p><strong>' . esc_html__( 'Health summary:', 'mp-custom-checkout' ) . '</strong> '
			. '<span class="mp-cc-admin-shell__scenario-badge">' . esc_html( sprintf( 'OK %d', (int) ( $summary['ok'] ?? 0 ) ) ) . '</span> '
			. '<span class="mp-cc-admin-shell__scenario-badge">' . esc_html( sprintf( 'WARN %d', (int) ( $summary['warn'] ?? 0 ) ) ) . '</span> '
			. '<span class="mp-cc-admin-shell__scenario-badge' . ( (int) ( $summary['fail'] ?? 0 ) > 0 ? ' is-risky' : '' ) . '">' . esc_html( sprintf( 'FAIL %d', (int) ( $summary['fail'] ?? 0 ) ) ) . '</span>'
			. '</p>';
		foreach ( $checks as $check ) {
			$status = isset( $check['status'] ) ? (string) $check['status'] : 'ok';
			$name = isset( $check['name'] ) ? (string) $check['name'] : '';
			$message = isset( $check['message'] ) ? (string) $check['message'] : '';
			$badge_class = 'mp-cc-admin-shell__scenario-badge';
			if ( 'fail' === $status ) {
				$badge_class .= ' is-risky';
			}
			echo '<p><strong>' . esc_html( $name ) . '</strong> <span class="' . esc_attr( $badge_class ) . '">' . esc_html( strtoupper( $status ) ) . '</span><br />' . esc_html( $message ) . '</p>';
		}
		echo '<p><a class="button button-small" href="' . esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&tab=' . OptionKeys::SECTION_LOGS ) ) . '">' . esc_html__( 'Открыть логи checkout', 'mp-custom-checkout' ) . '</a></p>';
		echo '</details>';
	}

	/**
	 * @param mixed $value
	 */
	private static function render_supplemental_group( string $title, string $name_prefix, $value, string $path, string $type ): void {
		if ( ! is_array( $value ) || empty( $value ) ) {
			return;
		}
		echo '<details class="mp-cc-admin-shell__fieldset" open>';
		echo '<summary><span>' . esc_html( $title ) . '</span><em class="mp-cc-admin-shell__type-badge mp-cc-admin-shell__type-badge--' . esc_attr( $type ) . '">' . esc_html( self::group_type_label( $type ) ) . '</em></summary>';
		self::render_field_group( $name_prefix, $value, $path );
		echo '</details>';
	}

	/**
	 * @param mixed $value
	 */
	private static function render_field_group( string $name_prefix, $value, string $path ): void {
		if ( ! is_array( $value ) ) {
			self::render_leaf_input( $name_prefix, $value, $path );
			return;
		}

		foreach ( $value as $key => $child ) {
			$key_str = (string) $key;
			$child_name = $name_prefix . '[' . $key_str . ']';
			$child_path = $path . '.' . $key_str;
			if ( is_array( $child ) && ! self::is_list_array( $child ) ) {
				$group_type = self::detect_group_type( $key_str, $child_path );
				echo '<details class="mp-cc-admin-shell__fieldset" open>';
				echo '<summary><span>' . esc_html( self::localized_group_label_for_path( $child_path, $key_str ) ) . '</span><em class="mp-cc-admin-shell__type-badge mp-cc-admin-shell__type-badge--' . esc_attr( $group_type ) . '">' . esc_html( self::group_type_label( $group_type ) ) . '</em></summary>';
				self::render_field_group( $child_name, $child, $child_path );
				echo '</details>';
				continue;
			}
			self::render_leaf_input( $child_name, $child, $child_path );
		}
	}

	/**
	 * @param mixed $value
	 */
	private static function render_leaf_input( string $name, $value, string $path ): void {
		// Поля price у методов с тарифами (post_russia/pvz/courier) — служебные fallback для режима «catalog»;
		// в режиме «woocommerce» они не используются и в UI лишние. Скрываем их в админке, но сохраняем значение
		// через hidden input, чтобы случайно не обнулить уже настроенный fallback при сабмите формы.
		if ( self::is_admin_field_hidden( $path ) ) {
			$hidden_value = is_scalar( $value ) ? (string) $value : '';
			echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="' . esc_attr( $hidden_value ) . '" />';
			return;
		}
		$label = self::localized_label_for_path( $path );
		$filters = self::build_filters_for_path( $path );
		$risky = self::is_risky_path( $path );
		$scenario = self::scenario_scope_for_path( $path );
		echo '<label class="mp-cc-admin-shell__field' . ( $risky ? ' is-risky' : '' ) . '" data-setting-path="' . esc_attr( $path ) . '" data-setting-filters="' . esc_attr( implode( ',', $filters ) ) . '" data-setting-scenario="' . esc_attr( $scenario ) . '">';
		echo '<span class="mp-cc-admin-shell__field-label">' . esc_html( ucfirst( $label ) ) . '</span>';
		if ( 'all' !== $scenario ) {
			echo '<span class="mp-cc-admin-shell__scenario-badge">' . esc_html( self::scenario_scope_label( $scenario ) ) . '</span>';
		}
		$help = self::tooltip_text_for_path( $path );
		if ( '' !== $help ) {
			echo '<span class="mp-cc-admin-shell__help" title="' . esc_attr( $help ) . '" aria-label="' . esc_attr( $help ) . '">?</span>';
			echo '<small class="mp-cc-admin-shell__hint">' . esc_html( $help ) . '</small>';
		}
		if ( is_bool( $value ) ) {
			// Без hidden WordPress не присылает ключ при снятом чекбоксе — булевы флаги нельзя сохранить как false.
			echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="0" />';
			echo '<input type="checkbox" name="' . esc_attr( $name ) . '" value="1"' . checked( true, $value, false ) . ' />';
		} elseif ( is_int( $value ) || is_float( $value ) ) {
			echo '<input type="number" step="any" class="regular-text" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $value ) . '" />';
		} elseif ( is_array( $value ) ) {
			$list = implode( ', ', array_map( 'strval', $value ) );
			echo '<input type="text" class="regular-text" name="' . esc_attr( $name ) . '" value="' . esc_attr( $list ) . '" />';
		} else {
			$raw = (string) $value;
			if ( strlen( $raw ) > 120 || false !== strpos( $raw, "\n" ) ) {
				echo '<textarea class="large-text" rows="3" name="' . esc_attr( $name ) . '">' . esc_textarea( $raw ) . '</textarea>';
			} else {
				echo '<input type="text" class="regular-text" name="' . esc_attr( $name ) . '" value="' . esc_attr( $raw ) . '" />';
			}
		}
		echo '<code class="mp-cc-admin-shell__field-path">' . esc_html( $path ) . '</code>';
		echo '</label>';
	}

	/**
	 * Локализованные подписи для наиболее важных полей админки.
	 */
	private static function localized_label_for_path( string $path ): string {
		$leaf = basename( str_replace( '.', '/', $path ) );
		if ( 'wc_rate_id' === $leaf && false !== strpos( $path, 'shipping_catalog.methods' ) && false === strpos( $path, '.tariffs.' ) ) {
			return __( 'WC rate ID (метод без тарифов)', 'mp-custom-checkout' );
		}
		$map = array(
			'general.checkout_layout.max_width'          => __( 'Максимальная ширина страницы checkout', 'mp-custom-checkout' ),
			'general.checkout_layout.vertical_padding'   => __( 'Вертикальные отступы блока checkout (сверху и снизу)', 'mp-custom-checkout' ),
			'step_1.address_form_style_preset'          => __( 'Пресет стиля формы адреса', 'mp-custom-checkout' ),
			'step_1.address_form_styles.card_bg'        => __( 'Фон карточки', 'mp-custom-checkout' ),
			'step_1.address_form_styles.card_border'    => __( 'Рамка карточки', 'mp-custom-checkout' ),
			'step_1.address_form_styles.card_radius'    => __( 'Скругление карточки', 'mp-custom-checkout' ),
			'step_1.address_form_styles.form_border_width' => __( 'Толщина внешней рамки блока (населённый пункт / доставка)', 'mp-custom-checkout' ),
			'step_1.address_form_styles.row_divider'    => __( 'Цвет линий между строками', 'mp-custom-checkout' ),
			'step_1.address_form_styles.row_divider_width' => __( 'Толщина линий между строками', 'mp-custom-checkout' ),
			'step_1.address_form_styles.divider_after_city_width' => __( 'Линия под первой строкой (населённый пункт): толщина или пусто', 'mp-custom-checkout' ),
			'step_1.address_form_styles.divider_after_method_width' => __( 'Линия под способом доставки: толщина или пусто', 'mp-custom-checkout' ),
			'step_1.address_form_styles.label_color'    => __( 'Цвет названий полей', 'mp-custom-checkout' ),
			'step_1.address_form_styles.label_size'     => __( 'Размер названий полей', 'mp-custom-checkout' ),
			'step_1.address_form_styles.value_color'    => __( 'Цвет значений', 'mp-custom-checkout' ),
			'step_1.address_form_styles.value_size'     => __( 'Размер значений', 'mp-custom-checkout' ),
			'step_1.address_form_styles.placeholder_color' => __( 'Цвет плейсхолдера', 'mp-custom-checkout' ),
			'step_1.address_form_styles.option_title_color' => __( 'Цвет названия способа доставки', 'mp-custom-checkout' ),
			'step_1.address_form_styles.option_title_size'  => __( 'Размер названия способа доставки', 'mp-custom-checkout' ),
			'step_1.address_form_styles.option_hint_color'  => __( 'Цвет описания способа доставки', 'mp-custom-checkout' ),
			'step_1.address_form_styles.option_hint_size'   => __( 'Размер описания способа доставки', 'mp-custom-checkout' ),
			'step_1.address_form_styles.radio_border_color' => __( 'Цвет рамки радио-кнопки', 'mp-custom-checkout' ),
			'step_1.address_form_styles.radio_checked_color' => __( 'Цвет выбранной радио-кнопки', 'mp-custom-checkout' ),
			'step_1.address_form_styles.edit_btn_bg'      => __( 'Фон кнопки «другой»', 'mp-custom-checkout' ),
			'step_1.address_form_styles.edit_btn_border'  => __( 'Рамка кнопки «другой»', 'mp-custom-checkout' ),
			'step_1.address_form_styles.edit_btn_color'   => __( 'Цвет текста кнопки «другой»', 'mp-custom-checkout' ),
			'step_1.address_form_styles.edit_btn_radius'  => __( 'Скругление кнопки «другой»', 'mp-custom-checkout' ),
			'step_1.step_panel_screen_styles.border_width' => __( 'Панель шага (.mp-cc-step-panel): толщина рамки', 'mp-custom-checkout' ),
			'step_1.step_panel_screen_styles.border_color' => __( 'Панель шага: цвет рамки (пусто — как у темы)', 'mp-custom-checkout' ),
			'step_1.step_panel_screen_styles.box_shadow'   => __( 'Панель шага: box-shadow (пусто — как у темы)', 'mp-custom-checkout' ),
			'step_4.recipient_step_panel_styles.border_width' => __( 'Панель шага «Получатель» (recipient): толщина рамки', 'mp-custom-checkout' ),
			'step_4.recipient_step_panel_styles.border_color' => __( 'Панель «Получатель»: цвет рамки (пусто — как у шага 1 / темы)', 'mp-custom-checkout' ),
			'step_4.recipient_step_panel_styles.box_shadow'   => __( 'Панель «Получатель»: box-shadow (пусто — как у шага 1)', 'mp-custom-checkout' ),
			'step_4.contact_block.layout.desktop_columns' => __( 'Контакты: колонки (desktop)', 'mp-custom-checkout' ),
			'step_4.contact_block.layout.tablet_columns'  => __( 'Контакты: колонки (tablet)', 'mp-custom-checkout' ),
			'step_4.contact_block.layout.mobile_columns'  => __( 'Контакты: колонки (mobile)', 'mp-custom-checkout' ),
			'step_4.contact_block.layout.grid_gap'        => __( 'Контакты: расстояние между полями', 'mp-custom-checkout' ),
			'step_4.contact_block.field_state_styles.invalid_style' => __( 'Контакты: стиль невалидного поля', 'mp-custom-checkout' ),
			'step_4.contact_block.field_state_styles.hint_style'    => __( 'Контакты: стиль подсказок', 'mp-custom-checkout' ),
			'step_4.contact_block.field_state_styles.focus_style'   => __( 'Контакты: стиль фокуса', 'mp-custom-checkout' ),
			'step_4.contact_block.field_state_styles.disabled_style'=> __( 'Контакты: стиль disabled-поля', 'mp-custom-checkout' ),
			'step_4.contact_block.title'                  => __( 'Контакты: заголовок блока', 'mp-custom-checkout' ),
			'step_4.contact_block.intro'                  => __( 'Контакты: подзаголовок блока', 'mp-custom-checkout' ),
			'step_4.address_block.title'                  => __( 'Адрес: заголовок блока', 'mp-custom-checkout' ),
			'step_4.address_block.intro'                  => __( 'Адрес: подзаголовок блока', 'mp-custom-checkout' ),
			'step_4.address_block.default_country'        => __( 'Адрес: страна по умолчанию', 'mp-custom-checkout' ),
			'step_4.address_block.postcode_max_length'    => __( 'Адрес: максимальная длина индекса', 'mp-custom-checkout' ),
			'step_4.address_block.labels.country'         => __( 'Адрес: подпись поля «Страна»', 'mp-custom-checkout' ),
			'step_4.address_block.labels.state'           => __( 'Адрес: подпись поля «Регион»', 'mp-custom-checkout' ),
			'step_4.address_block.labels.city'            => __( 'Адрес: подпись поля «Населённый пункт»', 'mp-custom-checkout' ),
			'step_4.address_block.labels.address_1'       => __( 'Адрес: подпись поля «Улица, дом»', 'mp-custom-checkout' ),
			'step_4.address_block.labels.address_2'       => __( 'Адрес: подпись поля «Квартира, офис»', 'mp-custom-checkout' ),
			'step_4.address_block.labels.postcode'        => __( 'Адрес: подпись поля «Почтовый индекс»', 'mp-custom-checkout' ),
			'step_4.recipient_styles.contact_card_bg'     => __( 'Шаг 2: фон карточки контактов', 'mp-custom-checkout' ),
			'step_4.recipient_styles.contact_card_border' => __( 'Шаг 2: рамка карточки контактов', 'mp-custom-checkout' ),
			'step_4.recipient_styles.contact_card_radius' => __( 'Шаг 2: скругление карточки контактов', 'mp-custom-checkout' ),
			'step_4.recipient_styles.contact_card_padding'=> __( 'Шаг 2: внутренние отступы карточки контактов', 'mp-custom-checkout' ),
			'step_4.recipient_styles.contact_header_divider' => __( 'Шаг 2: разделитель заголовка контактов', 'mp-custom-checkout' ),
			'step_4.recipient_styles.contact_title_size'  => __( 'Шаг 2: размер заголовка контактов', 'mp-custom-checkout' ),
			'step_4.recipient_styles.contact_intro_size'  => __( 'Шаг 2: размер подзаголовка контактов', 'mp-custom-checkout' ),
			'step_4.recipient_styles.contact_label_size'  => __( 'Шаг 2: размер названий полей контактов', 'mp-custom-checkout' ),
			'step_4.recipient_styles.contact_input_border'=> __( 'Шаг 2: рамка полей контактов', 'mp-custom-checkout' ),
			'step_4.recipient_styles.contact_input_radius'=> __( 'Шаг 2: скругление полей контактов', 'mp-custom-checkout' ),
			'step_4.recipient_styles.address_card_bg'     => __( 'Шаг 2: фон карточки адреса', 'mp-custom-checkout' ),
			'step_4.recipient_styles.address_card_border' => __( 'Шаг 2: рамка карточки адреса', 'mp-custom-checkout' ),
			'step_4.recipient_styles.address_card_radius' => __( 'Шаг 2: скругление карточки адреса', 'mp-custom-checkout' ),
			'step_4.recipient_styles.address_card_padding'=> __( 'Шаг 2: внутренние отступы карточки адреса', 'mp-custom-checkout' ),
			'step_4.recipient_styles.address_header_divider' => __( 'Шаг 2: разделитель заголовка адреса', 'mp-custom-checkout' ),
			'step_4.recipient_styles.address_title_size'  => __( 'Шаг 2: размер заголовка адреса', 'mp-custom-checkout' ),
			'step_4.recipient_styles.address_intro_size'  => __( 'Шаг 2: размер подзаголовка адреса', 'mp-custom-checkout' ),
			'step_4.payment_block.two_up_show_card_description' => __( 'Оплата (две карточки): показывать описание под заголовком', 'mp-custom-checkout' ),
			'step_4.payment_block.two_up_show_perk_tags'        => __( 'Оплата (две карточки): показывать теги под линией', 'mp-custom-checkout' ),
			'step_4.payment_block.two_up_minimal_idle_chrome'   => __( 'Оплата (две карточки): без рамки и кружка до выбора', 'mp-custom-checkout' ),
			'step_4.payment_block.card_styles.grid_gap'      => __( 'Оплата: расстояние между карточками', 'mp-custom-checkout' ),
			'step_4.payment_block.card_styles.card_padding'  => __( 'Оплата: внутренние отступы карточки', 'mp-custom-checkout' ),
			'step_4.payment_block.card_styles.card_radius'   => __( 'Оплата: скругление карточки', 'mp-custom-checkout' ),
			'step_4.payment_block.card_styles.card_border'   => __( 'Оплата: цвет рамки карточки', 'mp-custom-checkout' ),
			'step_4.payment_block.card_styles.card_shadow'   => __( 'Оплата: тень карточки-контейнера (two-up)', 'mp-custom-checkout' ),
			'step_4.payment_block.card_styles.shell_shadow'  => __( 'Оплата: тень изображения/области карты (visual)', 'mp-custom-checkout' ),
			'step_4.payment_block.card_styles.active_border' => __( 'Оплата: цвет рамки активной карточки', 'mp-custom-checkout' ),
			'step_4.payment_block.card_styles.active_glow_outer' => __( 'Оплата: свечение активной карточки (внешнее)', 'mp-custom-checkout' ),
			'step_4.payment_block.card_styles.active_glow_shadow' => __( 'Оплата: тень активной карточки', 'mp-custom-checkout' ),
			'step_4.payment_block.card_styles.selection_glow_color' => __( 'Оплата: цвет ореола при выборе (логотип, рамка two-up)', 'mp-custom-checkout' ),
			'step_4.payment_block.bank_card_visual.glow_color' => __( 'Оплата: цвет свечения после клика по карте (bank card visual)', 'mp-custom-checkout' ),
			'step_4.payment_block.bank_card_visual.glow_intensity' => __( 'Оплата: интенсивность свечения (soft | medium | strong)', 'mp-custom-checkout' ),
			'step_4.payment_block.card_styles.radio_size'    => __( 'Оплата: размер радиокнопки', 'mp-custom-checkout' ),
			'step_4.payment_block.card_styles.logo_height'   => __( 'Оплата: высота логотипа/картинки', 'mp-custom-checkout' ),
			'step_4.payment_block.card_styles.logo_max_width'=> __( 'Оплата: максимальная ширина логотипа/картинки', 'mp-custom-checkout' ),
			'step_4.payment_block.card_styles.two_up_card_min_height'  => __( 'Оплата (two-up): минимальная высота всей карточки', 'mp-custom-checkout' ),
			'step_4.payment_block.card_styles.two_up_shell_min_height' => __( 'Оплата (two-up): минимальная высота блока с логотипом', 'mp-custom-checkout' ),
			'step_4.payment_block.card_styles.title_size'    => __( 'Оплата: размер заголовка карточки', 'mp-custom-checkout' ),
			'step_4.payment_block.card_styles.desc_size'     => __( 'Оплата: размер описания карточки', 'mp-custom-checkout' ),
			'step_4.payment_block.card_styles.perk_font_size'=> __( 'Оплата: размер текста плашек преимуществ', 'mp-custom-checkout' ),
			'step_4.payment_block.card_styles.perk_radius'   => __( 'Оплата: скругление плашек преимуществ', 'mp-custom-checkout' ),
			'step_4.payment_block.card_styles.perk_padding'  => __( 'Оплата: внутренние отступы плашек преимуществ', 'mp-custom-checkout' ),
			'step_4.payment_block.card_styles.gift_card_width' => __( 'Оплата: ширина карточки подарочной карты рядом', 'mp-custom-checkout' ),
			'step_4.payment_block.card_styles.gift_bar_style' => __( 'Оплата: стиль нижней карточки подарочной карты', 'mp-custom-checkout' ),
			'step_4.payment_block.card_styles.gift_bar_bg' => __( 'Подарочная карта (нижний блок): фон', 'mp-custom-checkout' ),
			'step_4.payment_block.card_styles.gift_bar_border' => __( 'Подарочная карта (нижний блок): цвет рамки', 'mp-custom-checkout' ),
			'step_4.payment_block.card_styles.gift_bar_shadow' => __( 'Подарочная карта (нижний блок): тень', 'mp-custom-checkout' ),
			'step_4.payment_block.card_styles.gift_bar_title_color' => __( 'Подарочная карта (нижний блок): цвет заголовка', 'mp-custom-checkout' ),
			'step_4.payment_block.card_styles.gift_bar_text_color' => __( 'Подарочная карта (нижний блок): цвет описания', 'mp-custom-checkout' ),
			'step_4.payment_block.card_styles.gift_bar_input_bg' => __( 'Подарочная карта (нижний блок): фон поля ввода', 'mp-custom-checkout' ),
			'step_4.payment_block.card_styles.gift_bar_input_border' => __( 'Подарочная карта (нижний блок): рамка поля ввода', 'mp-custom-checkout' ),
			'step_4.payment_block.card_styles.gift_bar_input_text' => __( 'Подарочная карта (нижний блок): цвет текста поля ввода', 'mp-custom-checkout' ),
			'step_4.payment_block.card_styles.gift_bar_button_bg' => __( 'Подарочная карта (нижний блок): фон кнопки', 'mp-custom-checkout' ),
			'step_4.payment_block.card_styles.gift_bar_button_text' => __( 'Подарочная карта (нижний блок): цвет текста кнопки', 'mp-custom-checkout' ),
			'step_4.coupon_block.styles.summary_glow_color' => __( 'Промокод: цвет свечения блока', 'mp-custom-checkout' ),
			'step_4.coupon_block.styles.summary_bg' => __( 'Промокод: фон блока (градиент/цвет)', 'mp-custom-checkout' ),
			'step_4.coupon_block.styles.summary_border' => __( 'Промокод: цвет рамки блока', 'mp-custom-checkout' ),
			'step_4.coupon_block.styles.title_color' => __( 'Промокод: цвет заголовка', 'mp-custom-checkout' ),
			'step_4.coupon_block.styles.text_color' => __( 'Промокод: цвет текста/подписей', 'mp-custom-checkout' ),
			'step_4.coupon_block.styles.input_bg' => __( 'Промокод: фон поля ввода', 'mp-custom-checkout' ),
			'step_4.coupon_block.styles.input_border' => __( 'Промокод: рамка поля ввода', 'mp-custom-checkout' ),
			'step_4.coupon_block.styles.input_text' => __( 'Промокод: цвет текста поля ввода', 'mp-custom-checkout' ),
			'step_4.coupon_block.styles.button_bg' => __( 'Промокод: фон кнопки «Применить»', 'mp-custom-checkout' ),
			'step_4.coupon_block.styles.button_border' => __( 'Промокод: рамка кнопки «Применить»', 'mp-custom-checkout' ),
			'step_4.coupon_block.styles.button_text' => __( 'Промокод: цвет текста кнопки «Применить»', 'mp-custom-checkout' ),
			'step_4.coupon_block.styles.button_bg_hover' => __( 'Промокод: фон кнопки при наведении', 'mp-custom-checkout' ),
			'step_4.coupon_block.styles.button_border_hover' => __( 'Промокод: рамка кнопки при наведении', 'mp-custom-checkout' ),
			'delivery.pricing_mode' => __( 'Режим цен доставки', 'mp-custom-checkout' ),
			'delivery.wc_integration.respect_chosen_shipping_methods' => __( 'WC: сохранять выбранные методы доставки в сессии', 'mp-custom-checkout' ),
			'styles.progress_step_index.pending_bg'     => __( 'Шаги (кружок): фон — ещё не пройден', 'mp-custom-checkout' ),
			'styles.progress_step_index.pending_digit'  => __( 'Шаги (кружок): цвет цифры — ещё не пройден', 'mp-custom-checkout' ),
			'styles.progress_step_index.active_bg'      => __( 'Шаги (кружок): фон — текущий шаг', 'mp-custom-checkout' ),
			'styles.progress_step_index.active_digit'   => __( 'Шаги (кружок): цвет цифры — текущий шаг', 'mp-custom-checkout' ),
			'styles.progress_step_index.complete_bg'    => __( 'Шаги (кружок): фон — пройден', 'mp-custom-checkout' ),
			'styles.progress_step_index.complete_digit' => __( 'Шаги (кружок): цвет цифры — пройден', 'mp-custom-checkout' ),
			'styles.progress_step_index.pending_border'  => __( 'Шаги (кружок): цвет рамки — ещё не пройден', 'mp-custom-checkout' ),
			'styles.progress_step_index.active_border'   => __( 'Шаги (кружок): цвет рамки — текущий шаг', 'mp-custom-checkout' ),
			'styles.progress_step_index.complete_border' => __( 'Шаги (кружок): цвет рамки — пройден', 'mp-custom-checkout' ),
			'styles.progress_step_index.border_width'    => __( 'Шаги (кружок): толщина рамки (все состояния)', 'mp-custom-checkout' ),
		);
		if ( isset( $map[ $path ] ) ) {
			return (string) $map[ $path ];
		}
		return str_replace( '_', ' ', $leaf );
	}

	/**
	 * Локализация заголовков групп/fieldset в дереве настроек.
	 */
	private static function localized_group_label_for_path( string $path, string $fallback_key ): string {
		$map = array(
			'general.checkout_layout'                    => __( 'Макет страницы checkout', 'mp-custom-checkout' ),
			'styles.progress_step_index'                 => __( 'Кружки номеров шагов (вертикальный таймлайн)', 'mp-custom-checkout' ),
			'step_1.address_form_styles'                 => __( 'Стили формы адреса и доставки (шаг 1)', 'mp-custom-checkout' ),
			'step_1.step_panel_screen_styles'            => __( 'Рамка экрана шага (.mp-cc-step-panel.mp-cc-step-screen)', 'mp-custom-checkout' ),
			'step_4.contact_block'                       => __( 'Контактные данные (шаг 4)', 'mp-custom-checkout' ),
			'step_4.contact_block.layout'                => __( 'Сетка полей контактов', 'mp-custom-checkout' ),
			'step_4.contact_block.field_state_styles'    => __( 'Стили состояний полей контактов', 'mp-custom-checkout' ),
			'step_4.contact_block.labels'                => __( 'Подписи полей контактов', 'mp-custom-checkout' ),
			'step_4.contact_block.placeholders'          => __( 'Плейсхолдеры полей контактов', 'mp-custom-checkout' ),
			'step_4.contact_block.hints'                 => __( 'Подсказки полей контактов', 'mp-custom-checkout' ),
			'step_4.contact_block.validation_messages'   => __( 'Сообщения валидации контактов', 'mp-custom-checkout' ),
			'step_4.contact_block.validation_constraints'=> __( 'Ограничения валидации контактов', 'mp-custom-checkout' ),
			'step_4.contact_block.ajax_messages'         => __( 'AJAX-сообщения контактов', 'mp-custom-checkout' ),
			'step_4.address_block'                       => __( 'Адрес доставки (шаг 4)', 'mp-custom-checkout' ),
			'step_4.address_block.labels'                => __( 'Подписи полей адреса', 'mp-custom-checkout' ),
			'step_4.address_block.subfields_visible'     => __( 'Видимость полей адреса', 'mp-custom-checkout' ),
			'step_4.address_block.subfields_order'       => __( 'Порядок полей адреса', 'mp-custom-checkout' ),
			'step_4.recipient_styles'                    => __( 'Стили шага 2: Получатель', 'mp-custom-checkout' ),
			'step_4.recipient_step_panel_styles'         => __( 'Рамка и тень панели шага «Получатель» (data-step-panel=recipient)', 'mp-custom-checkout' ),
			'step_4.payment_block.card_styles'           => __( 'Стили карточек оплаты', 'mp-custom-checkout' ),
		);
		if ( isset( $map[ $path ] ) ) {
			return (string) $map[ $path ];
		}
		return str_replace( '_', ' ', $fallback_key );
	}

	/**
	 * @return array<int, string>
	 */
	private static function build_filters_for_path( string $path ): array {
		$filters = array( 'logic' );
		$p = strtolower( $path );
		if ( false !== strpos( $p, 'label' ) || false !== strpos( $p, 'title' ) || false !== strpos( $p, 'placeholder' ) || false !== strpos( $p, 'hint' ) || false !== strpos( $p, 'copy' ) || false !== strpos( $p, 'message' ) ) {
			$filters[] = 'content';
		}
		if ( false !== strpos( $p, 'field_' ) || false !== strpos( $p, 'address' ) || false !== strpos( $p, 'phone' ) || false !== strpos( $p, 'email' ) ) {
			$filters[] = 'fields';
		}
		if ( false !== strpos( $p, 'style' ) || false !== strpos( $p, 'token' ) || false !== strpos( $p, 'theme' ) ) {
			$filters[] = 'styles';
		}
		if ( false !== strpos( $p, 'error' ) || false !== strpos( $p, 'required' ) || false !== strpos( $p, 'validation' ) ) {
			$filters[] = 'validation';
		}
		if ( false !== strpos( $p, 'mobile' ) || false !== strpos( $p, 'tablet' ) || false !== strpos( $p, 'responsive' ) ) {
			$filters[] = 'mobile';
		}
		if ( false !== strpos( $p, 'motion.' ) || false !== strpos( $p, 'durations_ms' ) || false !== strpos( $p, 'ease_profile' ) ) {
			$filters[] = 'styles';
		}
		return array_values( array_unique( $filters ) );
	}

	private static function scenario_scope_for_path( string $path ): string {
		$p = strtolower( $path );
		if ( false !== strpos( $p, 'pickup' ) ) {
			return 'pickup-only';
		}
		if ( false !== strpos( $p, 'delivery' ) || false !== strpos( $p, 'address' ) ) {
			return 'delivery-only';
		}
		return 'all';
	}

	private static function scenario_scope_label( string $scope ): string {
		switch ( $scope ) {
			case 'pickup-only':
				return __( 'только самовывоз', 'mp-custom-checkout' );
			case 'delivery-only':
				return __( 'только доставка', 'mp-custom-checkout' );
			default:
				return __( 'все сценарии', 'mp-custom-checkout' );
		}
	}

	private static function tooltip_text_for_path( string $path ): string {
		$p = strtolower( $path );
		if ( false !== strpos( $p, 'checkout_layout.vertical_padding' ) ) {
			return __( 'Одинаковый отступ сверху и снизу у всего блока #mp-cc-checkout (например 75px или 4rem). Только безопасные единицы: px, rem, em, vw, %.', 'mp-custom-checkout' );
		}
		if ( false !== strpos( $p, 'step_1.address_form_styles.form_border_width' ) || false !== strpos( $p, 'step_1.address_form_styles.row_divider_width' ) ) {
			return __( 'CSS-размер: например 1px или 0 чтобы убрать линию. Допустимы px, rem.', 'mp-custom-checkout' );
		}
		if ( false !== strpos( $p, 'divider_after_city_width' ) || false !== strpos( $p, 'divider_after_method_width' ) ) {
			return __( 'Переопределяет толщину линии только для указанной границы. Пусто — как у «Толщина линий между строками». 0 — без линии.', 'mp-custom-checkout' );
		}
		if ( false !== strpos( $p, 'step_1.step_panel_screen_styles.border_width' ) ) {
			return __( 'Рамка вокруг панели текущего шага (v2). 0 или 0px — без рамки.', 'mp-custom-checkout' );
		}
		if ( false !== strpos( $p, 'step_1.step_panel_screen_styles.border_color' ) ) {
			return __( 'Необязательно: цвет в формате #rrggbb. Пустое поле — цвет границы из темы checkout.', 'mp-custom-checkout' );
		}
		if ( false !== strpos( $p, 'step_1.step_panel_screen_styles.box_shadow' ) ) {
			return __( 'CSS для box-shadow панели шага. Пусто — тень из токена темы (--mp-cc-shadow-card). none — без тени.', 'mp-custom-checkout' );
		}
		if ( false !== strpos( $p, 'step_4.recipient_step_panel_styles.border_width' ) || false !== strpos( $p, 'step_4.recipient_step_panel_styles.border_color' ) ) {
			return __( 'Только для шага с контактами (recipient). Пусто — те же значения, что на шаге 1 в «Рамка экрана шага». 0 — без рамки.', 'mp-custom-checkout' );
		}
		if ( false !== strpos( $p, 'step_4.recipient_step_panel_styles.box_shadow' ) ) {
			return __( 'Переопределяет тень только у панели recipient. Пусто — как у шага 1; none — без тени.', 'mp-custom-checkout' );
		}
		if ( false !== strpos( $p, 'payment_block.card_styles.selection_glow_color' ) ) {
			return __( 'HEX цвет (например #c4a574): ореол за логотипом, фокус и подсветка выбранной карточки в сетке two-up. Дублирует смысл «glow» до клика; после клика см. bank_card_visual.glow_color.', 'mp-custom-checkout' );
		}
		if ( false !== strpos( $p, 'bank_card_visual.glow_color' ) ) {
			return __( 'Цвет drop-shadow вокруг области логотипа после подтверждения выбора (если включён bank card visual).', 'mp-custom-checkout' );
		}
		if ( false !== strpos( $p, 'route_slug' ) ) {
			return __( 'Изменяет URL маршрутов checkout/success. Требует проверки rewrite-правил.', 'mp-custom-checkout' );
		}
		if ( false !== strpos( $p, 'step_order' ) ) {
			return __( 'Порядок шагов влияет на навигацию и валидацию. Меняйте с осторожностью.', 'mp-custom-checkout' );
		}
		if ( false !== strpos( $p, 'checkout_testing_mode' ) ) {
			return __( 'Тестовый режим оплаты может обходить реальный платёжный процесс.', 'mp-custom-checkout' );
		}
		if ( false !== strpos( $p, 'field_order' ) ) {
			return __( 'Определяет визуальный порядок полей в шаге.', 'mp-custom-checkout' );
		}
		if ( false !== strpos( $p, 'step_4.payment_block.two_up_show_card_description' ) ) {
			return __( 'Только для раскладки с двумя крупными карточками (ЮKassa / Robokassa). Снимите галочку, чтобы убрать серый текст описания под названием способа.', 'mp-custom-checkout' );
		}
		if ( false !== strpos( $p, 'step_4.payment_block.two_up_show_perk_tags' ) ) {
			return __( 'Только для двухкарточной раскладки. Снимите галочку, чтобы убрать блок с тегами («Без комиссии» и т. п.) и линию над ним.', 'mp-custom-checkout' );
		}
		if ( false !== strpos( $p, 'step_4.payment_block.two_up_minimal_idle_chrome' ) ) {
			return __( 'Только для двух крупных карточек (ЮKassa + Robokassa). Включите: у не выбранной карточки скрыты серая рамка и декоративный кружок; после выбора активная карточка выглядит как сейчас (фиолетовая рамка и индикатор). Снимите галочку, чтобы снова показывать рамку и кружок всегда.', 'mp-custom-checkout' );
		}
		if ( false !== strpos( $p, 'payment_block.card_styles.two_up_card_min_height' ) ) {
			return __( 'CSS min-height для карточки ЮKassa/Robokassa в двухколоночной раскладке (например 18rem). Пусто — встроенное значение по умолчанию (25rem).', 'mp-custom-checkout' );
		}
		if ( false !== strpos( $p, 'payment_block.card_styles.two_up_shell_min_height' ) ) {
			return __( 'CSS min-height верхней области с логотипом в two-up. Пусто — по умолчанию 8.8rem. Уменьшите вместе с «Высота логотипа», если карточка слишком высокая.', 'mp-custom-checkout' );
		}
		if ( false !== strpos( $p, 'payment_block' ) && false !== strpos( $p, 'card_surface' ) ) {
			return __( 'Режим карточек: classic — радио-список WooCommerce; visual — карточки и поля шлюза под сеткой; in_card — поля шлюза внутри выбранной карточки (HTML из payment_fields(), без самодельных PAN).', 'mp-custom-checkout' );
		}
		if ( false !== strpos( $p, 'payment_block' ) && false !== strpos( $p, 'auto_classic_on_empty_gateway_fields' ) ) {
			return __( 'Если для выбранного шлюза не удалось получить разметку полей, автоматически показать классический список способов оплаты.', 'mp-custom-checkout' );
		}
		if ( false !== strpos( $p, 'payment_block' ) && false !== strpos( $p, 'decorative_card_fields' ) ) {
			return __( 'Устаревший флаг: декоративный PAN в checkout не используется; ввод только через шлюз WooCommerce.', 'mp-custom-checkout' );
		}
		if ( false !== strpos( $p, 'motion.ease_profile' ) ) {
			return __( 'Пресет кривых easing: snappy, balanced, smooth или custom (тогда используются строки ease.standard / ease.emphasized).', 'mp-custom-checkout' );
		}
		if ( false !== strpos( $p, 'motion.mobile.use_desktop_durations' ) ) {
			return __( 'Если включено, на узких экранах используются те же длительности, что и для desktop.', 'mp-custom-checkout' );
		}
		if ( false !== strpos( $p, 'motion.mobile.durations_ms' ) ) {
			return __( 'Длительности для viewport ≤767px (мс). Игнорируются, если включено «как desktop».', 'mp-custom-checkout' );
		}
		if ( false !== strpos( $p, 'motion.durations_ms' ) ) {
			return __( 'Длительности анимаций в миллисекундах (0–4000). Сохраняются с clamp на сервере.', 'mp-custom-checkout' );
		}
		if ( false !== strpos( $p, 'motion.throttle' ) ) {
			return __( 'Ограничение частоты второстепенных анимаций на слабых устройствах / при лавине событий.', 'mp-custom-checkout' );
		}
		if ( false !== strpos( $p, 'shipping_catalog.methods' ) && false !== strpos( $p, 'tariffs' ) && false !== strpos( $p, '.price' ) ) {
			return __( 'Цена тарифа в каталоге (руб., целое). При режиме цен «woocommerce» и заполненном WC rate ID у тарифа сумма на витрине подменяется на расчёт WooCommerce (СДЭК/зоны и т.д.); это поле тогда резерв/подсказка и для подстраховки, если ставка WC не найдена.', 'mp-custom-checkout' );
		}
		if ( false !== strpos( $p, 'shipping_catalog.methods' ) && false !== strpos( $p, '.price' ) ) {
			return __( 'Цена метода без тарифов (руб., целое). Нужна в режиме «catalog» и как запасная, если в режиме «woocommerce» не удалось сопоставить WC rate. Если везде только тарифы СДЭК через WC — держите «woocommerce», задайте wc_rate_id у тарифов и не опирайтесь на это число.', 'mp-custom-checkout' );
		}
		if ( false !== strpos( $p, 'shipping_catalog.methods' ) && false !== strpos( $p, '.eta' ) ) {
			return __( 'Срок доставки (произвольный текст). Например: «2 дней», «в течение дня», пусто — не показывать.', 'mp-custom-checkout' );
		}
		if ( false !== strpos( $p, 'shipping_catalog.methods' ) && false !== strpos( $p, 'wc_rate_id' ) && false === strpos( $p, '.tariffs.' ) ) {
			return __( 'Идентификатор ставки WooCommerce для метода без тарифов (как в нативном checkout: method_id:instance_id, например flat_rate:12). Нужен для режима цен «woocommerce» и синхронизации выбранного способа с сессией WC. Поле дублируется в блоке «Каталог доставки» вверху вкладки.', 'mp-custom-checkout' );
		}
		if ( false !== strpos( $p, 'shipping_catalog.methods' ) && false !== strpos( $p, 'tariffs' ) && false !== strpos( $p, 'wc_rate_id' ) ) {
			return __( 'Идентификатор ставки WooCommerce (как в нативном checkout: shipping_method:instance). При режиме цен «woocommerce» цена тарифа в каталоге подменяется на расчёт WC по адресу. Пусто — остаётся цена из каталога.', 'mp-custom-checkout' );
		}
		if ( false !== strpos( $p, 'shipping_catalog.methods' ) && false !== strpos( $p, '.title' ) ) {
			return __( 'Название метода/тарифа, которое увидит покупатель на шаге «Адрес и доставка».', 'mp-custom-checkout' );
		}
		if ( false !== strpos( $p, 'shipping_catalog.methods' ) && false !== strpos( $p, '.active' ) ) {
			return __( 'Включить метод/тариф. Выключенные в checkout не показываются.', 'mp-custom-checkout' );
		}
		if ( false !== strpos( $p, 'shipping_catalog.methods' ) && false !== strpos( $p, 'visibility_scenarios' ) ) {
			return __( 'Сценарии, в которых метод доступен: pickup, krasnoyarsk_delivery, other_city_delivery.', 'mp-custom-checkout' );
		}
		if ( false !== strpos( $p, 'delivery.pricing_mode' ) ) {
			return __( 'catalog — цены из каталога модуля и shipping_price в сессии. woocommerce — адрес синхронизируется с WC customer, пересчёт доставки как в нативном checkout (СДЭК и зоны); суммы в списке методов подставляются из WC, если у тарифа задан WC rate ID в каталоге. hybrid — зарезервировано.', 'mp-custom-checkout' );
		}
		if ( false !== strpos( $p, 'delivery.wc_integration.respect_chosen_shipping_methods' ) ) {
			return __( 'Если в сессии WooCommerce уже выбран rate (method_id:instance_id), WC старается не сбрасывать его при пересчёте, пока он доступен. Для диагностики см. логи плагина при расхождении выбранного метода и списка rate’ов.', 'mp-custom-checkout' );
		}
		if ( false !== strpos( $p, 'styles.progress_step_index.border_width' ) ) {
			return __( 'Толщина рамки кружка: например 2px, 1px, 0.15rem. Допустимы px, rem, em, vw, % или число без единицы (тогда px).', 'mp-custom-checkout' );
		}
		if ( false !== strpos( $p, 'styles.progress_step_index.' ) ) {
			return __( 'Цвет в формате #rrggbb. Для рамок — цвет обводки кружка в каждом состоянии; для фона и цифры — как раньше.', 'mp-custom-checkout' );
		}
		if ( false !== strpos( $p, 'pickup.points' ) && false !== strpos( $p, 'address' ) ) {
			return __( 'Адрес пункта самовывоза одной строкой. Отображается в карточке метода «Самовывоз».', 'mp-custom-checkout' );
		}
		if ( false !== strpos( $p, 'step_1.labels.address_form' ) ) {
			return __( 'Тексты полей формы «Адрес и доставка» на checkout (шаг 1): подписи строк, placeholder города, кнопка «другой», подпись к тарифам, строка адреса офиса.', 'mp-custom-checkout' );
		}
		if ( false !== strpos( $p, 'phone_country_codes' ) ) {
			return __( 'Список стран для выбора кода телефона на шаге «Получатель»: dial, ISO, national_digits (сколько цифр без кода страны), label (подпись в списке, обычно код ISO: RU, KZ, …). Флаги на сайте — эмодзи по ISO.', 'mp-custom-checkout' );
		}
		return '';
	}

	private static function is_risky_path( string $path ): bool {
		$p = strtolower( $path );
		return false !== strpos( $p, 'route_slug' )
			|| false !== strpos( $p, 'step_order' )
			|| false !== strpos( $p, 'step_definitions' )
			|| false !== strpos( $p, 'checkout_testing_mode' );
	}

	/**
	 * Поля настроек, которые сохраняем (значение пишется в hidden), но не показываем в UI админки.
	 *
	 * Сейчас сюда попадают `delivery.shipping_catalog.methods.{X}.price` для всех методов кроме
	 * `pickup` и `krasnoyarsk_delivery` (у них цена реально берётся из админки), а также
	 * `delivery.shipping_catalog.methods.{X}.tariffs.{Y}.price` — у тарифов цена всегда приходит
	 * из WC rates через overlay, а это поле — просто источник «нулевой» подписи в UI checkout.
	 */
	private static function is_admin_field_hidden( string $path ): bool {
		$p = (string) $path;
		if ( false === strpos( $p, 'delivery.shipping_catalog.methods.' ) ) {
			return false;
		}
		$is_price_leaf = ( '.price' === substr( $p, -6 ) );
		if ( ! $is_price_leaf ) {
			return false;
		}
		// Цены тарифов (path содержит .tariffs.) — всегда скрываем.
		if ( false !== strpos( $p, '.tariffs.' ) ) {
			return true;
		}
		// Цена самого метода: оставляем для pickup и krasnoyarsk_delivery, скрываем остальное.
		if ( preg_match( '/delivery\.shipping_catalog\.methods\.([a-z0-9_\-]+)\.price$/i', $p, $matches ) ) {
			$method_id = strtolower( (string) $matches[1] );
			if ( 'pickup' === $method_id || 'krasnoyarsk_delivery' === $method_id ) {
				return false;
			}
			return true;
		}
		return false;
	}

	private static function detect_group_type( string $key, string $path ): string {
		$haystack = strtolower( $key . ' ' . $path );
		if ( false !== strpos( $haystack, 'motion' ) || false !== strpos( $haystack, 'duration' ) || false !== strpos( $haystack, 'ease' ) ) {
			return 'styles';
		}
		if ( false !== strpos( $haystack, 'valid' ) || false !== strpos( $haystack, 'error' ) || false !== strpos( $haystack, 'required' ) ) {
			return 'validation';
		}
		if ( false !== strpos( $haystack, 'style' ) || false !== strpos( $haystack, 'color' ) || false !== strpos( $haystack, 'theme' ) || false !== strpos( $haystack, 'token' ) ) {
			return 'styles';
		}
		if ( false !== strpos( $haystack, 'responsive' ) || false !== strpos( $haystack, 'mobile' ) || false !== strpos( $haystack, 'tablet' ) || false !== strpos( $haystack, 'desktop' ) ) {
			return 'adaptive';
		}
		if ( false !== strpos( $haystack, 'label' ) || false !== strpos( $haystack, 'title' ) || false !== strpos( $haystack, 'intro' ) || false !== strpos( $haystack, 'copy' ) || false !== strpos( $haystack, 'hint' ) ) {
			return 'content';
		}
		return 'logic';
	}

	private static function group_type_label( string $type ): string {
		switch ( $type ) {
			case 'content':
				return __( 'контент', 'mp-custom-checkout' );
			case 'validation':
				return __( 'валидация', 'mp-custom-checkout' );
			case 'styles':
				return __( 'стили', 'mp-custom-checkout' );
			case 'adaptive':
				return __( 'адаптив', 'mp-custom-checkout' );
			default:
				return __( 'логика', 'mp-custom-checkout' );
		}
	}

	/**
	 * @param array<int|string, mixed> $value
	 */
	private static function is_list_array( array $value ): bool {
		if ( function_exists( 'array_is_list' ) ) {
			return array_is_list( $value );
		}
		$expected = 0;
		foreach ( array_keys( $value ) as $key ) {
			if ( $key !== $expected ) {
				return false;
			}
			$expected++;
		}
		return true;
	}
}

