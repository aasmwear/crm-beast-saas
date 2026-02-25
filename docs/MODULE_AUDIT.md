# CRM Beast — Module Audit Report

**Audit date:** 2025-02  
**Scope:** Full module + DB alignment (no feature implementation)

---

## A) Module Verification

### 1. Clients

| Item | Details |
|------|---------|
| **Routes** | GET `/clients` (index), `/clients/import`, `/clients/create`, `/clients/pipeline`, POST `/clients/{client}/pipeline`, POST `/clients`, GET `/clients/{client}`, GET `/clients/{client}/edit`, PUT `/clients/{client}`, DELETE `/clients/{client}`, contacts CRUD, portal-access, GET `/clients/export` |
| **Controllers** | ClientsInertiaController, ClientController, ClientContactController, ClientsImportController, ClientsPipelineController, PortalAccessController |
| **Models** | Client, ClientContact |
| **Policies** | ClientPolicy (viewAny, view, create, update, delete) — enforced |
| **Vue Pages** | Clients/Index, Show, Edit, Create, Import, Pipeline, QuickCreate |
| **Permissions in code** | clients.view, clients.create, clients.update, clients.delete, financials.view, contacts.manage |
| **Permissions in seeder** | clients.view, clients.edit, clients.create, clients.delete, clients.manage, clients.import, clients.export |
| **Gap** | Policy uses `clients.update`; seeder has `clients.edit`. ClientsImportController has **no authorize()**. |
| **Status** | **Done** (with gaps: permission alias, import auth) |

---

### 2. Projects

| Item | Details |
|------|---------|
| **Routes** | GET `/projects/board`, `/projects/calendar`, comments, files, messages, pipeline status, resource CRUD |
| **Controllers** | ProjectController, ProjectBoardController, ProjectCalendarController, CommentController, ProjectFileController, ProjectMessagesController |
| **Models** | Project, Comment, ProjectFile, ProjectMessage |
| **Policies** | ProjectPolicy (viewAny, view, create, update, delete, viewBudget) — enforced |
| **Vue Pages** | Projects/Index, Board, Calendar, List, Show, Create, Edit, Messages |
| **Permissions in code** | projects.view, projects.create, projects.update, projects.delete, financials.view |
| **Permissions in seeder** | projects.view, projects.edit, projects.create, projects.delete |
| **Gap** | Policy uses `projects.update`; seeder has `projects.edit`. |
| **Status** | **Done** |

---

### 3. Tasks

| Item | Details |
|------|---------|
| **Routes** | GET `/tasks/board`, `/tasks`, POST `/tasks`, GET/PUT/DELETE `/tasks/{task}`, POST submit, review |
| **Controllers** | TaskController, TaskBoardController |
| **Models** | Task |
| **Policies** | TaskPolicy (viewAny, view, create, update, delete, submit, review) — enforced |
| **Vue Pages** | Tasks/Index, Board |
| **Permissions in code** | tasks.view, tasks.create, tasks.update, tasks.delete, tasks.review |
| **Permissions in seeder** | tasks.view, tasks.edit, tasks.create, tasks.delete, tasks.review |
| **Gap** | Policy uses `tasks.update`; seeder has `tasks.edit`. |
| **Status** | **Done** |

---

### 4. Attendance

| Item | Details |
|------|---------|
| **Routes** | GET `/attendance`, POST clock-in, clock-out, approve, PATCH update |
| **Controllers** | AttendanceController |
| **Models** | Attendance |
| **Policies** | AttendancePolicy (viewAny, clockIn, clockOut, approve, update) — enforced |
| **Vue Pages** | Attendance/Index |
| **Permissions in code** | attendance.view, attendance.clock-in, attendance.approve, etc. |
| **Permissions in seeder** | attendance.view, attendance.view-own, clock-in, clock-out, approve, manage |
| **Feature gate** | `feature:attendance` middleware |
| **Status** | **Done** |

---

### 5. Announcements

| Item | Details |
|------|---------|
| **Routes** | GET/POST `/announcements`, GET/PUT/DELETE `/announcements/{id}` |
| **Controllers** | AnnouncementController |
| **Models** | Announcement |
| **Policies** | AnnouncementPolicy — enforced |
| **Vue Pages** | Announcements/Index |
| **Permissions in seeder** | announcements.view, create, update, delete, pin |
| **Status** | **Done** |

---

### 6. Notifications

| Item | Details |
|------|---------|
| **Routes** | GET `/notifications`, `/notifications/list`, POST read-all, `/{notification}/read` |
| **Controllers** | NotificationCenterController |
| **Storage** | Laravel default `notifications` table (DatabaseNotification). Morphs(notifiable). JSONB `data` with `organization_id` in payload. |
| **Tenant scoping** | Via `data->>'organization_id' = ?` in queries. Application-level; no FK. |
| **RBAC** | **None** — No `authorize()` in NotificationCenterController. Any authenticated org member can access. |
| **Vue Pages** | Notifications/Index |
| **Status** | **Partial** — Works but no permission check. |

---

### 7. Activity / Audit Log

| Item | Details |
|------|---------|
| **Routes** | GET `/activity` |
| **Controllers** | ActivityController |
| **Storage** | `audit_logs` table (organization_id, actor_id, action, entity, entity_id). Also `activities` table (no org_id — see DB risks). |
| **Policies** | Uses `TaskPolicy::viewAny` as gate (reuse). |
| **Vue Pages** | Activity/Index |
| **Status** | **Done** |

---

### 8. Settings

| Item | Details |
|------|---------|
| **Routes** | GET `/settings`, PUT/POST `/settings` |
| **Controllers** | SettingsController |
| **RBAC** | **None** — No authorize(). Any org member can edit org settings. |
| **Vue Pages** | Settings/Index |
| **Status** | **Partial** — No permission check. |

---

### 9. Billing

| Item | Details |
|------|---------|
| **Routes** | GET `/billing`, POST checkout, GET portal |
| **Controllers** | Admin\SubscriptionController |
| **RBAC** | **None** — No authorize(). |
| **Vue Pages** | Billing/Index, Manage, Subscribe |
| **Status** | **Partial** — No permission check. |

---

### 10. HRM / Team

| Item | Details |
|------|---------|
| **Routes** | GET/POST `/hrm`, PUT/DELETE `/hrm/{user}` |
| **Controllers** | HRMController |
| **RBAC** | **Commented out** — `// $this->authorize(...)` in index, store, destroy. |
| **Vue Pages** | HRM/Index |
| **Status** | **Partial** — Auth disabled. |

---

### 11. Departments

| Item | Details |
|------|---------|
| **Routes** | GET/POST `/departments`, PUT/DELETE `/departments/{department}` |
| **Controllers** | DepartmentController |
| **Policies** | DepartmentPolicy — enforced |
| **Vue Pages** | Departments/Index |
| **Status** | **Done** |

---

### 12. Users / Role Assignment

| Item | Details |
|------|---------|
| **Routes** | GET `/users`, PUT `/users/{user}` |
| **Controllers** | UserManagementController |
| **Policies** | UserPolicy — enforced |
| **Permissions** | users.manage, users.update (policy); seeder has users.edit |
| **Vue Pages** | Users/Index |
| **Status** | **Done** |

---

### 13. Roles & Permissions

| Item | Details |
|------|---------|
| **Routes** | GET `/settings/roles`, POST store, PUT update, POST `/roles-permissions/save` |
| **Controllers** | RolePermissionController |
| **RBAC** | `roles.manage` or super_admin |
| **Vue Pages** | Settings/Roles |
| **Status** | **Done** |

---

### 14. Invoices

| Item | Details |
|------|---------|
| **Routes** | Full CRUD, download, mark-sent, mark-paid |
| **Controllers** | InvoiceController |
| **Policies** | InvoicePolicy — enforced |
| **Vue Pages** | Invoices/Index, Create, Show |
| **Status** | **Done** |

---

### 15. Reports

| Item | Details |
|------|---------|
| **Routes** | GET `/reports`, GET `/export/csv/{entity}`, POST `/backup/snapshot` |
| **Controllers** | ReportController |
| **RBAC** | **None** on index. Export uses org-scoped Client query. |
| **Vue Pages** | **Reports/Index.vue does NOT exist** — Controller renders it; 500 or blank. |
| **Status** | **Missing** — Page missing. |

---

## B) Notifications Deep Check

| Question | Answer |
|----------|--------|
| **Route exists?** | Yes. GET `/org/{slug}/notifications`, `/notifications/list`, POST read-all, `/{id}/read` |
| **Controller** | NotificationCenterController (index, list, markAllRead, markRead) |
| **Page** | Notifications/Index.vue exists |
| **Storage** | Laravel `notifications` table (uuid, type, morphs(notifiable), data jsonb, read_at) |
| **Tenant scoping** | `data->>'organization_id' = ?` in all queries. No FK; relies on notification payload. |
| **RBAC** | **Not enforced** — No authorize() or permission check. |
| **Weak/missing** | 1) No permission (e.g. notifications.view). 2) Org in JSON only — no index for org filtering. 3) Any org member can see all org notifications. |

---

## C) Permission Mismatch Summary

| Policy uses | Seeder has | Action |
|-------------|------------|--------|
| clients.update | clients.edit | Add clients.update to seeder or alias |
| projects.update | projects.edit | Add projects.update or alias |
| tasks.update | tasks.edit | Add tasks.update or alias |
| users.update | users.edit | Add users.update or alias |

---

## D) Validation Commands

```bash
# Route list
./vendor/bin/sail php artisan route:list

# Migration status
./vendor/bin/sail php artisan migrate:status

# Tests
./vendor/bin/sail php artisan test

# Build
./vendor/bin/sail npm run build
```

---

## E) Next 3 PR-Sized Tasks (Zero Duplication)

1. **PR: Reports page + RBAC gaps**
   - Create `Reports/Index.vue` (ReportController expects it).
   - Add authorize() to ReportController (e.g. reports.view or reuse clients.view).
   - Fix ReportController export: use `primary_contact_email` / `primary_contact_phone` instead of `email`/`phone`.

2. **PR: Permission canonicalization**
   - Add `clients.update`, `tasks.update`, `users.update`, `projects.update` to RolesAndPermissionsSeeder (or ensure aliasMap covers policy checks).
   - Assign to Manager/Owner roles. Verify policy checks pass.

3. **PR: Unprotected controllers**
   - Add authorize() to: NotificationCenterController (notifications.view?), SettingsController (settings.manage?), SubscriptionController (billing.view?), ClientsImportController (clients.import).
   - Uncomment and fix HRMController authorize() calls.
