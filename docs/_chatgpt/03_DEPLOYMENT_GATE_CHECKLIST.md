# 03_DEPLOYMENT_GATE_CHECKLIST.md
This is the “market deployable” gate for CRM Beast.

---

## Gate A — Correctness & Security
- [ ] All tenant routes under `/org/{organization:slug}`
- [ ] `ResolveTenant` sets Spatie team context correctly
- [ ] No cross-tenant leakage (DB + tests)
- [ ] RBAC enforced server-side for all modules (policies/authorize/abort_unless)
- [ ] Permission canonicalization complete (no mismatched permission strings in policies/controllers)

---

## Gate B — Product Stability
- [ ] No broken routes/pages (Reports exists; Role editor works)
- [ ] Save flows work (no unsaved-changes modal blocking saves)
- [ ] Task drawer loads task details with clear 403/404/500 messaging
- [ ] Imports/exports are authorized

---

## Gate C — CI-like Verification (local)
Run:
- [ ] `./vendor/bin/sail artisan test`
- [ ] `./vendor/bin/sail npm run build`
- [ ] `./vendor/bin/sail artisan migrate:status`

---

## Gate D — Production Readiness
- [ ] Production build artifacts present (`public/build/manifest.json`)
- [ ] `.env` is not committed; secrets managed properly
- [ ] Queues configured if broadcasting/notifications used
- [ ] Logs + backups + DB indexes verified
- [ ] Rate limiting configured (login already rate-limited)

---

## Launch Plan (lean enterprise)
- Launch with perfect modules (Clients/Projects/Tasks/Attendance/Announcements/Roles/Reports)
- Keep features minimal; polish UX + performance after stability gates pass