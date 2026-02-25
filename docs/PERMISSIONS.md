# CRM Beast — Permissions

## Overview

- **Package:** Spatie Laravel-Permission (Teams)
- **Seeder:** `database/seeders/RolesAndPermissionsSeeder.php`
- **Catalog (Matrix UI):** `RolePermissionController::buildPermissionCatalog()` — single source of truth for modules, actions, matrix mapping, specials, aliases

---

## Canonical Permission Standard

**CRUD verbs:** `*.view`, `*.create`, `*.update`, `*.delete`  
**Admin panels:** `*.manage`  
**Specials:** `*.import`, `*.export`, `attendance.approve`, `tasks.review`, `roles.assign`, `users.assign-roles`, `reports.export`, etc.

| Module      | View | Create | Update | Delete | Manage | Specials                          |
|-------------|------|--------|--------|--------|--------|------------------------------------|
| clients     | ✓    | ✓      | ✓      | ✓      | ✓      | import, export                     |
| projects    | ✓    | ✓      | ✓      | ✓      | —     | —                                  |
| tasks       | ✓    | ✓      | ✓      | ✓      | —     | review                             |
| users       | ✓    | ✓      | ✓      | ✓      | ✓     | assign-roles                       |
| departments | ✓    | ✓      | ✓      | ✓      | —     | —                                  |
| announcements | ✓  | ✓      | ✓      | ✓      | —     | pin                                |
| attendance  | ✓    | —      | —      | —      | ✓     | view-own, clock-in, clock-out, approve |
| reports     | ✓    | —      | —      | —      | —     | export                             |
| activity    | ✓    | —      | —      | —      | —     | —                                  |
| roles       | ✓    | —      | —      | —      | ✓     | assign                             |

---

## Legacy vs Canonical

Legacy `*.edit` permissions are kept for backward compatibility but are **not** used by policies or the matrix UI.

| Legacy (deprecated) | Canonical |
|---------------------|-----------|
| `clients.edit`      | `clients.update` |
| `projects.edit`     | `projects.update` |
| `tasks.edit`        | `tasks.update` |
| `users.edit`        | `users.update` |

The catalog `aliasMap` maps **legacy → canonical** for display consistency. Policies and controllers use **canonical only**.

---

## Reconciliation Command

For existing databases that were seeded before canonical permissions existed:

```bash
# Ensure canonical permissions exist (creates if missing)
php artisan permissions:reconcile

# Also assign canonical to roles that have legacy perms
php artisan permissions:reconcile --assign

# Preview without making changes
php artisan permissions:reconcile --assign --dry-run
```

**Behavior:**
- Creates `clients.update`, `projects.update`, `tasks.update`, `users.update` if they do not exist
- With `--assign`: adds canonical permissions to roles that currently have the corresponding legacy permission
- **Does NOT delete** any permissions or role assignments

**When to run:**
- After upgrading from a version that only had `*.edit`
- After restoring a DB backup that predates canonical permissions

---

## Seeded vs DB

- **Seeder** creates both canonical and legacy permissions; roles get canonical.
- **DB** may contain additional permissions from migrations or manual inserts.
- **Catalog** defines which permissions are "recognized" for the Matrix UI; others appear as "orphans" in Advanced mode.

---

## Matrix Structure

- **Modules:** clients, projects, tasks, attendance, announcements, etc.
- **Actions:** view, create, update, delete, manage
- **Specials:** e.g. `tasks.review`, `attendance.approve`, `roles.assign`, `clients.import`, `reports.export`
- **Frontend:** `resources/js/lib/permissionCatalog.ts` + `PermissionMatrix.vue`

---

## Team Scoping

- `team_id` = `organization_id` for tenant roles
- `team_id` = `null` for global roles (Super Admin, etc.)
- Uniqueness: role name + guard + team_id

---

## Enforcement Mapping

| Controller / Action | Permission | Description |
|---------------------|------------|-------------|
| **Notifications** | | |
| NotificationCenterController::index | `notifications.view` | View notifications list |
| NotificationCenterController::list | `notifications.view` | JSON dropdown list |
| NotificationCenterController::markAllRead | `notifications.update` | Mark all as read |
| NotificationCenterController::markRead | `notifications.update` | Mark single as read |
| **Settings** | | |
| SettingsController::index | `settings.view` | View org settings |
| SettingsController::update | `settings.update` | Update org branding/locale |
| **Billing** | | |
| SubscriptionController::index | `billing.view` | View billing page |
| SubscriptionController::portal | `billing.view` | Stripe customer portal |
| SubscriptionController::checkout | `billing.manage` | Checkout / plan changes |
| **HRM** | | |
| HRMController::index | UserPolicy::viewAny | `users.view` or `users.manage` |
| HRMController::store | UserPolicy::create | `users.create` |
| HRMController::update | UserPolicy::update | `users.update` or `users.manage` |
| HRMController::destroy | UserPolicy::delete | `users.delete` |
| **Clients Import** | | |
| ClientsInertiaController::import | `clients.import` | View import form |
| ClientsImportController::import | `clients.import` | POST import CSV |

- **Billing:** Owner-only by default (Owner/Super Admin get all permissions). Manager/Employee do not get billing.view or billing.manage.
- **Settings update:** Manager and Owner get `settings.update`; Employee gets `settings.view` only.

---

## Reports Permissions

| Permission      | Enforced in                    | Description                          |
|-----------------|--------------------------------|--------------------------------------|
| `reports.view`  | ReportController::index        | View Reports dashboard               |
| `reports.export`| ReportController::exportCsv    | Download client CSV export           |

- **Seeder:** Both permissions created; Owner/Manager get both; Employee gets reports.view only.
- **Matrix:** reports.view in matrix; reports.export as special under Reports module.

## Activity Permissions

| Permission      | Enforced in                    | Description                          |
|-----------------|--------------------------------|--------------------------------------|
| `activity.view` | ActivityController::index      | View Activity / Audit Log page       |

- **Seeder:** Permission created; Owner/Manager/Employee get activity.view.
- **Matrix:** activity.view in matrix under Activity module.
- **Nav:** IconRail and Command Palette hide Activity link when user lacks activity.view.
