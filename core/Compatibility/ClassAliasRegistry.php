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
	 * Register backward-compatibility aliases between old and new namespaces.
	 */
	public static function register(): void {
		$aliases = array(
			'MP\\CustomCheckout\\Hooks\\FrontendAssetsHooks'       => 'MP\\CustomCheckout\\Frontend\\Hooks\\FrontendAssetsHooks',
			'MP\\CustomCheckout\\Hooks\\CheckoutEntryFrontendHooks' => 'MP\\CustomCheckout\\Frontend\\Hooks\\CheckoutEntryFrontendHooks',
			'MP\\CustomCheckout\\Hooks\\CheckoutSuccessFrontendHooks' => 'MP\\CustomCheckout\\Frontend\\Hooks\\CheckoutSuccessFrontendHooks',
			'MP\\CustomCheckout\\Hooks\\CheckoutNoJsFallbackHooks' => 'MP\\CustomCheckout\\Frontend\\Hooks\\CheckoutNoJsFallbackHooks',
			'MP\\CustomCheckout\\Hooks\\AdminAssetsHooks'          => 'MP\\CustomCheckout\\Admin\\Hooks\\AdminAssetsHooks',
			'MP\\CustomCheckout\\Hooks\\CheckoutAjaxHooks'         => 'MP\\CustomCheckout\\Checkout\\Hooks\\CheckoutAjaxHooks',
			'MP\\CustomCheckout\\Hooks\\CheckoutEntryAjaxHooks'    => 'MP\\CustomCheckout\\Checkout\\Hooks\\CheckoutEntryAjaxHooks',
			'MP\\CustomCheckout\\Hooks\\OrderMetaHooks'            => 'MP\\CustomCheckout\\Checkout\\Hooks\\OrderMetaHooks',
			'MP\\CustomCheckout\\Hooks\\EmailHooks'                => 'MP\\CustomCheckout\\Checkout\\Hooks\\EmailHooks',
			'MP\\CustomCheckout\\Routing\\CheckoutPermalinkCompatibility' => 'MP\\CustomCheckout\\Checkout\\Routing\\CheckoutPermalinkCompatibility',
			'MP\\CustomCheckout\\Routing\\CheckoutRouteController'  => 'MP\\CustomCheckout\\Checkout\\Routing\\CheckoutRouteController',
			'MP\\CustomCheckout\\Routing\\CheckoutSuccessController' => 'MP\\CustomCheckout\\Checkout\\Routing\\CheckoutSuccessController',
			'MP\\CustomCheckout\\Routing\\CheckoutSuccessOrderReceivedRedirect' => 'MP\\CustomCheckout\\Checkout\\Routing\\CheckoutSuccessOrderReceivedRedirect',
			'MP\\CustomCheckout\\Routing\\CheckoutSuccessRouteHooks' => 'MP\\CustomCheckout\\Checkout\\Routing\\CheckoutSuccessRouteHooks',
			'MP\\CustomCheckout\\Routing\\CheckoutEntryService'     => 'MP\\CustomCheckout\\Checkout\\Routing\\CheckoutEntryService',
			'MP\\CustomCheckout\\Routing\\CheckoutSessionService'   => 'MP\\CustomCheckout\\Checkout\\Routing\\CheckoutSessionService',
		);

		foreach ( $aliases as $legacy => $modern ) {
			if ( class_exists( $legacy, false ) || ! class_exists( $modern ) ) {
				continue;
			}
			class_alias( $modern, $legacy );
		}
	}
}
