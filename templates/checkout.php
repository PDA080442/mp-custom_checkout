<?php
/**
 * Шаблон страницы кастомного checkout.
 *
 * @package MP_Custom_Checkout
 * @var array<string, mixed> $mp_cc_checkout_context Контекст маршрута.
 */

defined( 'ABSPATH' ) || exit;

// Контроллер передаёт массив в include; если по какой-то причине переменная не в области видимости — берём из $GLOBALS (иначе data-mp-cc-context и initialContext пустые).
if ( ! isset( $mp_cc_checkout_context ) || ! is_array( $mp_cc_checkout_context ) ) {
	$mp_cc_checkout_context = isset( $GLOBALS['mp_cc_checkout_context'] ) && is_array( $GLOBALS['mp_cc_checkout_context'] )
		? $GLOBALS['mp_cc_checkout_context']
		: array();
}
$GLOBALS['mp_cc_checkout_context'] = $mp_cc_checkout_context;

get_header();
?>

<main id="mp-cc-checkout" class="mp-cc-checkout" data-mp-cc-context="<?php echo esc_attr( wp_json_encode( $mp_cc_checkout_context ) ); ?>">
	<div class="mp-cc-checkout__inner" role="region" aria-label="<?php echo esc_attr__( 'Checkout shell', 'mp-custom-checkout' ); ?>">
		<header class="mp-cc-v2-shell-head" role="banner" aria-label="<?php echo esc_attr__( 'Checkout header', 'mp-custom-checkout' ); ?>">
			<div class="mp-cc-v2-shell-head__title-wrap">
				<h1 class="mp-cc-v2-shell-head__title"><?php esc_html_e( 'Оформление заказа', 'mp-custom-checkout' ); ?></h1>
			</div>
			<button type="button" class="mp-cc-v2-shell-head__exit" id="mp-cc-exit-checkout" aria-label="<?php echo esc_attr__( 'Закрыть оформление', 'mp-custom-checkout' ); ?>" data-exit-checkout="1">×</button>
		</header>
		<section id="mp-cc-parcel-header" class="mp-cc-region mp-cc-region--parcel" aria-live="polite" aria-label="<?php echo esc_attr__( 'Order parcel header', 'mp-custom-checkout' ); ?>"></section>
		<section id="mp-cc-notifications" class="mp-cc-region mp-cc-region--notifications" role="status" aria-live="polite" aria-atomic="true"></section>
		<div class="mp-cc-layout" role="group" aria-label="<?php echo esc_attr__( 'Checkout layout', 'mp-custom-checkout' ); ?>">
			<div class="mp-cc-layout__main">
				<nav id="mp-cc-progress-container" class="mp-cc-region mp-cc-region--progress" aria-label="<?php echo esc_attr__( 'Checkout progress', 'mp-custom-checkout' ); ?>"></nav>
				<section id="mp-cc-step-content-container" class="mp-cc-region mp-cc-region--content" role="region" aria-label="<?php echo esc_attr__( 'Checkout step content', 'mp-custom-checkout' ); ?>">
					<?php
					/**
					 * Точка вывода контента checkout (SPA/шаги подключаются позже).
					 */
					do_action( 'mp_custom_checkout_render_content', $mp_cc_checkout_context );
					?>
				</section>
				<nav id="mp-cc-navigation-actions" class="mp-cc-region mp-cc-region--actions" aria-label="<?php echo esc_attr__( 'Checkout actions', 'mp-custom-checkout' ); ?>"></nav>
			</div>
			<aside id="mp-cc-summary-sidebar" class="mp-cc-region mp-cc-region--summary" role="complementary" aria-label="<?php echo esc_attr__( 'Order summary', 'mp-custom-checkout' ); ?>"></aside>
		</div>
		<section id="mp-cc-success-container" class="mp-cc-region mp-cc-region--success" role="region" aria-live="polite" aria-label="<?php echo esc_attr__( 'Checkout success screen', 'mp-custom-checkout' ); ?>" hidden></section>
	</div>
	<script type="application/json" id="mp-cc-bootstrap-context"><?php echo wp_json_encode( $mp_cc_checkout_context, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE ); ?></script>
</main>

<?php
get_footer();
