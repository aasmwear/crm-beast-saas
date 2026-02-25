# 00_CRM_BEAST_MASTER_CONTEXT.md
Last updated: 2026-02-26 (Asia/Karachi)

This file is the single source of truth for continuing CRM Beast (“The Beast”) in a new chat without losing context.

---

## Project Identity
- Name: CRM Beast (“The Beast”)
- Type: Multi-tenant SaaS CRM for digital marketing agencies
- Tenancy URL: `/org/{organization:slug}/...`
- Canonical workspace folder: `crm-beast-saas`

---

## Stack
- Backend: Laravel 12, PHP 8.2+
- DB: PostgreSQL (Sail)
- Frontend: Inertia.js 2 + Vue 3 (`<script setup>` + TypeScript)
- Styling: Tailwind
- Auth: Breeze (Inertia)
- RBAC: Spatie Laravel-Permission with Teams
- Billing: Cashier (Stripe)

Ports:
- APP: 8080
- Vite: 5199 (do not use dev server; production build only)

---

## Non-Negotiables (Architecture Rules)
### Production Build Only
- DO NOT use `npm run dev`
- Use `npm run build` → outputs `public/build/manifest.json`
- Laravel assets via `@vite(...)`

### Multi-Tenancy
- All tenant routes are under `/org/{organization:slug}/...`
- `ResolveTenant`:
  - resolves org from route param
  - sets Spatie team context: `team_id = organization_id`
  - binds `scoped.organization` into container
- Every org-scoped query must filter by `organization_id`
- No cross-tenant leakage (DB + code + tests)

### RBAC
- Spatie Teams enabled (`team_id = organization_id`)
- User model uses `HasRoles`
- Canonical permission verbs:
  - `*.view`, `*.create`, `*.update`, `*.delete`, `*.manage`
  - specials: `*.import`, `*.export`, `attendance.approve`, `tasks.review`, etc.
- Legacy permissions like `*.edit` may exist but must map to canonical.

---

## Current Branch / Git State (known good)
- Branch: `rescue-mission`
- Working tree: clean (at time of snapshot)
- Latest commits (high-level):
  - b04bdbe Docs baseline + repo hygiene
  - ba43b6e / 1deb675 archive legacy docs (two commits; acceptable)
  - 6a452ed Roles & permissions matrix UI + Create Role UX
  - 90d4593 Canonical permissions + reconcile command
  - 049216a Reports page + RBAC + export fields
  - 55945b6 RBAC enforcement for notifications/settings/billing/hrm/import (+ tests)
  - b4b4e21 DB hardening tenant isolation (activities/comments/attendance/notifications) (+ tests)

---

## Module Status Snapshot
DONE (core stable):
- Clients
- Projects
- Tasks
- Attendance
- Announcements
- Departments
- Users + Roles
- Invoices
- Reports (page + RBAC + export fixed)

PARTIAL (exists, improved, but future polish possible):
- Notifications (now tenant-scoped via column + RBAC enforced; UI can be polished)
- Settings (RBAC enforced; possible UX polish)
- Billing (RBAC enforced; full Stripe readiness depends on environment config)
- Activity (DB tenant-safe now; UX + “meaningful logging coverage” polish in progress)

---

## Test/Build Health Snapshot
- `./vendor/bin/sail artisan test` → passing at last snapshot
- `./vendor/bin/sail npm run build` → passing at last snapshot

---

## Key Delivered Features (Recent)
- Roles & Permissions editor: “Quick Matrix + Advanced mode”, save guard fixed, Create Role UX fixed (auto-slug key).
- Permission canonicalization: canonical `*.update` added; legacy `*.edit` kept; reconciliation command exists.
- Reports: Index page created, RBAC enforced, export fields corrected.
- RBAC coverage: notifications/settings/billing/hrm/import enforced with tests.
- DB tenant isolation hardening: organization_id added/backfilled/indexed for activities/comments/notifications; attendance org_id hardened.

---

## Current Work In Progress
- Activity module “Enterprise polish” PR is currently running in Cursor:
  - ensure meaningful logging coverage across modules
  - ensure Activity UI/filters/links are correct
  - ensure RBAC `activity.view` enforced consistently
  - add tests for activity RBAC + emission

---

## Operating Rule for Future Work
Every Cursor prompt MUST:
1) Search the repo first to confirm what exists.
2) Improve gaps only; never duplicate controllers/pages/routes.
3) Keep PR scope small and testable.
4) Update:
   - `docs/PROJECT_STATUS.md`
   - `docs/PERMISSIONS.md` (if RBAC touched)