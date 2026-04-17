<?php
/**
 * Transitional class aliases for phased project restructuring.
 *
 * @package MP_Custom_Checkout
 */

namespace MP\CustomCheckout\Compatibility;

defined( 'ABSPATH' ) || exit;

final class ClassAliasRegistry {

	/**
	 * Регистрирует ленивые алиасы: не вызывает class_exists() по всем классам сразу
	 * (это подгружало бы весь фронт/AJAX и могло давать фатал при ранней загрузке).
	 */
	public static function register(): void {
		$aliases = array(
			'MP\\CustomCheckout\\Hooks\\FrontendAssetsHooks'        => 'MP\\CustomCheckout\\Frontend\\Hooks\\FrontendAssetsHooks',
			'MP\\CustomCheckout\\Hooks\\CheckoutEntryFrontendHooks' => 'MP\\CustomCheckout\\Frontend\\Hooks\\CheckoutEntryFrontendHooks',
			'MP\\CustomCheckout\\Hooks\\CheckoutSuccessFrontendHooks' => 'MP\\CustomCheckout\\Frontend\\Hooks\\CheckoutSuccessFrontendHooks',
			'MP\\CustomCheckout\\Hooks\\CheckoutNoJsFallbackHooks'  => 'MP\\CustomCheckout\\Frontend\\Hooks\\CheckoutNoJsFallbackHooks',
			'MP\\CustomCheckout\\Hooks\\AdminAssetsHooks'           => 'MP\\CustomCheckout\\Admin\\Hooks\\AdminAssetsHooks',
			'MP\\CustomCheckout\\Hooks\\CheckoutAjaxHooks'          => 'MP\\CustomCheckout\\Checkout\\Hooks\\CheckoutAjaxHooks',
			'MP\\CustomCheckout\\Hooks\\CheckoutEntryAjaxHooks'     => 'MP\\CustomCheckout\\Checkout\\Hooks\\CheckoutEntryAjaxHooks',
			'MP\\CustomCheckout\\Hooks\\OrderMetaHooks'             => 'MP\\CustomCheckout\\Checkout\\Hooks\\OrderMetaHooks',
			'MP\\CustomCheckout\\Hooks\\EmailHooks'                 => 'MP\\CustomCheckout\\Checkout\\Hooks\\EmailHooks',
		);

		spl_autoload_register(
			static function ( $class ) use ( $aliases ) {
				if ( ! isset( $aliases[ $class ] ) ) {
					return;
				}
				$modern = $aliases[ $class ];
				if ( ! class_exists( $modern ) ) {
					return;
				}
				if ( ! class_exists( $class, false ) ) {
					class_alias( $modern, $class );
				}
			},
			true,
			true
		);
	}
}
