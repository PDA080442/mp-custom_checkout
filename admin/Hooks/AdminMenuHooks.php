<?php
/**
 * Меню и экран настроек плагина в админке WordPress.
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout\Admin\Hooks;

use MP\CustomCheckout\Admin\Config\AdminTabRegistry;
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
		return self::sanitize_by_shape( $incoming, $defaults );
	}

	/**
	 * @param array<string, mixed> $incoming
	 * @param array<string, mixed> $shape
	 * @return array<string, mixed>
	 */
	private static function sanitize_by_shape( array $incoming, array $shape ): array {
		$result = array();
		foreach ( $shape as $key => $default_value ) {
			$raw = array_key_exists( $key, $incoming ) ? $incoming[ $key ] : $default_value;
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
				$result[ $key ]  = self::sanitize_by_shape( $raw_array, $default_value );
				continue;
			}
			if ( is_bool( $default_value ) ) {
				$result[ $key ] = ! empty( $raw );
				continue;
			}
			if ( is_int( $default_value ) ) {
				$result[ $key ] = (int) $raw;
				continue;
			}
			if ( is_float( $default_value ) ) {
				$result[ $key ] = (float) $raw;
				continue;
			}
			$result[ $key ] = sanitize_textarea_field( (string) $raw );
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
		<div class="wrap mp-cc-admin-shell mp-cc-admin-shell--<?php echo esc_attr( $layout ); ?>">
			<h1><?php esc_html_e( 'MP Custom Checkout — Настройки', 'mp-custom-checkout' ); ?></h1>
			<p class="description">
				<?php esc_html_e( 'Единый экран управления сценариями checkout, текстами, валидацией и визуальным поведением шагов.', 'mp-custom-checkout' ); ?>
			</p>
			<?php if ( isset( $_GET['settings-updated'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Настройки успешно сохранены.', 'mp-custom-checkout' ); ?></p></div>
			<?php endif; ?>

			<div class="mp-cc-admin-shell__layout-toggle">
				<a class="button button-small<?php echo 'top' === $layout ? ' button-primary' : ''; ?>" href="<?php echo esc_url( add_query_arg( array( 'page' => self::PAGE_SLUG, 'tab' => $active_tab, 'nav_layout' => 'top' ), admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Верхняя навигация', 'mp-custom-checkout' ); ?></a>
				<a class="button button-small<?php echo 'side' === $layout ? ' button-primary' : ''; ?>" href="<?php echo esc_url( add_query_arg( array( 'page' => self::PAGE_SLUG, 'tab' => $active_tab, 'nav_layout' => 'side' ), admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Боковая навигация', 'mp-custom-checkout' ); ?></a>
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
					<form method="post" action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>" data-mp-cc-admin-settings-form="1">
						<?php settings_fields( self::OPTION_GROUP ); ?>
						<?php self::render_tab_fields( (string) $active_tab, $settings, $tabs ); ?>
						<p class="submit">
							<button type="submit" class="button button-primary"><?php esc_html_e( 'Сохранить настройки', 'mp-custom-checkout' ); ?></button>
						</p>
					</form>
				</div>
			</div>
		</div>
		<script>
			(function () {
				var form = document.querySelector('[data-mp-cc-admin-settings-form="1"]');
				if (!form) { return; }
				var isDirty = false;
				var onBeforeUnload = function (event) {
					if (!isDirty) { return; }
					event.preventDefault();
					event.returnValue = '';
				};
				form.addEventListener('change', function () { isDirty = true; });
				form.addEventListener('input', function () { isDirty = true; });
				form.addEventListener('submit', function () { isDirty = false; });
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
		$section_value = isset( $settings[ $tab_id ] ) ? $settings[ $tab_id ] : array();
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
		}
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
		echo '<label class="mp-cc-admin-shell__field">';
		echo '<span class="mp-cc-admin-shell__field-label">' . esc_html( ucfirst( $label ) ) . '</span>';
		if ( is_bool( $value ) ) {
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

