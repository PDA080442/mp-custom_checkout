# V2 Scope Baseline (Implementation Start Artifact)

This document locks the approved v2 implementation baseline before active coding.

## Baseline status
- Version: `v2-baseline-1`
- Scope owner: checkout redesign stream
- Effective source: approved checklist + v2 TЗ overrides

## Locked decisions

1. **Flow**
- Four-screen flow only:
  - Step 1: address + shipping method
  - Step 2: recipient data
  - Step 3: payment
  - Step 4: final confirmation

2. **Route mode**
- Checkout is page-based at `/checkout/`.

3. **Discount composition**
- Coupon and gift card are both visible and usable in v2 UI.

4. **Discount removal**
- Removal of applied discount entities is configurable in admin.

5. **Gateway obligations**
- Required support matrix:
  - bank card UI flow
  - Robokassa
  - YooKassa

6. **Configurability NFR**
- New v2 functionality must follow admin-first configurability.

7. **Rollout**
- Controlled via feature flags.
- `checkout_ui_v2` governs v2 UI activation.
- Default state is `off` until QA sign-off.

8. **Release policy**
- One-click rollback to v1 is not mandatory acceptance criteria.
- Staged rollout + smoke gates are mandatory.

## Non-goals for baseline phase
- Full UI redesign completion in this baseline commit.
- Payment card visual overhaul implementation in this baseline commit.
- Mobile redesign implementation in this baseline commit.

## Traceability
- `docs/v2-conflict-matrix.md` resolves legacy-vs-v2 conflicts.
- `docs/v2-delivery-governance.md` defines must-have blocks, streams, DoD, QA lock and stage gates.
- `docs/v2-change-log.md` is mandatory for post-baseline requirement changes.
- `docs/checkout-rollout-flags-strategy.md` defines rollout stages.
- `core/Settings/DefaultFeatureFlagsRegistry.php` defines `checkout_ui_v2`.
- `core/Settings/FeatureFlagResolver.php` exports `checkout_ui_v2` to frontend runtime.

## Acceptance checks (baseline-only)
- [ ] feature flag added and discoverable
- [ ] rollout doc updated
- [ ] conflict matrix present
- [ ] baseline document present
- [ ] governance doc present
- [ ] change-log process initialized
