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
use MP\CustomCheckout\Settings\OptionKeys;
use MP\CustomCheckout\Settings\SafeSettingsResolver;

defined( 'ABSPATH' ) || exit;

final class AdminMenuHooks {
	public const PAGE_SLUG      = 'mp-custom-checkout';
	public const OPTION_GROUP   = 'mp_custom_checkout_settings_group';
	public const NAV_LAYOUT_KEY = 'mp_cc_admin_nav_layout';

	public static function register(): void {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_init', array( __CLASS__, 'handle_logs_actions' ) );
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
		$sanitized = self::sanitize_by_shape( $incoming, $defaults, '' );
		return self::normalize_delivery_settings( $sanitized );
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
			if ( isset( $method['tariffs'] ) && is_array( $method['tariffs'] ) ) {
				foreach ( $method['tariffs'] as $tariff_id => $tariff ) {
					if ( ! is_array( $tariff ) ) {
						unset( $method['tariffs'][ $tariff_id ] );
						continue;
					}
					$tariff['price'] = isset( $tariff['price'] ) ? max( 0, (int) $tariff['price'] ) : 0;
					$tariff['eta']   = isset( $tariff['eta'] ) ? sanitize_text_field( (string) $tariff['eta'] ) : '';
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
		$settings[ OptionKeys::SECTION_DELIVERY ] = $delivery;
		return $settings;
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
		$section_value = isset( $settings[ $tab_id ] ) ? $settings[ $tab_id ] : array();
		$section_value = is_array( $section_value ) ? $section_value : array();
		$defaults_tree   = SafeSettingsResolver::get_defaults_tree();
		$defaults_for_tab = isset( $defaults_tree[ $tab_id ] ) && is_array( $defaults_tree[ $tab_id ] ) ? $defaults_tree[ $tab_id ] : array();
		if ( ! empty( $defaults_for_tab ) ) {
			$section_value = array_replace_recursive( $defaults_for_tab, $section_value );
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
		self::render_field_group( OptionKeys::MAIN . '[' . $tab_id . ']', $section_value, $tab_id );
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
				echo '<summary><span>' . esc_html( str_replace( '_', ' ', $key_str ) ) . '</span><em class="mp-cc-admin-shell__type-badge mp-cc-admin-shell__type-badge--' . esc_attr( $group_type ) . '">' . esc_html( self::group_type_label( $group_type ) ) . '</em></summary>';
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
		$label = str_replace( '_', ' ', basename( str_replace( '.', '/', $path ) ) );
		$filters = self::build_filters_for_path( $path );
		$risky = self::is_risky_path( $path );
		$scenario = self::scenario_scope_for_path( $path );
		echo '<label class="mp-cc-admin-shell__field' . ( $risky ? ' is-risky' : '' ) . '" data-setting-filters="' . esc_attr( implode( ',', $filters ) ) . '" data-setting-scenario="' . esc_attr( $scenario ) . '">';
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
		if ( false !== strpos( $p, 'payment_block' ) && false !== strpos( $p, 'card_surface' ) ) {
			return __( 'Режим карточек: classic — радио-список WooCommerce; visual — карточки и поля шлюза под сеткой; in_card — поля шлюза внутри выбранной карточки (HTML из payment_fields(), без самодельных PAN).', 'mp-custom-checkout' );
		}
		if ( false !== strpos( $p, 'payment_block' ) && false !== strpos( $p, 'auto_classic_on_empty_gateway_fields' ) ) {
			return __( 'Если для выбранного шлюза не удалось получить разметку полей, автоматически показать классический список способов оплаты.', 'mp-custom-checkout' );
		}
		if ( false !== strpos( $p, 'payment_block' ) && false !== strpos( $p, 'decorative_card_fields' ) ) {
			return __( 'Устаревший флаг: декоративный PAN в checkout не используется; ввод только через шлюз WooCommerce.', 'mp-custom-checkout' );
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

	private static function detect_group_type( string $key, string $path ): string {
		$haystack = strtolower( $key . ' ' . $path );
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

