# Post-Deploy Smoke Checks (Checkout)

## Scope
Run this list right after plugin update in production-like environment.

## Smoke Pack (10-15 minutes)
- Open checkout and verify step progress is visible and clickable.
- Move through all steps without console errors.
- Apply valid and invalid coupon.
- Apply valid and invalid gift card.
- Submit payment in happy path and verify success screen.
- Confirm order appears in WooCommerce admin with checkout meta.

## Operational Checks
- `MP Checkout -> Служебное`: no `FAIL` in health summary.
- `MP Checkout -> Логи`: no fresh unresolved `critical` logs.
- Confirm diagnostic markers `step_transition_ok` appear during transitions.

## Fail Criteria
- Payment cannot be submitted in happy path.
- Repeated step transition failures.
- Any new `critical` logs linked to current release window.

## Evidence
- Save one screenshot of each step.
- Export logs JSON and attach to release notes.

