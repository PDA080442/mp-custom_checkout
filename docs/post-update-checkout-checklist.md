# MP Custom Checkout — Post-Update Checklist

## 1) Service Health
- Open admin `MP Checkout -> Служебное` and verify `Checkout Health Checks` has no `FAIL`.
- Verify `checkout_testing_mode` is set to intended state for environment.

## 2) Logs Baseline
- Open admin `MP Checkout -> Логи`.
- Filter `channel=critical` and ensure there are no new unresolved critical entries.
- Export logs snapshot to JSON before production traffic.

## 3) Frontend Flow Smoke
- Run sandbox preview scenarios: `pickup_happy_path`, `delivery_with_coupon`, `payment_error_case`.
- Verify step switching and summary/progress are rendered correctly.

## 4) Fulfillment Scenarios
- Validate `pickup`, `krasnoyarsk_delivery`, `other_city_delivery`.
- Confirm date/conditions behavior and summary shipping visibility per scenario.

## 5) Discounts and Payment
- Check coupon apply/reject states.
- Check gift card apply/reject states.
- Check payment runtime states: `loading`, `success`, `error`.

## 6) Validation
- Trigger required field validation on step 4.
- Confirm validation messages and error styles are displayed.
- Verify validation events appear in checkout logs.

## 7) Success Screen
- Verify success page blocks: fulfillment, contact summary (masked), financial summary.
- Confirm values match checkout summary and order meta.

## 8) Final Sign-off
- Clear resolved logs if needed.
- Keep exported JSON log snapshot with release notes.
- Mark release as checkout-verified.
- Confirm baseline docs are актуальны:
  - `docs/v2-scope-baseline.md`
  - `docs/v2-conflict-matrix.md`
  - `docs/v2-delivery-governance.md`
  - `docs/v2-change-log.md`

