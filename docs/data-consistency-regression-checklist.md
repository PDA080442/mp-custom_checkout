# Data Consistency Regression Checklist

This checklist verifies that checkout data stays consistent across:
- frontend final review,
- order meta,
- admin order output,
- emails,
- success screen.

## 1) Baseline test matrix

Run all checks for these scenarios:
- `pickup`
- `krasnoyarsk_delivery`
- `other_city_delivery`

For each scenario, run at least:
- one order without discounts,
- one order with coupon only,
- one order with gift card only (if enabled),
- one order with coupon + gift card (if allowed by current setup),
- different payment methods available in checkout.

## 2) Source-of-truth keys

Verify order meta keys are present/absent as expected (`OrderMetaKeys`):
- Fulfillment: scenario id/label/payload, selected date/date label, pickup point fields.
- Conditions: confirmed flag/ts, summary.
- Contact extras: patronymic, birthdate, gender, phone country/meta.
- Structured address: country/region/city/line1/line2/postcode.
- Discounts: applied coupons, applied gift cards, coupon total, gift card total.

## 3) Frontend review vs order meta

On `contact_payment` final review, compare with order meta after place order:
- Fulfillment type and selected date.
- Pickup point (for pickup only).
- Structured address (delivery scenarios).
- Birthdate and gender (when filled).
- Discounts and totals summary.

Expected:
- pickup hides shipping address data in both frontend review and saved meta;
- delivery keeps structured address in both places.

## 4) Order meta vs admin output

In order admin page:
- Header/admin blocks show same fulfillment type/date as meta.
- Conditions block text equals stored conditions summary.
- Structured address reflects saved structured fields.
- Personal fields (birthdate/gender) displayed only when present.
- Discounts block equals saved applied coupons/gift card totals.

In order list:
- scenario/date/conditions columns and compact key meta match same order meta values.

## 5) Order meta vs email output

For customer and admin emails (HTML + plain):
- Fulfillment type/date present and equal meta.
- Conditions summary present and readable.
- Structured address aligns with saved fields.
- Discount lines align with saved coupon/gift card meta totals.
- No accidental sensitive overexposure (only masked contact data where intended).

## 6) Checkout summary totals vs final order totals

Compare:
- checkout final review totals (subtotal/discount/shipping/tax/total)
- success screen financial summary
- Woo order totals in admin

Expected:
- total values are identical after order creation.
- discount and shipping behavior matches scenario and applied promos.

## 7) Scenario consistency rules

`pickup`:
- pickup point present when selected,
- shipping address meta removed/empty,
- shipping line hidden where expected.

`krasnoyarsk_delivery` and `other_city_delivery`:
- no pickup point meta,
- structured address present,
- fulfillment/date and conditions consistent across all channels.

## 8) Regression sign-off

Before release:
- run this checklist against latest branch,
- capture at least one order ID per scenario as evidence,
- record mismatches with channel (`frontend/admin/email/success`) and key name,
- fix and re-run until all checks pass.

