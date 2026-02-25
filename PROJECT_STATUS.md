# 🦁 CRM Beast: Execution Ledger

> **Canonical status:** [docs/PROJECT_STATUS.md](docs/PROJECT_STATUS.md) — update that file for PRs.

**Target:** Master Specification v3.0

## 🟢 Completed & Verified
1.  **Architecture:** Sail/Docker, Inertia, Multi-tenant Middleware.
2.  **User Management:** Roles, Permissions, Team Scoping.
3.  **Clients Module:** Created with **Singular Ownership** (Fronter/Closer FKs).
4.  **Projects Module:** Financials (Budget/Price) added.
5.  **Tasks Module:** Kanban Board, List View, Assignees.
6.  **Roles & Permissions UI:** Matrix layout with Quick Matrix + Advanced modes (see below).

## 🟡 Immediate To-Do (The "Production Push")
1.  **Attendance Upgrade:** Implement the `minutes` calculation and `status` logic defined in Master Spec v3.0.
2.  **Task Workflow:** Build the "Submit for Review" -> "Approve/Reject" UI and Controller logic.
3.  **Audit Logs:** Hook up the `ActivityLogger` to Client Creation and Task Completion.

## 🔴 Blockers / Risks
- **None.** Codebase is currently stable and aligned with v3.0 Spec.

---

## Roles & Permissions — Matrix UX (Added 2025-02)

### Overview
The Roles & Permissions page (`/org/{org}/settings/roles`) now has a two-mode editor:

- **Quick Matrix (default):** Grid layout where rows = Modules (Clients, Projects, Tasks, etc.) and columns = Actions (View, Create, Update/Edit, Delete, Manage). Each module also shows "Special permissions" as pills (e.g. `tasks.review`, `attendance.approve`, `roles.assign`). Search filters modules. Expand/collapse per module. Counts show "X / Y catalog permissions" (recognized permissions only, not all DB rows).

- **Advanced:** Full accordion list of all DB permissions, with filters: All DB Permissions, Recognized Only, Orphan Only.

### Features
- Permission catalog (backend): Single source of truth for modules, actions, matrix mapping, specials, and aliases (`clients.edit` ↔ `clients.update`).
- Sticky bottom bar: "Unsaved changes" with Save + Discard when edits are pending.
- Leave confirmation: Modal when navigating away with unsaved changes; `beforeunload` for tab close/refresh.
- Team-scoped roles display "Team" badge; global roles display "Global" badge.

### Manual QA Steps
1. **Persist changes:** Pick a role → toggle matrix cells (Quick Matrix) or checkboxes (Advanced) → click Save → confirm toast "Permissions saved." → sticky bar disappears → refresh page → confirm permissions persisted.
2. **Save flow (no Leave modal):** Toggle any permission → click Save → confirm NO "Leave anyway?" modal appears → save completes → toast shows → dirty state resets.
3. **Create Role flow:** Click Create Role → enter name → Create → modal closes → toast "Role created." → new role auto-selected in left list → permissions shown (empty by default).
4. **Team scoping:** Ensure roles with `team_id = org.id` show "Team"; roles with `team_id = null` show "Global". Create a new role → confirm it is team-scoped.
5. **Unsaved flow:** Toggle permissions → confirm sticky bar appears → click Discard → confirm changes reverted. Toggle again → click a nav link (sidebar) → confirm "Leave anyway?" modal → click Stay → confirm still on page. Click link again → Leave anyway → confirm navigation proceeds.
6. **Mode switch:** Switch between Quick Matrix and Advanced → confirm same selection state in both.
7. **Search:** In Quick Matrix, type in search → confirm only matching modules show. In Advanced, type → confirm only matching permissions show.