<?php
/**
 * Шаблон страницы кастомного checkout.
 *
 * @package MP_Custom_Checkout
 * @var array<string, mixed> $mp_cc_checkout_context Контекст маршрута.
 */

defined( 'ABSPATH' ) || exit;

$mp_cc_checkout_context = isset( $mp_cc_checkout_context ) && is_array( $mp_cc_checkout_context )
	? $mp_cc_checkout_context
	: array();

get_header();
?>

<main id="mp-cc-checkout" class="mp-cc-checkout" data-mp-cc-context="<?php echo esc_attr( wp_json_encode( $mp_cc_checkout_context ) ); ?>">
	<div class="mp-cc-checkout__inner">
		<?php
		/**
		 * Точка вывода контента checkout (SPA/шаги подключаются позже).
		 */
		do_action( 'mp_custom_checkout_render_content', $mp_cc_checkout_context );
		?>
	</div>
</main>

<?php
get_footer();
