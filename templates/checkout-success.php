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

			<?php
			$fulfillment = isset( $mp_cc_success_presenter['fulfillment'] ) && is_array( $mp_cc_success_presenter['fulfillment'] )
				? $mp_cc_success_presenter['fulfillment']
				: array();
			$contact_summary = isset( $mp_cc_success_presenter['contact_summary'] ) && is_array( $mp_cc_success_presenter['contact_summary'] )
				? $mp_cc_success_presenter['contact_summary']
				: array();
			$financial_summary = isset( $mp_cc_success_presenter['financial_summary'] ) && is_array( $mp_cc_success_presenter['financial_summary'] )
				? $mp_cc_success_presenter['financial_summary']
				: array();
			?>

			<?php if ( ! empty( array_filter( $fulfillment ) ) ) : ?>
				<section class="mp-cc-success__summary" aria-labelledby="mp-cc-success-fulfillment-heading">
					<h2 id="mp-cc-success-fulfillment-heading" class="mp-cc-success__summary-title">
						<?php echo esc_html( isset( $labels['fulfillment_title'] ) ? $labels['fulfillment_title'] : __( 'Получение', 'mp-custom-checkout' ) ); ?>
					</h2>
					<dl class="mp-cc-success__dl">
						<?php if ( ! empty( $fulfillment['scenario_label'] ) ) : ?>
							<div class="mp-cc-success__row">
								<dt><?php echo esc_html( isset( $labels['fulfillment_type'] ) ? $labels['fulfillment_type'] : __( 'Способ получения', 'mp-custom-checkout' ) ); ?></dt>
								<dd><?php echo esc_html( (string) $fulfillment['scenario_label'] ); ?></dd>
							</div>
						<?php endif; ?>
						<?php if ( ! empty( $fulfillment['date_label'] ) ) : ?>
							<div class="mp-cc-success__row">
								<dt><?php echo esc_html( isset( $labels['fulfillment_date'] ) ? $labels['fulfillment_date'] : __( 'Дата получения/доставки', 'mp-custom-checkout' ) ); ?></dt>
								<dd><?php echo esc_html( (string) $fulfillment['date_label'] ); ?></dd>
							</div>
						<?php endif; ?>
						<?php if ( ! empty( $fulfillment['pickup_point'] ) ) : ?>
							<div class="mp-cc-success__row">
								<dt><?php echo esc_html( isset( $labels['pickup_point'] ) ? $labels['pickup_point'] : __( 'Точка самовывоза', 'mp-custom-checkout' ) ); ?></dt>
								<dd><?php echo esc_html( (string) $fulfillment['pickup_point'] ); ?></dd>
							</div>
						<?php endif; ?>
					</dl>
				</section>
			<?php endif; ?>

			<?php if ( ! empty( array_filter( $contact_summary ) ) ) : ?>
				<section class="mp-cc-success__summary" aria-labelledby="mp-cc-success-contact-heading">
					<h2 id="mp-cc-success-contact-heading" class="mp-cc-success__summary-title">
						<?php echo esc_html( isset( $labels['contact_title'] ) ? $labels['contact_title'] : __( 'Контактные данные', 'mp-custom-checkout' ) ); ?>
					</h2>
					<dl class="mp-cc-success__dl">
						<?php if ( ! empty( $contact_summary['recipient'] ) ) : ?>
							<div class="mp-cc-success__row">
								<dt><?php echo esc_html( isset( $labels['recipient'] ) ? $labels['recipient'] : __( 'Получатель', 'mp-custom-checkout' ) ); ?></dt>
								<dd><?php echo esc_html( (string) $contact_summary['recipient'] ); ?></dd>
							</div>
						<?php endif; ?>
						<?php if ( ! empty( $contact_summary['email'] ) ) : ?>
							<div class="mp-cc-success__row">
								<dt><?php echo esc_html( isset( $labels['email_masked'] ) ? $labels['email_masked'] : __( 'Email', 'mp-custom-checkout' ) ); ?></dt>
								<dd><?php echo esc_html( (string) $contact_summary['email'] ); ?></dd>
							</div>
						<?php endif; ?>
						<?php if ( ! empty( $contact_summary['phone'] ) ) : ?>
							<div class="mp-cc-success__row">
								<dt><?php echo esc_html( isset( $labels['phone_masked'] ) ? $labels['phone_masked'] : __( 'Телефон', 'mp-custom-checkout' ) ); ?></dt>
								<dd><?php echo esc_html( (string) $contact_summary['phone'] ); ?></dd>
							</div>
						<?php endif; ?>
					</dl>
				</section>
			<?php endif; ?>

			<?php if ( ! empty( array_filter( $financial_summary ) ) ) : ?>
				<section class="mp-cc-success__summary" aria-labelledby="mp-cc-success-financial-heading">
					<h2 id="mp-cc-success-financial-heading" class="mp-cc-success__summary-title">
						<?php echo esc_html( isset( $labels['financial_title'] ) ? $labels['financial_title'] : __( 'Финансовый итог', 'mp-custom-checkout' ) ); ?>
					</h2>
					<dl class="mp-cc-success__dl">
						<?php if ( ! empty( $financial_summary['subtotal'] ) ) : ?>
							<div class="mp-cc-success__row">
								<dt><?php echo esc_html( isset( $labels['subtotal'] ) ? $labels['subtotal'] : __( 'Подытог', 'mp-custom-checkout' ) ); ?></dt>
								<dd><?php echo esc_html( (string) $financial_summary['subtotal'] ); ?></dd>
							</div>
						<?php endif; ?>
						<?php if ( ! empty( $financial_summary['shipping'] ) ) : ?>
							<div class="mp-cc-success__row">
								<dt><?php echo esc_html( isset( $labels['shipping'] ) ? $labels['shipping'] : __( 'Доставка', 'mp-custom-checkout' ) ); ?></dt>
								<dd><?php echo esc_html( (string) $financial_summary['shipping'] ); ?></dd>
							</div>
						<?php endif; ?>
						<?php if ( ! empty( $financial_summary['discount'] ) ) : ?>
							<div class="mp-cc-success__row">
								<dt><?php echo esc_html( isset( $labels['discount'] ) ? $labels['discount'] : __( 'Скидка', 'mp-custom-checkout' ) ); ?></dt>
								<dd><?php echo esc_html( (string) $financial_summary['discount'] ); ?></dd>
							</div>
						<?php endif; ?>
						<?php if ( ! empty( $financial_summary['tax'] ) ) : ?>
							<div class="mp-cc-success__row">
								<dt><?php echo esc_html( isset( $labels['tax'] ) ? $labels['tax'] : __( 'Налог', 'mp-custom-checkout' ) ); ?></dt>
								<dd><?php echo esc_html( (string) $financial_summary['tax'] ); ?></dd>
							</div>
						<?php endif; ?>
						<?php if ( ! empty( $financial_summary['total'] ) ) : ?>
							<div class="mp-cc-success__row">
								<dt><?php echo esc_html( isset( $labels['total'] ) ? $labels['total'] : __( 'Итого', 'mp-custom-checkout' ) ); ?></dt>
								<dd><strong><?php echo esc_html( (string) $financial_summary['total'] ); ?></strong></dd>
							</div>
						<?php endif; ?>
					</dl>
				</section>
			<?php endif; ?>
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
