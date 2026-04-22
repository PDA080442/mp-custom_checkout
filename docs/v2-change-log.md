# V2 Change Log

Track all requirement and scope changes after baseline lock.

## Baseline lock
- Baseline document: `docs/v2-scope-baseline.md`
- Governance document: `docs/v2-delivery-governance.md`
- Conflict matrix: `docs/v2-conflict-matrix.md`

---

## Entry template

- Change ID:
- Date:
- Author:
- Requested by:
- Summary:
- Related source update:
  - `cq.md`:
  - `tz.md`:
  - `dp.md`:
- Impact domain:
  - [ ] Core flow
  - [ ] Discounts
  - [ ] Payment
  - [ ] Mobile
  - [ ] Animation
  - [ ] Admin settings
  - [ ] QA / rollout
- Severity:
  - [ ] Blocker
  - [ ] Major
  - [ ] Minor
- Architectural impact:
- Delivery impact:
- Test impact:
- Risk notes:
- Decision:
  - [ ] Approved
  - [ ] Rejected
  - [ ] Deferred
- Approver:
- Required follow-up tasks:
- Verification status:

---

## Entries

### V2-0001
- Change ID: V2-0001
- Date: 2026-04-22
- Author: Codex
- Requested by: product owner
- Summary: Locked v2 governance package from `dp.md` items `949-956`.
- Related source update:
  - `cq.md`: section 18 (already approved)
  - `tz.md`: section 30 (already approved)
  - `dp.md`: items `949-956` execution
- Impact domain:
  - [x] Core flow
  - [x] Discounts
  - [x] Payment
  - [x] Mobile
  - [x] Animation
  - [x] Admin settings
  - [x] QA / rollout
- Severity:
  - [x] Major
  - [ ] Blocker
  - [ ] Minor
- Architectural impact: governance and rollout constraints formalized.
- Delivery impact: stream split + stage gates required.
- Test impact: QA stand and staged smoke packs mandated.
- Risk notes: undocumented scope changes now explicitly prohibited.
- Decision:
  - [x] Approved
  - [ ] Rejected
  - [ ] Deferred
- Approver: pending product owner confirmation
- Required follow-up tasks:
  - keep entries updated on every scope shift
  - sync with rollout and release checklist
- Verification status: baseline package created
