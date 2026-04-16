# Checkout Log Reading Guide (for PM)

## Where to Open Logs
- Admin path: `MP Checkout -> Логи`.
- Start with filter `channel=critical`.

## How to Read One Entry
- Header: `[level] message` + source badge.
- Metadata row: timestamp, `event_type`, `channel`.
- `context` block: technical details (masked for sensitive fields).

## Recommended Triage Order
1. `critical` channel entries in current release window.
2. Repeating `payment_*` or `session_*` failures.
3. `ajax_error_log` spikes (may indicate frontend or network issue).
4. `validation_log` anomalies on key steps.

## Useful Filters
- `source`: frontend / backend / php.
- `event_type`: narrow to payment, step transitions, validation.
- `search`: order ID, scenario, or error code.

## Incident Escalation Rules
- Escalate immediately when:
  - payment submit fails repeatedly,
  - stale session recovery loops,
  - fatal/php critical entries appear in sequence.

## Daily Routine
- Review logs after deployments.
- Export JSON snapshot for release notes.
- Clear only resolved/noise logs, keep incident evidence intact.

