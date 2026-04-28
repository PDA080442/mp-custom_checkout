# V2 Conflict Matrix Against Legacy Plan

This matrix resolves conflicts between legacy planning assumptions and the approved v2 scope baseline.

## Source of truth
- `tz.md` section 30 (v2 overrides)
- `cq.md` section 18 (approved checklist answers)

## Resolved conflicts

1. **Step structure**
- Legacy assumption: step composition centered around older `cart/date/conditions/contact`.
- V2 decision: four-screen flow is mandatory:
  - `Шаг 1`: `Адрес и способ доставки`
  - `Шаг 2`: `Получатель`
  - `Шаг 3`: `Способ оплаты`
  - `Шаг 4`: `Подтверждение`
- Resolution: v2 flow wins.

2. **Checkout container mode**
- Legacy assumption: modal-first UX remains acceptable.
- V2 decision: checkout works as page `/checkout/`.
- Resolution: page mode wins; modal assumptions are non-default.

3. **Discount UI composition**
- Legacy assumption: one discount surface (coupon-like block) may be enough.
- V2 decision: both coupon and gift card are simultaneously present in UI.
- Resolution: dual discount UX is mandatory.

4. **Discount removal behavior**
- Legacy assumption: removal UX can remain disabled.
- V2 decision: discount removal (coupon/gift card) is optional and admin-configurable.
- Resolution: configurable removal wins.

5. **Payment integrations**
- Legacy assumption: generic gateway compatibility without hard list.
- V2 decision: required integrations are:
  - bank card flow
  - Robokassa
  - YooKassa
- Resolution: required gateway set is mandatory in QA/release.

6. **Configurability policy**
- Legacy assumption: most settings configurable, but not strict across all surfaces.
- V2 decision: "almost everything configurable from admin" is top-level NFR.
- Resolution: admin-first configurability is mandatory for new v2 blocks.

7. **Rollout policy**
- Legacy assumption: rollback-centric release narrative.
- V2 decision: rollout is feature-flag driven with `checkout_ui_v2`; one-click v1 rollback is not mandatory acceptance criteria.
- Resolution: staged rollout wins.

## Implementation anchors
- `core/Settings/DefaultFeatureFlagsRegistry.php`: includes `checkout_ui_v2`.
- `core/Settings/FeatureFlagResolver.php`: exposes `checkout_ui_v2` to frontend.
- `docs/checkout-rollout-flags-strategy.md`: rollout sequence updated for v2.

## Verification checklist
- [ ] `checkout_ui_v2` exists in defaults and is `false` by default.
- [ ] frontend payload includes `checkout_ui_v2`.
- [ ] rollout strategy includes a dedicated v2 enable stage.
- [ ] release checklists reference v2 baseline + conflict matrix.
