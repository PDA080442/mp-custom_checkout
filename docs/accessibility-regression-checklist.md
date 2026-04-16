# MP Custom Checkout — Accessibility Regression Checklist

## Keyboard Navigation
- Navigate step progress buttons via `Tab` and arrow keys.
- Navigate scenario cards via `Tab` and arrow keys.
- Navigate calendar days via arrow keys, `Home`, `End`, `Enter`/`Space`.
- Confirm all actionable controls are reachable without mouse.

## Live Regions / Announcements
- Trigger validation errors and confirm assertive announcement.
- Trigger success/info notifications and confirm polite announcement.
- Confirm month title in calendar updates is announced (`aria-live`).

## Focus Management
- On step change, heading receives focus.
- On invalid form submit, focus moves to first invalid control.
- After recovery flows (stale session / sync failure), focus remains usable.

## Contrast and State Visibility
- Check error text and error badges in light/dark monitor conditions.
- Check disabled controls remain distinguishable from enabled controls.
- Check selected/active states (progress, calendar, payment cards) are visible.

## Custom Controls Accessibility
- Payment gateway radios: keyboard-selectable and label announced.
- Scenario and pickup point radio groups expose correct `aria-checked`.
- Calendar buttons expose `aria-label`, `aria-selected`, `aria-disabled`.

## Mobile / Touch Targets
- Verify minimum tap target size on key controls (qty, nav, calendar, remove).
- Check sticky summary controls are usable with thumb reach.

## Zoom 200%
- Browser zoom to 200% on desktop and mobile emulation.
- Confirm no critical overlap/cutoff for step content, nav, and summary.
- Ensure horizontal scrolling is not required for core checkout actions.

## Sign-off
- Run full flow: cart -> date -> conditions -> contact/payment -> success.
- Attach screenshots/videos for any a11y regression.
- Export logs and include in QA artifact if failures were observed.

