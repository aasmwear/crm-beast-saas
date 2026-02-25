# CRM Beast — Project Status

> **Cursor Operating Rules**
>
> - Every PR must update this file with: what changed, how to QA, and any new risks.
> - Every RBAC change must update [PERMISSIONS.md](PERMISSIONS.md).

---

## Module Status

| Module | Status | Notes |
|--------|--------|-------|
| Architecture | ✅ | Sail/Docker, Inertia, Multi-tenant Middleware |
| User Management | ✅ | Roles, Permissions, Team Scoping |
| Clients | ✅ | Module complete (500 fixed: Relation type-hint in load closure) |
| Projects | ✅ | Financials (Budget/Price) added |
| Tasks | ✅ | Kanban, List, Assignees, drawer (fixed: JSON + status-based errors) |
| Roles & Permissions UI | ✅ | Matrix layout, Quick Matrix + Advanced modes |
| Attendance | 🟡 | Basic flow; upgrade pending (minutes, status) |
| Audit Logs | 🟡 | ActivityLogger exists; hook to Client/Task pending |
| Reports | ✅ | Page + RBAC + export (primary_contact_email/phone) |
| Notifications | 🟡 | Works; no RBAC |
| Settings | 🟡 | Works; no RBAC |
| Billing | 🟡 | Works; no RBAC |
| HRM | 🟡 | Auth commented out |

---

## Known Blockers / Risks

1. **Permission canonicalization** — Pending: align `clients.update` ↔ `clients.edit`, `tasks.update` ↔ `tasks.edit`, etc. (see PERMISSIONS.md).
2. ~~**Reports page missing**~~ — Fixed: Reports/Index.vue created, RBAC (reports.view, reports.export), export uses primary_contact_*.
3. **Unprotected controllers** — No authorize(): NotificationCenterController, SettingsController, SubscriptionController (billing), ClientsImportController. HRMController has auth commented out.
4. **DB risks** — activities table has no organization_id; comments has no org_id; attendance.organization_id nullable. ~~ReportController export~~ — fixed: now uses primary_contact_email, primary_contact_phone. See docs/DB_SCHEMA.md.

---

## Last PR Notes

- **Reports page + RBAC + export alignment:** Reports/Index.vue created (dark/glass UI, quick cards for Clients/Projects/Tasks/Attendance/Invoices). Permissions reports.view, reports.export added; index requires reports.view, export requires reports.export. Export columns fixed to primary_contact_email, primary_contact_phone. See QA steps below.
- **Full module + DB audit:** docs/MODULE_AUDIT.md, docs/DB_SCHEMA.md. Next PRs: Permission canonicalization; Unprotected controllers.

---

## Reports QA Steps

1. **Reports page:** `/org/{org-slug}/reports` (e.g. `/org/acme/reports`)
   - Must be logged in with reports.view permission.
   - Page shows quick cards (Clients, Projects, Tasks, Attendance, Invoices) with counts.
   - Export section visible; Export CSV button requires reports.export.
2. **Export CSV:** `/org/{org-slug}/export/csv/clients`
   - Requires reports.export.
   - CSV columns: Company, Primary Contact Email, Primary Contact Phone.
   - Add `?include_deleted=1` to include soft-deleted clients.
- **Runtime blockers fix:** Clients show 500 fixed (Relation type-hint in load closure); Task drawer fixed (Accept: application/json, status-based error messages 403/404/500).
- **Repo Cleanup + Documentation Baseline:** Removed Zone.Identifier files, backups from git; added .gitignore patterns; created docs baseline (PROJECT_CONTEXT, PROJECT_STATUS, PERMISSIONS, DECISIONS, RUNBOOK_LOCAL).
- **Create Role UX:** Auto-slug from display name, "Customize key" override, toast "Role created. Now choose permissions and click Save."
- **Roles Save flow:** Fixed Leave modal blocking Save; POST visits no longer trigger unsaved-changes guard; dirty state resets on success.

---

## Roles & Permissions — Matrix UX

- **Page:** `/org/{org}/settings/roles`
- **Modes:** Quick Matrix (default), Advanced
- **Features:** Permission catalog, sticky bar, leave confirmation, team-scoped badges
- **QA:** See Manual QA Steps in root PROJECT_STATUS.md or run through: persist, save flow, create role, unsaved flow, mode switch, search.
