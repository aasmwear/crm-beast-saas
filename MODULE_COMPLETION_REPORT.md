# CRM Beast — Module Completion Report

**Audit Date:** 2025-02-20  
**Scope:** Clients, Projects, Tasks, Attendance, Announcements, Notifications, Activity, Settings, Billing

---

## 1. NON-NEGOTIABLE CONEXT VERIFICATION

### 1.1 ResolveTenant Middleware (`app/Http/Middleware/ResolveTenant.php`)

- **Behavior:** Resolves org from route param `organization` (slug or model binding).
- **Tenant scoping:** Sets `app()->instance('scoped.organization', $org)`.
- **Spatie team context:** `app(PermissionRegistrar::class)->setPermissionsTeamId($org->id)` when `Auth::check()`.
- **Active org sync:** Updates `user->active_organization_id` to match route org if schema supports it.
- **All tenant routes:** Under `Route::prefix('org/{organization:slug}')->middleware(['auth', 'verified', 'resolveTenant'])` (lines 198–199).

### 1.2 User Model HasRoles (`app/Models/User.php`)

- **Trait:** `use HasRoles` (line 31).
- **Guard:** `protected $guard_name = 'web'` (line 33).

### 1.3 Seeded Permissions (`database/seeders/RolesAndPermissionsSeeder.php`)

- **Permissions count:** 34 (not 164 as stated in `.cursorrules`).
- **Permissions:** `clients.create`, `clients.view`, `clients.edit`, `clients.delete`, `clients.manage`, `projects.create`, `projects.view`, `projects.edit`, `projects.delete`, `roles.manage`, `roles.view`, `roles.assign`, `users.manage`, `users.view`, `users.create`, `users.edit`, `users.delete`, `users.assign-roles`, `tasks.create`, `tasks.view`, `tasks.edit`, `tasks.delete`, `messages.create`, `financials.view`, `contacts.manage`, `departments.view`, `departments.create`, `departments.update`, `departments.delete`, `attendance.view`, `attendance.view-own`, `attendance.clock-in`, `attendance.clock-out`, `attendance.approve`, `attendance.manage`, `announcements.view`, `announcements.create`, `announcements.update`, `announcements.delete`, `announcements.pin`.
- **Permission mismatch:** Policies use `clients.update`, `tasks.update`, `users.update` but seeder has `clients.edit`, `tasks.edit`, `users.edit`, so permission checks for those actions will fail for users with only `.edit` roles.

### 1.4 Production Build

- **`public/build/manifest.json`:** Present (contains `app-B41ANOZO.js`, `app-CmyAZtBN.css`).
- **`npm run dev`:** Not used per project rules.

---

## 2. MODULE-BY-MODULE BREAKDOWN

### 2.1 CLIENTS

| Item | Details |
|------|---------|
| **Routes** | `clients.index` (GET `/clients`), `clients.import` (GET/POST `/clients/import`), `clients.create` (GET `/clients/create`), `clients.pipeline` (GET `/clients/pipeline`), `clients.pipeline.update` (POST `/clients/{client}/pipeline`), `clients.store` (POST `/clients`), `clients.show` (GET `/clients/{client}`), `clients.edit` (GET `/clients/{client}/edit`), `clients.update` (PUT `/clients/{client}`), `clients.destroy` (DELETE `/clients/{client}`), `clients.contacts.store/update/destroy`, `clients.contacts.portal.store`, `clients.export` |
| **Controllers** | `ClientsInertiaController`, `ClientController`, `ClientContactController`, `ClientsImportController`, `ClientsPipelineController`, `PortalAccessController` |
| **Models** | `Client`, `ClientContact` |
| **Policies** | `ClientPolicy` (viewAny, view, create, update, delete) |
| **RBAC** | All actions protected via `$this->authorize()`. Uses `clients.view`, `clients.create`, `clients.update`, `clients.delete` (policy uses `clients.update` but seeder has `clients.edit`). |
| **Vue Pages** | `Clients/Index.vue`, `Clients/Show.vue`, `Clients/Edit.vue`, `Clients/Create.vue`, `Clients/Import.vue`, `Clients/Pipeline.vue`, `Clients/QuickCreate.vue` |
| **Gaps** | Permission mismatch: `clients.update` vs `clients.edit`. `ClientsImportController` has no authorization. |

---

### 2.2 PROJECTS

| Item | Details |
|------|---------|
| **Routes** | `projects.board` (GET `/projects/board`), `projects.calendar` (GET `/projects/calendar`), `projects.comments.store`, `comments.destroy`, `projects.files.store/destroy/toggleVisibility/download`, `projects.messages.index/store/download`, `projects.pipeline.update`, `projects` resource (index, create, store, show, edit, update, destroy) |
| **Controllers** | `ProjectController`, `ProjectBoardController`, `ProjectCalendarController`, `CommentController`, `ProjectFileController`, `ProjectMessagesController` |
| **Models** | `Project`, `Comment`, `ProjectFile`, `ProjectMessage` |
| **Policies** | `ProjectPolicy` (viewAny, view, create, update, delete, viewBudget) |
| **RBAC** | All actions protected. Uses `projects.view`, `projects.create`, `projects.update`, `projects.delete`, `financials.view`. |
| **Vue Pages** | `Projects/Index.vue`, `Projects/Board.vue`, `Projects/Calendar.vue`, `Projects/Show.vue`, `Projects/Create.vue`, `Projects/Edit.vue`, `Projects/List.vue`, `Projects/Messages.vue` |
| **Gaps** | None. |

---

### 2.3 TASKS

| Item | Details |
|------|---------|
| **Routes** | `tasks.board` (GET `/tasks/board`), `tasks.index` (GET `/tasks`), `tasks.store` (POST `/tasks`), `tasks.show` (GET `/tasks/{task}`), `tasks.update` (PUT `/tasks/{task}`), `tasks.destroy` (DELETE `/tasks/{task}`), `tasks.submit`, `tasks.review` |
| **Feature gate** | `feature:tasks` middleware (enabled in `config/features.php`) |
| **Controllers** | `TaskController`, `TaskBoardController` |
| **Models** | `Task` |
| **Policies** | `TaskPolicy` (viewAny, view, create, update, delete, submit, review) |
| **RBAC** | All actions protected. Uses `tasks.view`, `tasks.create`, `tasks.update`, `tasks.delete`, `tasks.review`. Seeder has `tasks.edit` not `tasks.update`; `tasks.review` not seeded. |
| **Vue Pages** | `Tasks/Index.vue`, `Tasks/Board.vue` |
| **Gaps** | Permission mismatch: `tasks.update` vs `tasks.edit`. `tasks.review` used in policy but not in seeder. |

---

### 2.4 ATTENDANCE

| Item | Details |
|------|---------|
| **Routes** | `attendance.index` (GET `/attendance`), `attendance.clockIn`, `attendance.clockOut`, `attendance.approve`, `attendance.update` (PATCH) |
| **Feature gate** | `feature:attendance` middleware (enabled in `config/features.php`) |
| **Controllers** | `AttendanceController` |
| **Models** | `Attendance` |
| **Policies** | `AttendancePolicy` (viewAny, view, clockIn, clockOut, approve, update) |
| **RBAC** | All actions protected. Uses `attendance.view`, `attendance.view-own`, `attendance.clock-in`, `attendance.clock-out`, `attendance.approve`, `attendance.manage`. |
| **Vue Pages** | `Attendance/Index.vue` |
| **Gaps** | None. |

---

### 2.5 ANNOUNCEMENTS

| Item | Details |
|------|---------|
| **Routes** | `announcements.index` (GET `/announcements`), `announcements.store` (POST), `announcements.show` (GET `/{announcement}`), `announcements.update` (PUT), `announcements.destroy` (DELETE) |
| **Controllers** | `AnnouncementController` |
| **Models** | `Announcement` |
| **Policies** | `AnnouncementPolicy` (viewAny, view, create, update, delete, pin) |
| **RBAC** | All actions protected. Uses `announcements.view`, `announcements.create`, `announcements.update`, `announcements.delete`, `announcements.pin`. |
| **Vue Pages** | `Announcements/Index.vue` |
| **Gaps** | `show` route redirects to index. No dedicated show page. |

---

### 2.6 NOTIFICATIONS

| Item | Details |
|------|---------|
| **Routes** | `notifications.index` (GET `/notifications`), `notifications.list` (GET `/notifications/list`), `notifications.readAll` (POST), `notifications.read` (POST `/{notification}`) |
| **Controllers** | `NotificationCenterController` |
| **Models** | `DatabaseNotification` (Laravel) |
| **Policies** | None. |
| **RBAC** | No authorization. Uses `$user->notifications()` scoped by `organization_id` in data. |
| **Vue Pages** | `Notifications/Index.vue`, `Notifications/Settings.vue` |
| **Gaps** | No permission checks. Any authenticated user in org can view/mark all notifications. |

---

### 2.7 ACTIVITY

| Item | Details |
|------|---------|
| **Routes** | `activity.index` (GET `/activity`) |
| **Controllers** | `ActivityController` |
| **Models** | `audit_logs` table (no Eloquent model) |
| **Policies** | None. |
| **RBAC** | Uses `$this->authorize('viewAny', Task::class)` — reuses Task visibility as gate. |
| **Vue Pages** | `Activity/Index.vue` |
| **Gaps** | No dedicated `activity.view` permission. Activity is gated by task visibility. |

---

### 2.8 SETTINGS

| Item | Details |
|------|---------|
| **Routes** | `settings.index` (GET `/settings`), `settings.update` (PUT/POST `/settings`) |
| **Controllers** | `SettingsController` |
| **Models** | `Organization` |
| **Policies** | None. |
| **RBAC** | No authorization. Any authenticated user can view and update org settings. |
| **Vue Pages** | `Settings/Index.vue`, `Settings/Roles.vue` |
| **Gaps** | No permission checks. Settings page links to Roles & Permissions (`roles.index`). |

---

### 2.9 BILLING

| Item | Details |
|------|---------|
| **Routes** | `billing.index` (GET `/billing`), `billing.checkout` (POST), `billing.portal` (GET) |
| **Controllers** | `Admin\SubscriptionController` |
| **Models** | `Organization` (Cashier) |
| **Policies** | None. |
| **RBAC** | No authorization. Any authenticated user can view billing, checkout, and portal. |
| **Vue Pages** | `Billing/Index.vue`, `Billing/Manage.vue`, `Billing/Subscribe.vue` |
| **Gaps** | No permission checks. |

---

### 2.10 OTHER (Invoices, Reports, HRM, Departments, Users, Roles)

| Module | Routes | RBAC | Notes |
|--------|--------|------|-------|
| **Invoices** | Full CRUD + download, mark-sent, mark-paid | `InvoicePolicy` (clients.view, clients.manage) | Done. |
| **Reports** | `reports.index`, `export.csv`, `backup.snapshot` | None | `ReportController::index` renders `Reports/Index` but **`Reports/Index.vue` does not exist** — broken. |
| **HRM** | `hrm.index`, `hrm.store`, `hrm.update`, `hrm.destroy` | **Disabled** (COUNCIL OVERRIDE comments) | No authorization. |
| **Departments** | Full CRUD | `DepartmentPolicy` | Done. |
| **Users** | `users.index`, `users.update` | `UserPolicy` | Policy uses `users.update` but seeder has `users.edit`. |
| **Roles** | `roles.index`, `roles.store`, `roles.update`, `roles.save` | `hasPermissionTo('roles.manage')` in controller + FormRequests | Done. |

---

## 3. SUMMARY TABLE

| Module | Status | RBAC Coverage | Key Gaps | Next Step |
|--------|--------|---------------|----------|------------|
| **Clients** | Done | Partial | `clients.update` vs `clients.edit`; `ClientsImportController` has no auth | Align permission names; add auth to import |
| **Projects** | Done | Full | None | — |
| **Tasks** | Done | Partial | `tasks.update` vs `tasks.edit`; `tasks.review` not seeded | Align permission names; add `tasks.review` |
| **Attendance** | Done | Full | None | — |
| **Announcements** | Done | Full | None | — |
| **Notifications** | Partial | None | No permission checks | Add `notifications.view` and enforce |
| **Activity** | Partial | Indirect | Gated by Task visibility; no dedicated permission | Add `activity.view` or document as intentional |
| **Settings** | Partial | None | No permission checks | Add `settings.manage` and enforce |
| **Billing** | Partial | None | No permission checks | Add `billing.manage` and enforce |

---

## 4. CHECK RESULTS

| Check | Result |
|-------|--------|
| **php artisan test** | Not run (PHP not in PATH in audit environment). |
| **phpstan** | Not run (PHP not in PATH). Config: `phpstan.neon` level 6, paths: app, routes, database. |
| **npm run build** | Not run (npm not in PATH). `public/build/manifest.json` exists. |

---

## 5. PROPOSED NEXT 3 PR-SIZED TASKS

1. **Permission alignment (Seeder + Policies)**  
   Add `clients.update`, `tasks.update`, `users.update`, `tasks.review` to `RolesAndPermissionsSeeder` and assign to appropriate roles. Optionally deprecate `clients.edit`, `tasks.edit`, `users.edit` or map them to the same capability.  
   **Files:** `database/seeders/RolesAndPermissionsSeeder.php`, `app/Policies/ClientPolicy.php`, `app/Policies/TaskPolicy.php`, `app/Policies/UserPolicy.php`.

2. **Create `Reports/Index.vue`**  
   Implement `resources/js/Pages/Reports/Index.vue` so `ReportController::index` renders a valid page. Include links to export CSV and backup snapshot if applicable.  
   **Files:** `resources/js/Pages/Reports/Index.vue`, `app/Http/Controllers/ReportController.php`.

3. **Add RBAC to Settings, Billing, Notifications**  
   Add `settings.manage`, `billing.manage`, `notifications.view` to seeder and enforce in `SettingsController`, `SubscriptionController`, `NotificationCenterController`.  
   **Files:** `database/seeders/RolesAndPermissionsSeeder.php`, `app/Http/Controllers/SettingsController.php`, `app/Http/Controllers/Admin/SubscriptionController.php`, `app/Http/Controllers/NotificationCenterController.php`, new policies or inline checks.
