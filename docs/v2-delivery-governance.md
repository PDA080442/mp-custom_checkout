# V2 Delivery Governance (Must-Have Blocks, Streams, DoD, Stage Gates)

This document operationalizes `dp.md` items `949-956` and is mandatory for v2 execution control.

## 1) Release policy lock
- One-click rollback to v1 is **not** a mandatory acceptance criterion.
- Mandatory rollout control is `checkout_ui_v2` feature flag.
- Any production enablement must follow staged rollout with smoke gates.

## 2) Must-have final blocks (no scope cuts allowed)

The following blocks are release-critical and cannot be removed from final v2 scope:

1. **V2 step flow**
- Four-step composition:
  - Step 1: address + shipping
  - Step 2: recipient
  - Step 3: payment
  - Step 4: final confirmation

2. **Checkout runtime mode**
- `/checkout/` page mode is mandatory.

3. **Discount UX**
- Coupon and gift card both visible and functional.
- Configurable removal behavior for applied discount entities.

4. **Payment matrix**
- Required support:
  - bank card flow
  - Robokassa
  - YooKassa

5. **Summary and financial integrity**
- Real-time totals refresh.
- Correct subtotal/shipping/discount/total consistency across channels.

6. **Admin-first configurability**
- New v2 blocks must expose configuration via admin settings.

7. **Mobile and first-visit stability**
- Mobile-specific layout quality.
- First-visit hardening (pre-hydration safety, anti-cache, layering stability).

8. **QA operations**
- Dedicated QA stand and test dataset are mandatory.

## 3) Workstream split (business-priority streams)

## Stream A: Core v2
Scope:
- step flow, route mode, shipping step, recipient step, payment step, confirmation step
- discounts and summary logic
- gateway compatibility and submit flow

Exit criteria:
- all must-have functional paths pass smoke and E2E
- no critical `checkout` channel errors

## Stream B: Mobile epic
Scope:
- mobile layouts from `design/mobile/*`
- mobile-safe sticky/fixed elements
- touch usability and accessibility compliance

Exit criteria:
- mobile visual review pass
- no CTA overlap and no blocked input scenarios

## Stream C: Animation epic
Scope:
- motion system, stepper line transitions, interaction animations
- admin controls for animation parameters

Exit criteria:
- animation controls functional in admin
- reduced-motion and performance safeguards validated

## Stream D: V1 cleanup epic
Scope:
- staged legacy deprecation after v2 stabilization
- removal of obsolete v1-only contracts

Exit criteria:
- v2 stable in production
- cleanup plan approved separately (not pre-release blocker for v2 go-live)

## 4) Definition of Done (DoD) by stream

## A) Core v2 DoD (mapped to `cq.md` section 18)
- `cq 183-188`: four-step flow implemented and stable.
- `cq 189-192`: shipping logic and recalculation complete.
- `cq 198-205`: payment submit stability and anti-double-submit complete.
- `cq 208-210`: coupon/gift card behavior and summary integrity complete.
- `cq 225-228`: page mode, discount composition, confirmation behavior finalized.
- All acceptance checks:
  - functional tests pass,
  - no critical logs spike,
  - data consistency checks pass.

## B) Mobile epic DoD
- `cq 176`, `cq 212`: mobile UX aligned to approved design direction.
- mobile checkout remains fully operable across all steps.
- no blocker regressions for focus, tap targets, keyboard overlays.

## C) Animation epic DoD
- `cq 173`, `cq 211`, `cq 229`: animation behavior implemented and configurable from admin.
- state transitions visually stable (no jitter/jumps).
- reduced-motion fallback exists and is validated.

## D) V1 cleanup epic DoD
- legacy branches cataloged,
- removal candidates approved,
- cleanup completed without breaking v2 contracts.

## 5) QA stand and test data lock

Mandatory environment requirements:
- isolated QA stand with dedicated DB snapshot,
- representative catalog + variations,
- coupon/gift-card test data,
- shipping matrix test data,
- gateway sandbox credentials for all required providers.

Mandatory validation packs:
- stage smoke packs,
- cross-device visual checks,
- gateway matrix checks,
- first-visit stability checks,
- consistency checks (checkout -> order meta -> emails/admin).

## 6) Stage-gate reviews (hard approval points)

Gate 0 — **Baseline Gate**
- `v2-scope-baseline.md` and conflict matrix approved.
- `checkout_ui_v2` exists and defaults to `off`.

Gate 1 — **Core Functional Gate**
- step flow, shipping, recipient, payment basics are stable in QA.
- must-have paths pass first smoke cycle.

Gate 2 — **Discount + Payment Integrity Gate**
- coupon/gift-card + gateway matrix pass.
- submit flow and recovery pass.

Gate 3 — **Mobile + Motion Gate**
- mobile layouts approved.
- animation controls and reduced-motion validated.

Gate 4 — **Release Readiness Gate**
- full smoke pass (twice),
- no unresolved critical issues,
- rollout plan approved for controlled enablement.

## 7) Requirement change procedure (v2 change control)

Any requirement change after baseline lock must follow:
1. Create a new entry in `docs/v2-change-log.md`.
2. Mark impact domain:
   - core flow / payment / discounts / mobile / animation / admin / QA.
3. Assign severity:
   - blocker / major / minor.
4. Assess impacts:
   - architecture,
   - timeline,
   - testing scope,
   - rollback risk.
5. Approve by stream owner before implementation.
6. Update related docs (`tz`, `cq`, `dp`, rollout docs) in same change set.
7. Re-run impacted smoke packs before merge.

No undocumented requirement changes are allowed in v2 branch.
