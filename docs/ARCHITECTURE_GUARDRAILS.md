# Architecture Guardrails

> Constraints and patterns that must be followed when modifying the CRM Beast codebase.

---

## Multi-Tenancy

- **Route pattern:** All tenant routes must be under `/org/{organization:slug}/...`
- **Tenant resolution:** `ResolveTenant` middleware resolves org from route and sets Spatie team context. The `active_organization_id` column is canonical; no per-request schema checks.
- **No cross-tenant leakage:** Every org-scoped query MUST filter by `organization_id` or equivalent
- **Comments:** Each row has `organization_id` (backfilled from project/task). `CommentController` sets and checks it; `Project::comments()` scopes by parent org when persisted; project show eager-load qualifies `comments.organization_id` to the route org; `DELETE /comments/{comment}` uses scoped binding via `Organization::comments()` so a comment cannot be addressed under another tenant’s slug.
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
- **Project task progress (v1 denormalization):** `projects.tasks_count`, `open_tasks_count`, `completed_tasks_count`, `progress_percent` are maintained by `ProjectTaskProgressService::recalculateForProjectId()` from non-trashed tasks. **Completed** statuses for the ratio match list UX: `done`, `completed`, `closed`, `finished` (case-insensitive). Updates run from `Task` model `saved` / `deleted` / `restored` and after lifecycle hard-delete of trashed tasks so counts stay consistent. Project index reads these columns instead of loading all task statuses. v1 is transactional/recompute-per-change; a future projection or event stream could replace internals without changing the column contract.
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
- **Custom Fields module:** custom-fields.manage (create/edit/delete field definitions). Field definitions are org-scoped; **`custom_field_values` rows carry `organization_id` directly** (denormalized from the parent field, backfilled on migrate) so hot queries and future lifecycle/read-model work can filter by tenant without joining `custom_fields`. Values still link to entities via (entity_type, entity_id). v1 supports Client entity only. **Phase 2:** Select/multiselect values MUST be within configured options (ValidationException); client index supports `cf[slug]=value` filtering (text: contains, number/date/select: exact); filters tenant-scoped, only known slugs applied; `whereHas` on values includes `organization_id` for defense in depth.

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
- **Warm-table archives:** `lifecycle:archive-audit-logs`, `lifecycle:archive-activities`, and `lifecycle:archive-comments` default to **dry-run**; **`--execute`** moves rows in batches via `WarmTableArchiver` (idempotent, optional **`--organization=`**). Scheduled weekly UTC (audit/activities **03:00**, comments **03:30** — staggered). Product UI reads **hot** `comments` / `audit_logs` / `activities` only; archived rows live in `*_archive` tables until a future read path or restore workflow exists.

---

## Metrics Read-Model (v1)

- **Table:** `org_daily_metrics` stores daily per-org snapshots (clients, projects, tasks, open_tasks, attendance, activities, invoices, revenue_cents, outstanding_cents, users).
- **Grain:** One row per org per day. Unique constraint on `(organization_id, metric_date)`.
- **Service:** `OrgMetricsSnapshotService` computes and upserts metrics. Idempotent; safe to re-run.
- **Command:** `php artisan metrics:snapshot-orgs` (defaults to yesterday; supports `--date` and `--org` filters).
- **Schedule:** Daily at 01:00 UTC via `routes/console.php`.
- **Admin dashboard hybrid:** The tenant admin dashboard (`/org/{org}/dashboard`) now reads from `org_daily_metrics` when snapshot coverage exists for the requested date range. Snapshots are preferred; live-query fallback remains for missing or insufficient data. KPIs (revenue, outstanding), stats (clients/projects/tasks created-in-range), and series (clients30d, projects30d, tasks30d) use snapshots when sufficient. active_projects, monthly_revenue, project_status, workload, activities stay live.
- **Platform Feature Usage hybrid:** The platform Feature Usage dashboard (`FeatureUsageDashboardController`) uses `org_daily_metrics` for **module adoption aggregates** (orgs_with_any, total_records per module, and `summary.orgs_using_any_module`) when there is **full coverage**: row count for **yesterday** (app timezone) equals `organizations` count. Same snapshot columns as MODULES: clients, projects, tasks, attendance, invoices. **Billing setup** metrics (Stripe, subscriptions, addons) stay live — not in the snapshot. If any org is missing a row for that date (partial snapshot, new org before next run, etc.), the controller falls back entirely to live table counts for adoption so aggregates are never mixed.
- **Platform Org Health partial hybrid:** `OrgHealthController` uses `users_count` from **yesterday’s** `org_daily_metrics` row **per organization** for `seats_active` when that row exists; otherwise the live `organization_user` + `users.client_id` count. Billing, Stripe presence, subscription status, seat limits, storage pressure, and webhook aggregates remain live. No other snapshot columns are forced into this dashboard until semantics clearly match.
- **Migration path:** Remaining platform dashboards (e.g. system performance, revenue) stay live unless a column maps cleanly to operator expectations.
- **Soft-delete aware:** Snapshot queries exclude soft-deleted rows for clients, tasks, and attendance.

---

## Webhook event summaries (read model)

- **Table:** `webhook_event_summaries` — denormalized aggregates over `stripe_webhook_events` (Stripe only in v1: `provider = stripe`).
- **Grain:** One row per `(provider, organization_scope, event_type)`. `organization_scope` is `unscoped` when the source events have `organization_id` null, otherwise the string form of the org id (matches FK when set).
- **Columns:** `total_count`, `success_count` (status `processed`), `failure_count` (status `failed`), `last_received_at`, `last_processed_at`, `last_error_at`, `last_error_message` (notes from the latest failure in that bucket).
- **Service:** `App\Services\Webhooks\WebhookSummaryService` — `rebuildAll()` replaces all rows for a provider; `rebuildForOrganizationScope(?int $organizationId)` replaces one tenant bucket. Idempotent full recompute from the raw table (no change to webhook ingestion or handler semantics).
- **Command:** `php artisan webhooks:rebuild-summaries` — optional `--provider=` (default `stripe`), optional `--organization=` (existing org id only).
- **Schedule:** Daily **02:30** app timezone in `routes/console.php`, after `lifecycle:prune-webhooks` (02:00), so post-prune aggregates stay aligned with retained rows.
- **Dashboard usage:** Platform System Performance `webhook_health` uses summarized **lifetime** totals when any summary row exists for `provider = stripe`; **recent_failures** always come from `stripe_webhook_events`.

---

## Webhook daily rollups (time-window read model)

- **Table:** `webhook_event_daily_rollups` — per **calendar day** (app timezone), same grain keys as summaries plus `event_date`.
- **Columns:** `total_count`, `success_count` (processed), `failure_count` (failed), `last_received_at`, `last_processed_at`.
- **Service:** `App\Services\Webhooks\WebhookDailyRollupService` — `rebuildAll(?daysWindow)` deletes either all rows for the provider or only rows whose `event_date` falls in the last *N* calendar days (inclusive of today), then reinserts from `stripe_webhook_events` (PostgreSQL-only grouping). `rebuildForOrganizationScope($organizationId, ?daysWindow)` does the same for one `organization_scope`.
- **Command:** `php artisan webhooks:rebuild-daily-rollups` — `--provider=` (default `stripe`), `--organization=`, optional `--days=` (rolling calendar-day span from today).
- **Schedule:** Daily **02:40 UTC** in `routes/console.php`, after `webhooks:rebuild-summaries` (02:30 app timezone) and `lifecycle:prune-webhooks` (02:00 app timezone); operators should confirm wall-clock ordering for their deployment.
- **Dashboard usage:** When any rollup row exists for `provider = stripe`, System Performance uses rollups for **failed_last_24h** (sum of `failure_count` on the 1–2 calendar days that intersect `[now - 24h, now]` in app TZ), **failed_last_7d** (sum over **seven calendar days** ending today in app TZ), and **orgs_with_failures_7d** (`count(distinct organization_id)` with `failure_count > 0` in that same 7-day window). These definitions **differ slightly** from strict `created_at >= now()->subDays(n)` rolling windows at day boundaries; if rollups are empty, the controller falls back to raw-event queries.

---

## Data Lifecycle & Retention

- **Policy doc:** `docs/DATA_LIFECYCLE.md` defines retention categories (hot/warm/cold), windows, and operator rules
- **Config:** `config/lifecycle.php` declares per-table retention windows and categories
- **Reporting:** `php artisan lifecycle:report` shows row counts and aged-out candidates (read-only, non-destructive). For `notifications`, aged-out counts are **read** rows only (`read_at` set) with `created_at` before the cutoff.
- **Warm prune (notifications):** `lifecycle:prune-notifications` and `lifecycle:prune-notification-events` delete aged rows only with `--execute` (dry-run default); optional `--organization=`. Notifications: **read-only** prune by `created_at` age; unread preserved. **Scheduled** daily 02:15 / 02:20 in `routes/console.php` after other prunes. See `docs/DATA_LIFECYCLE.md`.
- **Archive (Phase 3):** `lifecycle:archive-audit-logs` and `lifecycle:archive-activities` move rows older than the configured window from hot tables to `audit_logs_archive` / `activities_archive`. Dry-run by default; `--execute` performs batched moves. **Scheduled:** weekly Sunday 03:00 UTC with `--execute` in `routes/console.php`. See `docs/DATA_LIFECYCLE.md`.
- **Service:** `RetentionPolicy` value object provides programmatic access to retention config (cutoff dates, category checks); `App\Services\Lifecycle\WarmTableArchiver` implements warm-table moves.
- **Operator rules:**
  - Never delete `audit_logs` without archiving first
  - Never bulk-prune `attendance` (legal/HR retention)
  - `stripe_webhook_events` > 90 days and `failed_jobs` > 30 days are safe to prune
  - All lifecycle jobs must be tenant-scoped and idempotent
  - Log all lifecycle actions to `audit_logs`
