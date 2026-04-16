# Checkout Rollback Plan

## Trigger Conditions
- Critical payment issues in production.
- Significant checkout conversion drop.
- Repeating `critical` channel errors tied to checkout flow.

## Immediate Rollback Actions (0-10 min)
1. Disable `custom_checkout_route`.
2. Disable `multi_step_flow` and `conditions_step`.
3. Keep logs export enabled for incident capture.
4. Announce incident status in release channel.

## Verification After Rollback (10-20 min)
- Open legacy checkout route and place a test order.
- Verify no new `critical` logs related to custom flow.
- Confirm customer support reports no new checkout failures.

## Data and Diagnostics
- Export logs JSON from `MP Checkout -> Логи`.
- Save screenshot of health summary from `MP Checkout -> Служебное`.
- Record failed order IDs and gateway responses.

## Recovery to Forward Path
- Keep custom checkout disabled until root cause is fixed.
- Re-test in staging with smoke checks.
- Re-enable flags progressively according to rollout strategy.

