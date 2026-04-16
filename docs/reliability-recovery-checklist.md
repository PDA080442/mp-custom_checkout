# MP Custom Checkout — Reliability Recovery Checklist

## 1. Critical Request Locks
- Try double-click on "Next" during step transition: only one transition request should execute.
- Try double-click on payment submit: second attempt must be blocked with in-progress message.

## 2. Failed Payment Recovery
- Force payment failure (`gateway_not_available` / generic `payment_submit_failed`).
- Verify:
  - contact/payment form data remains,
  - pre-payment confirm state remains available,
  - user can retry without full page reset.

## 3. Step AJAX Failure Recovery
- Simulate temporary AJAX failure during `session_set_step`.
- Verify UI recovers via sync and displays step sync error message.

## 4. Cart Desync Recovery
- Simulate mismatch between `items_count` and local item list.
- Verify automatic resync runs and cart snapshot is restored.

## 5. Stale Session / Invalid State
- Force stale context (`stale_context`).
- Verify recovery flow:
  - session abandon request,
  - state resync,
  - UI re-render without fatal lock.

## 6. Gateway Error Handling
- Simulate external gateway return with error response.
- Verify user sees graceful message and can select another gateway.

## 7. Partial Block Initialization Failure
- Simulate render exception for summary/progress/actions.
- Verify fallback UI blocks render and checkout remains operable.

## 8. QA Sign-off
- Run all scenarios in desktop + mobile.
- Check checkout logs for critical errors after each scenario.
- Attach exported logs JSON to QA report.

