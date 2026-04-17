# Checkout Recovery Guide

## When to Use
Use this guide if checkout becomes unstable after deploy: step transitions fail, payment errors spike, or sessions become stale.

## Fast Recovery Flow
1. Check `MP Checkout -> Служебное` health summary.
2. Check `MP Checkout -> Логи` with `channel=critical`.
3. Identify dominant failure pattern (`payment`, `step_transition`, `stale_context`).
4. Apply temporary mitigation:
   - disable risky feature flags,
   - enable known stable flow,
   - keep log export active.

## Scenario-Based Actions
- **Step transition failures**
  - Verify `session_set_step` responses.
  - Confirm markers: `step_transition_failed`.
  - Run state sync and retest with smoke checks.
- **Payment submit failures**
  - Check gateway availability and error code frequency.
  - Confirm retry path works and user data remains.
- **Stale session loops**
  - Confirm session abandon + resync behavior.
  - If persistent, rollback custom route.

## Exit Criteria
- Health summary has no `FAIL`.
- Smoke checks pass end-to-end.
- No new unresolved critical logs in latest run.

