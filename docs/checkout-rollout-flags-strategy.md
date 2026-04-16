# Checkout Feature-Flag Rollout Strategy

## Rollout Goals
- Release custom checkout safely with progressive exposure.
- Keep instant rollback path via feature flags.
- Capture diagnostics early with minimal customer impact.

## Flags and Stages
- `custom_checkout_route`
  - `off`: legacy route only
  - `on`: custom route enabled
- `multi_step_flow`
  - `off`: simplified flow fallback
  - `on`: full step flow
- `conditions_step`
  - `off`: skip conditions step
  - `on`: enforce conditions flow
- `discount_block_placement`
  - `off`: inline fallback
  - `on`: configured discount placement
- `checkout_testing_mode`
  - `on` only in QA/staging or controlled debug window
- `admin_live_preview`
  - keep `on` for content validation before rollout

## Suggested Rollout Sequence
1. Enable only `custom_checkout_route` for internal users / QA.
2. Enable `multi_step_flow` + `conditions_step` for small traffic slice.
3. Enable discount placement and advanced payment states.
4. Monitor logs (`critical` channel) + health summary at each stage.
5. Move to full traffic only after smoke checks pass twice.

## Guardrails
- Stop rollout immediately if:
  - critical logs spike,
  - checkout completion rate drops,
  - repeated payment gateway failures.
- Keep rollback owner and on-call contact defined before launch window.

