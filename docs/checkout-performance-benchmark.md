# MP Custom Checkout — Performance Benchmark (Before/After)

## What Was Optimized
- Reduced step transition network chatter by returning `flow + cart` from `session_set_step` and consuming it directly on frontend.
- Batched render updates by diffing `step/summary/progress/actions` HTML signatures before touching DOM.
- Added debounce for manual quantity input (`input` event) to avoid request bursts while typing.
- Reduced admin preview re-render churn by skipping DOM updates when preview signature is unchanged.
- Reduced layout/repaint pressure for sticky summary (`contain` desktop, `will-change` mobile).

## Measured Scenarios
- Step transition: `date -> conditions -> contact_payment`
- Cart quantity typing: rapid edits in quantity input (`123` typed quickly)
- Admin settings preview: rapid edits in multiple fields on one tab

## Before / After
- Step transition requests per navigation:
  - Before: `2` (`session_set_step` + `session_get_state`)
  - After: `1` (`session_set_step` only)
- Quantity input requests during rapid typing:
  - Before: up to `3` immediate updates for `123`
  - After: `1` debounced update after typing pause
- Frontend DOM writes per render cycle:
  - Before: full write of app + summary + progress + actions each render
  - After: only changed blocks are written
- Admin preview writes on noisy input:
  - Before: every rerender cycle always writes body/nav/progress
  - After: skipped entirely when computed preview signature is unchanged

## Benchmark Method
- Use browser DevTools Performance + Network panel.
- Capture 10 interactions per scenario before and after patch.
- Compare:
  - number of XHR requests,
  - main-thread scripting time,
  - layout/recalculate style events,
  - paint counts.

## Notes
- Results depend on product count, active gateways, and browser/device.
- This benchmark is intended as operational baseline for future regressions.

