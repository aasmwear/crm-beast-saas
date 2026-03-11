# Architecture Guardrails

> Constraints and patterns that must be followed when modifying the CRM Beast codebase.

---

## Multi-Tenancy

- **Route pattern:** All tenant routes must be under `/org/{organization:slug}/...`
- **Tenant resolution:** `ResolveTenant` middleware resolves org from route and sets Spatie team context
- **No cross-tenant leakage:** Every org-scoped query MUST filter by `organization_id` or equivalent
- **Team ID = Organization ID:** Spatie permission/role scoping uses `team_id` = `organization_id`

---

## RBAC (Spatie Laravel-Permission + Teams)

- **User model:** Must use `HasRoles` trait (Spatie)
- **Roles:** Team-scoped (`team_id` = `organization_id`) or global (`team_id` = null)
- **Permissions:** Granular, seeded via `RolesAndPermissionsSeeder`; naming `module.action` (e.g. `clients.view`, `projects.create`)
- **Permission checks:** Use `$user->can('permission.name')` or policy `$this->authorize()` with tenant context set
- **Clients module:** clients.view (list/show), clients.create, clients.edit (edit/update), clients.delete, clients.manage/clients.export (bulk)
- **Projects module:** projects.view (list/show/board/calendar), projects.create, projects.edit (edit/update/status/files), projects.delete, projects.manage (bulk/special)
- **Granular Role Maker:** Tenant admins with `roles.manage` can:
  - View, create, edit, delete (team-scoped) roles
  - Assign permissions via permission matrix UI
  - Cannot delete roles assigned to users
  - Cannot modify global roles (rename/delete)
- **Authorization:** Policies/Gates for server-side enforcement; UI hiding is not security

---

## Granular Role Maker UI

- **Page:** `/org/{org}/settings/roles`
- **Role list:** Shows role name, permission count, team/global badge
- **Permission matrix:** Grouped by module (Clients, Projects, Tasks, Attendance, etc.); "Select All" per module
- **Validation:** Role name unique per organization; cannot delete if assigned
- **Backend:** Uses Spatie `syncPermissions()`; `team_id` = `organization_id` for new roles

---

## Billing & Entitlements

- **Plan resolution:** `organization_subscriptions.plan_key` canonical; fallback to `organizations.plan`
- **Entitlements:** Resolved via `EntitlementsService` (plan → addons → org overrides)
- **Seat limit:** Staff users count toward limit; portal users excluded

---

## Audit & Observability

- **AuditLogger:** Use for sensitive mutations (clients, projects, roles, billing, etc.)
- **Readiness:** `GET /_readiness` for load-balancer probes
- **Structured logs:** Use dot-notation keys for webhook/billing failures
