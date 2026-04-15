<?php
/**
 * Шаблон экрана успеха после оплаты (без ссылки «назад» на checkout).
 *
 * @package MP_Custom_Checkout
 * @var array<string, mixed> $mp_cc_success_presenter Данные для вывода.
 * @var \WC_Order              $mp_cc_success_order    Заказ.
 */

defined( 'ABSPATH' ) || exit;

$mp_cc_success_presenter = isset( $mp_cc_success_presenter ) && is_array( $mp_cc_success_presenter )
	? $mp_cc_success_presenter
	: array();

$mp_cc_success_order = isset( $mp_cc_success_order ) && $mp_cc_success_order instanceof \WC_Order
	? $mp_cc_success_order
	: null;

$labels = isset( $mp_cc_success_presenter['labels'] ) && is_array( $mp_cc_success_presenter['labels'] )
	? $mp_cc_success_presenter['labels']
	: array();

$primary_url   = isset( $mp_cc_success_presenter['primary_cta_url'] ) ? (string) $mp_cc_success_presenter['primary_cta_url'] : '';
$secondary_url = isset( $mp_cc_success_presenter['secondary_cta_url'] ) ? (string) $mp_cc_success_presenter['secondary_cta_url'] : '';

if ( '' === $primary_url ) {
	$primary_url = \MP\CustomCheckout\Routing\CheckoutSuccessPresenter::get_primary_cta_url();
}

$secondary_label = isset( $labels['cta_secondary_label'] ) ? trim( (string) $labels['cta_secondary_label'] ) : '';
$show_secondary  = '' !== $secondary_label && '' !== $secondary_url;

get_header();
?>

<main id="mp-cc-success" class="mp-cc-success" role="main">
	<div id="mp-cc-success-container" class="mp-cc-success__inner" role="region" aria-label="<?php echo esc_attr__( 'Checkout success screen', 'mp-custom-checkout' ); ?>">
		<header class="mp-cc-success__header">
			<h1 class="mp-cc-success__title"><?php echo esc_html( isset( $labels['title'] ) ? $labels['title'] : '' ); ?></h1>
			<?php if ( ! empty( $labels['message'] ) ) : ?>
				<p class="mp-cc-success__message"><?php echo esc_html( (string) $labels['message'] ); ?></p>
			<?php endif; ?>
		</header>

		<?php if ( $mp_cc_success_order ) : ?>
			<section class="mp-cc-success__summary" aria-labelledby="mp-cc-success-summary-heading">
				<h2 id="mp-cc-success-summary-heading" class="mp-cc-success__summary-title">
					<?php echo esc_html( isset( $labels['order_summary_title'] ) ? $labels['order_summary_title'] : __( 'Ваш заказ', 'mp-custom-checkout' ) ); ?>
				</h2>
				<dl class="mp-cc-success__dl">
					<div class="mp-cc-success__row">
						<dt><?php echo esc_html( isset( $labels['order_number'] ) ? $labels['order_number'] : '' ); ?></dt>
						<dd><?php echo esc_html( isset( $mp_cc_success_presenter['order_number'] ) ? (string) $mp_cc_success_presenter['order_number'] : '' ); ?></dd>
					</div>
					<div class="mp-cc-success__row">
						<dt><?php echo esc_html( isset( $labels['status'] ) ? $labels['status'] : '' ); ?></dt>
						<dd><?php echo esc_html( isset( $mp_cc_success_presenter['status_label'] ) ? (string) $mp_cc_success_presenter['status_label'] : '' ); ?></dd>
					</div>
					<div class="mp-cc-success__row">
						<dt><?php echo esc_html( isset( $labels['payment'] ) ? $labels['payment'] : '' ); ?></dt>
						<dd><?php echo esc_html( isset( $mp_cc_success_presenter['payment_method'] ) ? (string) $mp_cc_success_presenter['payment_method'] : '—' ); ?></dd>
					</div>
					<div class="mp-cc-success__row">
						<dt><?php echo esc_html( isset( $labels['total'] ) ? $labels['total'] : '' ); ?></dt>
						<dd><?php echo wp_kses_post( isset( $mp_cc_success_presenter['total_formatted'] ) ? (string) $mp_cc_success_presenter['total_formatted'] : '' ); ?></dd>
					</div>
					<?php if ( ! empty( $mp_cc_success_presenter['date_formatted'] ) ) : ?>
						<div class="mp-cc-success__row">
							<dt><?php echo esc_html( isset( $labels['date'] ) ? $labels['date'] : '' ); ?></dt>
							<dd><?php echo esc_html( (string) $mp_cc_success_presenter['date_formatted'] ); ?></dd>
						</div>
					<?php endif; ?>
				</dl>
			</section>
		<?php endif; ?>

		<nav class="mp-cc-success__actions" aria-label="<?php echo esc_attr__( 'Дальнейшие действия', 'mp-custom-checkout' ); ?>">
			<a class="mp-cc-success__btn mp-cc-success__btn--primary" href="<?php echo esc_url( $primary_url ); ?>">
				<?php echo esc_html( isset( $labels['cta_primary_label'] ) ? $labels['cta_primary_label'] : __( 'В магазин', 'mp-custom-checkout' ) ); ?>
			</a>
			<?php if ( $show_secondary ) : ?>
				<a class="mp-cc-success__btn mp-cc-success__btn--secondary" href="<?php echo esc_url( $secondary_url ); ?>">
					<?php echo esc_html( $secondary_label ); ?>
				</a>
			<?php endif; ?>
		</nav>

		<p class="mp-cc-success__hint">
			<?php esc_html_e( 'Чтобы не отправить оплату повторно, не используйте кнопку «Назад» в браузере после оплаты.', 'mp-custom-checkout' ); ?>
		</p>
	</div>
</main>

<?php
get_footer();
