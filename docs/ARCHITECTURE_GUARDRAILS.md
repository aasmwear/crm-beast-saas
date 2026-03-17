# Architecture Guardrails

> Constraints and patterns that must be followed when modifying the CRM Beast codebase.

---

## Multi-Tenancy

- **Route pattern:** All tenant routes must be under `/org/{organization:slug}/...`
- **Tenant resolution:** `ResolveTenant` middleware resolves org from route and sets Spatie team context. The `active_organization_id` column is canonical; no per-request schema checks.
- **No cross-tenant leakage:** Every org-scoped query MUST filter by `organization_id` or equivalent
- **Tenant-owned attachments:** `project_files` MUST always carry `organization_id`; read/write paths must scope by `organization_id` plus parent id (`project_id`) for defense-in-depth
- **Validation hardening:** Foreign-key inputs (e.g. `client_id`, `department_id`, assignee user IDs) MUST use org-scoped validation rules (`Rule::exists(...)->where('organization_id', $org->id)` or organization membership pivot checks)
- **Team ID = Organization ID:** Spatie permission/role scoping uses `team_id` = `organization_id`

---

## RBAC (Spatie Laravel-Permission + Teams)

- **User model:** Must use `HasRoles` trait (Spatie)
- **Roles:** Team-scoped (`team_id` = `organization_id`) or global (`team_id` = null)
- **Permissions:** Granular, seeded via `RolesAndPermissionsSeeder`; naming `module.action` (e.g. `clients.view`, `projects.create`)
- **Permission checks:** Use `$user->can('permission.name')` or policy `$this->authorize()` with tenant context set
- **Clients module:** clients.view (list/show), clients.create, clients.edit (edit/update), clients.delete, clients.manage/clients.export (bulk)
- **Projects module:** projects.view (list/show/board/calendar), projects.create, projects.edit (edit/update/status/files), projects.delete, projects.manage (bulk/special)
- **Tasks module:** tasks.view (list/board/show), tasks.create, tasks.edit (edit/update/move/submit/review), tasks.delete, tasks.manage (bulk/special), tasks.review (review submitted tasks)
- **Task visibility/indexing:** `tasks.assignees` is JSONB and visibility checks use `whereJsonContains`; PostgreSQL deployments MUST keep a GIN index on `tasks.assignees` (`jsonb_path_ops`) to avoid full scans at tenant scale.
- **Task board safety:** Board endpoints must be paginated (current baseline: `paginate(50)->withQueryString()`), never unbounded `->get()`.
- **Attendance module:** attendance.view (index/history), attendance.create (clock in/out), attendance.edit (update status/notes), attendance.delete (destroy), attendance.manage (approve, bulk); legacy: view-own, clock-in, clock-out, approve
- **Attendance index filters:** date_from, date_to (nullable dates), status (open|closed|approved), approved (yes|no). user_id filter only honored when user has attendance.view (canViewAll); view-own users receive validation error (302) on crafted user_id for another user.
- **Attendance data integrity:** "Today" and one-record-per-day checks use `organization.timezone` (not app default). Partial unique index on `(organization_id, user_id) WHERE clock_out_at IS NULL` prevents double clock-in race. Approve requires `clock_out_at IS NOT NULL` and `status === 'closed'`.
- **HRM module:** hrm.view (index), hrm.create (store), hrm.edit (update), hrm.delete (destroy), hrm.manage (all); UserPolicy accepts hrm.* or users.*
- **HRM onboarding security:** Never assign predictable default passwords. New employees must get a cryptographically random password server-side and use password reset link flow for first credential setup. Raw passwords must never be flashed, logged, or rendered.
- **HRM reliability:** Employee create/update and role assignment must be atomic (single transaction). Do not silently swallow role sync failures; log context (`organization_id`, `user_id`, attempted role, exception class/message) and return a safe actionable error.
- **Granular Role Maker:** Tenant admins with `roles.manage` can:
  - View, create, edit, delete, clone (team-scoped) roles
  - Assign permissions via permission matrix UI
  - Cannot delete roles assigned to users
  - **Role cloning:** Team-scoped roles only; creates copy with `-copy` suffix (or `-copy-2`, etc. if exists); copies all permissions. Global roles cannot be cloned.
  - **Global roles are immutable:** Roles with `team_id = null` (Owner, Manager, Employee, Client, Super Admin) cannot have their permissions modified by tenants. API returns 403. UI disables matrix and Save for these roles.
- **Authorization:** Policies/Gates for server-side enforcement; UI hiding is not security
- **Custom Fields module:** custom-fields.manage (create/edit/delete field definitions). Field definitions are org-scoped; values link to entities (entity_type, entity_id). v1 supports Client entity only. **Phase 2:** Select/multiselect values MUST be within configured options (ValidationException); client index supports `cf[slug]=value` filtering (text: contains, number/date/select: exact); filters tenant-scoped, only known slugs applied.

---

## Granular Role Maker UI

- **Page:** `/org/{org}/settings/roles`
- **Role list:** Shows role name, permission count, team/global badge
- **Permission matrix:** Grouped by module (Clients, Projects, Tasks, Attendance, etc.); "Select All" per module
- **Validation:** Role name unique per organization; cannot delete if assigned
- **Backend:** Uses Spatie `syncPermissions()`; `team_id` = `organization_id` for new roles
- **Clone:** `POST roles.clone` creates a team-scoped copy with `-copy` suffix; same permissions; global roles cannot be cloned

---

## Billing & Entitlements

- **Plan resolution:** `organization_subscriptions.plan_key` canonical; fallback to `organizations.plan`
- **Entitlements:** Resolved via `EntitlementsService` (plan → addons → org overrides)
- **Seat limit:** Staff users count toward limit; portal users excluded

---

## Scale & Query Safety

- **Pipeline computed grouping:** Pipeline/board views that group items by status MUST use a single computed map (O(n) grouping) instead of repeated filter calls per column. Example: `clientsByStatus` in Pipeline.vue groups once; template does O(1) lookups.
- **Dropdown/filter bounds:** Dropdown and filter option queries (clients, users, projects) MUST use `->limit(200)->get()` or similar safe bounds; never unbounded `->get()`.
- **Show-page eager loads:** Heavy relations (tasks, files, comments, activities) on show pages MUST have safe limits (e.g. `->limit(100)` or `->limit(200)`) to avoid memory blow-up.
- **Batch lookups:** Avoid per-item `exists()` or `first()` in loops; collect keys and query once, map in memory.
- **Aggregation consolidation:** Use single grouped queries (e.g. `date_trunc` + `GROUP BY`) instead of looping with per-iteration queries.
- **Per-request cache:** Services that read expensive aggregates (e.g. StorageUsageService) should cache by org_id per request when called multiple times.

## Audit & Observability

- **AuditLogger:** Use for sensitive mutations (clients, projects, roles, billing, etc.)
- **Readiness:** `GET /_readiness` for load-balancer probes
- **Structured logs:** Use dot-notation keys for webhook/billing failures
