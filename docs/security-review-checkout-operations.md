# MP Custom Checkout — Security Review (Operations)

## Scope
- Custom checkout AJAX endpoints: `mp_cc_checkout`, `mp_cc_set_checkout_entry`
- Admin service/log actions on `MP Checkout -> Логи`
- Checkout diagnostics and logging pipeline

## Controls Verified
- Nonce checks are enforced for custom AJAX endpoints (`mp_cc_checkout`, `mp_cc_checkout_entry`).
- Unknown/empty `sub_action` requests are rejected with `400`.
- Endpoint input is validated and sanitized (step IDs, scenarios, diagnostics arrays, client error payload).
- Session answers payload is shape-guarded (depth/items/type limits) before persistence.
- Dynamic admin output is escaped with `esc_*`/`esc_textarea` in logs UI.
- Admin log maintenance actions are restricted by:
  - capability `manage_options`,
  - HTTP method `POST`,
  - page/tab allowlist (`page=mp-custom-checkout`, `tab=logs`),
  - nonce check `mp_cc_logs_actions`.
- Sensitive fields in checkout logs are masked (email/phone/token/nonce/coupon/gift-card/code/address/name-like keys).

## Residual Risks
- Frontend error logging can still generate high volume under repeated runtime failures (operational noise risk).
- Option-based log storage is bounded but not indexed; large environments may need DB table-based storage for analytics.

## Recommendations
- Add short rate-limiter for `client_error_log` and `ajax_error_log` per session/IP.
- Add optional allowlist for `client_error_log.error_type`.
- Add scheduled task to purge old logs by retention policy (e.g., 30/60/90 days).

