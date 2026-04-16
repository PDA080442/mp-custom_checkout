# Restructure Baseline Checklist

Freeze point: `dp.md` task `12.2` completed (`622-623`).

Do not break during migration:
- Plugin entrypoint: `mp-custom-checkout.php`
- Hook names: `mp_custom_checkout_*`
- Critical hook priorities and order:
  - `plugins_loaded`: 0 / 15 / 25
  - `woocommerce_init`: 20 / 30
  - `template_redirect`: 1 / 2 / 4 / 5 / 6

Smoke checklist after each migration phase:
- Checkout route opens and renders.
- Step navigation works and persists.
- Contact + payment gateway selection sync works.
- Coupon and gift card apply flows work.
- Order submit writes order meta and email/admin output.
- Success route opens correctly.
- Admin settings page previews render.
- Admin order list CSS enhancements still apply.
