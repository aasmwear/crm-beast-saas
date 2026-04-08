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
| Clients | ✅ | RBAC enforced: clients.view/create/edit/delete/manage; policy + UI hiding |
| Projects | ✅ | RBAC enforced: projects.view/create/edit/delete/manage; policy + UI hiding |
| Tasks | ✅ | RBAC enforced: tasks.view/create/edit/delete/manage/review; policy + UI hiding |
| Roles & Permissions UI | ✅ | Granular Role Maker: list/create/edit/delete roles, permission matrix, select-all per module |
| Attendance | ✅ | RBAC enforced: attendance.view/create/edit/delete/manage; policy + UI hiding; delete supported |
| Audit Logs | ✅ | activity.view RBAC; AuditLogger for Clients/Projects/Tasks/Attendance/Announcements/Import/Invoice/Settings/API Keys |
| Reports | ✅ | Page + RBAC + export (primary_contact_email/phone) |
| Notifications | 🟡 | Works; no RBAC |
| Settings | ✅ | RBAC; tabs: Organization, Branding, Work Hours, Notifications, Integrations, Modules, API Keys |
| Billing | 🟡 | Stripe/Cashier self-serve in place (checkout, webhook sync, portal access, invoice history UX); keep iterating on operational hardening |
| HRM | ✅ | RBAC enforced: hrm.view/create/edit/delete/manage; UserPolicy + hrm.*; scopeBindings; UI hiding |
| Custom Fields | ✅ | custom-fields.manage; org-scoped; Client entity; Settings → Custom Fields; Client create/edit dynamic fields |

---

## Known Blockers / Risks

1. **Permission canonicalization** — Pending: align `clients.update` ↔ `clients.edit`, `tasks.update` ↔ `tasks.edit`, etc. (see PERMISSIONS.md).
2. ~~**Reports page missing**~~ — Fixed: Reports/Index.vue created, RBAC (reports.view, reports.export), export uses primary_contact_*.
3. **Unprotected controllers** — No authorize(): NotificationCenterController, SettingsController, SubscriptionController (billing), ClientsImportController. ~~HRMController~~ — fixed: RBAC enforced.
4. **DB risks** — activities table has no organization_id; comments has no org_id; attendance.organization_id nullable. ~~ReportController export~~ — fixed: now uses primary_contact_email, primary_contact_phone. See docs/DB_SCHEMA.md.
5. ~~**Public API auth**~~ — Fixed: Bearer token auth via AuthenticateOrganizationApiKey middleware; GET /api/ping protected.

---

## Last PR Notes

- **Lifecycle: notification + notification_events prune (rescue-mission):**
  - **Goal:** Bound growth on Laravel `notifications` and org `notification_events` using the same dry-run / `--execute` pattern as other lifecycle prunes.
  - **Commands:** `lifecycle:prune-notifications` — deletes only rows with **`read_at` set** and **`created_at`** older than **180 days** (config); **unread never deleted**; optional `--organization=`. `lifecycle:prune-notification-events` — deletes rows with **`created_at`** older than **60 days**; optional `--organization=`.
  - **Config:** `config/lifecycle.php` — `notifications.retention_days` = 180, `prune_requires_read_at` + `prune_only`; `notification_events` retains 60d + `prune_only`. **`RetentionPolicy`** extended with `pruneRequiresReadAt` and `pruneOnly`; **`lifecycle:report`** uses them for correct “Aged Out” / PRUNE vs ARCHIVE labels.
  - **Schedule:** `routes/console.php` — `--execute` daily **02:15** (notifications) and **02:20** (notification_events), after webhook/failed/batch prunes (02:00–02:10). Uses app timezone unless changed.
  - **Tests:** `LifecyclePruneNotificationsTest`, `LifecyclePruneNotificationEventsTest`, `RetentionPolicyTest` (prune flags), `LifecycleReportTest` (notification PRUNE row; uses `Artisan::output()` because table rendering is not matched by `expectsOutputToContain`).
  - **Docs:** `docs/DATA_LIFECYCLE.md`, `docs/OBSERVABILITY_AND_RUNBOOK.md`, `docs/PRODUCTION_READINESS_CHECKLIST.md`, `docs/DB_SCHEMA.md`, this file.
  - **QA:** `sail artisan lifecycle:prune-notifications` (dry-run) → candidate count; `sail artisan schedule:list` → 02:15/02:20 entries; `./vendor/bin/sail artisan test` + `npm run build`.

- **Lifecycle archive — weekly schedule (rescue-mission):**
  - **Goal:** Run warm-table archival automatically so `audit_logs` and `activities` hot tables stay bounded.
  - **Change:** `routes/console.php` registers `lifecycle:archive-audit-logs` and `lifecycle:archive-activities` with `--execute`, **weekly Sunday 03:00 UTC** (`weeklyOn(0, '3:00')` + `timezone('UTC')`). Manual CLI without `--execute` remains dry-run; archiver still batched (default `--batch=500`), idempotent, supports `--organization=` for scoped manual runs.
  - **Why weekly:** Retention is 90 days; newly eligible rows accrue gradually—weekly spreads load vs daily while preventing unbounded growth.
  - **Tests:** `LifecycleArchiveWarmTablesTest::test_lifecycle_archive_commands_are_registered_in_schedule`; existing archive behavior tests unchanged.
  - **Docs:** `docs/DATA_LIFECYCLE.md`, `docs/ARCHITECTURE_GUARDRAILS.md`, `docs/OBSERVABILITY_AND_RUNBOOK.md`, this file.
  - **QA:** `./vendor/bin/sail artisan schedule:list` shows both archive lines at `0 3 * * 0`; `sail artisan lifecycle:archive-audit-logs` (no flags) still dry-run; `./vendor/bin/sail artisan test` and `npm run build`.

- **Lifecycle Phase 3 — Archive foundation (rescue-mission):**
  - **Goal:** Safe technical foundation to move aged `audit_logs` and `activities` to archive tables; no automatic bulk migration; no archive UI or restore path.
  - **Schema:** `audit_logs_archive`, `activities_archive` — mirror hot columns + `archived_at`; plain `organization_id` (no FK on archive); indexes on org + `created_at` and entity/subject identifiers.
  - **Retention:** Same as `config/lifecycle.php` warm entries — **90 days** on `created_at`; rows strictly older than cutoff are eligible.
  - **Commands:** `lifecycle:archive-audit-logs`, `lifecycle:archive-activities` — dry-run default, `--execute` to move, `--batch=500`, `--organization=` for scoped runs. **Scheduled** weekly Sunday 03:00 UTC with `--execute` (see Last PR Notes above).
  - **Implementation:** `App\Services\Lifecycle\WarmTableArchiver` — batched transactions, idempotent (preserves ids), pre-pass removes hot rows whose id already exists in archive.
  - **Tests:** `LifecycleArchiveWarmTablesTest` (10) — dry-run, execute, fresh rows stay hot, org preserved, no duplicate archives, reconcile, org scope, activities subject fields, schedule registration.
  - **Docs:** DATA_LIFECYCLE.md, PROJECT_STATUS.md, ARCHITECTURE_GUARDRAILS.md, OBSERVABILITY_AND_RUNBOOK.md, DB_SCHEMA.md.
  - **QA checklist:**
    1. `sail artisan migrate` → archive tables exist.
    2. `sail artisan lifecycle:archive-audit-logs` (no flags) → shows cutoff and candidate count; hot DB unchanged.
    3. Staging: `--execute` on a subset (`--organization=`) → rows move to archive; app still reads hot tables only.
    4. Re-run command → no duplicate archive PKs; `lifecycle:report` still reflects hot table counts.
    5. `./vendor/bin/sail artisan test` and `npm run build`.

- **Platform Org Health partial hybrid (rescue-mission):**
  - **Goal:** Use `org_daily_metrics` only where semantics match; no UI or schema change.
  - **Snapshot field:** `seats_active` uses `users_count` from **yesterday’s** row for that org when present (same definition as `OrgMetricsSnapshotService`: tenant users on `organization_user` with `users.client_id` null). **Per-org fallback:** missing row → live seat query for that org only.
  - **Stays live:** Billing status, Stripe link, seat limits/included from subscription, storage usage/limits, webhook stats, health flags derivation (flags still computed from displayed `seats_active` plus live billing/storage/webhook inputs).
  - **Tests:** `OrgHealthDashboardTest` — snapshot vs live seats, per-org isolation on one page, billing/webhooks still live with snapshot seats.
  - **Docs:** PROJECT_STATUS.md, ARCHITECTURE_GUARDRAILS.md, OBSERVABILITY_AND_RUNBOOK.md.
  - **QA checklist:**
    1. With snapshots for yesterday, open Platform → Org Health; `seats_active` matches `users_count` for orgs that have a row (may lag live by up to a day).
    2. Org without a snapshot row for yesterday → `seats_active` matches live membership.
    3. Billing past_due / webhook failures still surface as before.
    4. `./vendor/bin/sail artisan test tests/Feature/Platform/OrgHealthDashboardTest.php` and full suite + `npm run build`.

- **Platform Feature Usage hybrid (rescue-mission):**
  - **Goal:** Use `org_daily_metrics` for platform module adoption aggregates where safe; keep live fallback; no UI change.
  - **Hybrid rule:** Adoption metrics (`adoption.*` per module and `summary.orgs_using_any_module`) read from snapshots for **yesterday** (app timezone) only when snapshot row count for that date equals organization count (full coverage). Otherwise full live adoption queries. **Billing setup** remains 100% live (not in read model).
  - **Mapping:** clients → `clients_count`, projects → `projects_count`, tasks → `tasks_count`, attendance → `attendance_count`, invoices → `invoices_count`. `orgs_with_any` = orgs with count > 0; `total_records` = sum of counts (as-of EOD semantics from `OrgMetricsSnapshotService`).
  - **Tests:** `FeatureUsageDashboardTest` extended — snapshot path when full coverage, live fallback when incomplete, per-org aggregation sanity, Inertia shape unchanged.
  - **Docs:** PROJECT_STATUS.md, ARCHITECTURE_GUARDRAILS.md, OBSERVABILITY_AND_RUNBOOK.md.
  - **QA checklist:**
    1. Run `sail artisan metrics:snapshot-orgs` → open Platform → Feature Usage; adoption should reflect yesterday’s snapshot when all orgs have rows.
    2. New org with no snapshot row for yesterday → adoption falls back to live until the next daily snapshot covers all orgs.
    3. Billing block unchanged (Stripe/subscription/addon counts still live).
    4. `./vendor/bin/sail artisan test tests/Feature/Platform/FeatureUsageDashboardTest.php` and full suite + `npm run build`.

- **Admin Dashboard Hybrid Metrics Migration (rescue-mission):**
  - **Goal:** Migrate tenant admin dashboard to use `org_daily_metrics` read model in a safe hybrid mode. Reduces live query load without changing dashboard UI.
  - **Hybrid rule:** Use `org_daily_metrics` when snapshot data exists for the requested date range; fall back to live queries when snapshots are missing or insufficient. Live-query fallback remains intentionally.
  - **Migration:** `DashboardController::adminDashboard` now prefers snapshots for: total_revenue, outstanding_revenue (KPIs); clients, projects, tasks stats (created-in-range); clients30d, projects30d, tasks30d series (daily deltas). active_projects, monthly_revenue, project_status, workload, activities stay live (not in snapshot or different aggregation).
  - **Coverage logic:** `hasSufficientSnapshotCoverageForKpis` (≥1 row), `hasSufficientSnapshotCoverageForStats` (end_date row), `hasSufficientSnapshotCoverageForSeries` (consecutive rows from start-1 through end).
  - **Tests:** `DashboardHybridMetricsTest` — 4 tests: snapshot-backed values when rows exist, safe fallback when missing, chart series correctness, org isolation.
  - **Docs:** PROJECT_STATUS.md, ARCHITECTURE_GUARDRAILS.md, OBSERVABILITY_AND_RUNBOOK.md updated.
  - **QA checklist:**
    1. Run `sail artisan metrics:snapshot-orgs` for yesterday → visit tenant admin dashboard → KPIs/stats/charts load.
    2. New org with no snapshots → dashboard falls back to live; values correct.
    3. Change date range via DateRangeButton → snapshot used when coverage exists, live when not.
    4. Run `./vendor/bin/sail artisan test tests/Feature/Dashboard/` and `npm run build`.

- **Metrics Snapshot / Read-Model Foundation (rescue-mission):**
  - **Goal:** Foundation for 10M+ record scale: daily org-level metrics read model. No dashboard rewrites.
  - **Schema:** `org_daily_metrics` table with unique `(organization_id, metric_date)`. Columns: clients_count, projects_count, tasks_count, open_tasks_count, attendance_count, activities_count, invoices_count, revenue_cents, outstanding_cents, users_count.
  - **Service:** `OrgMetricsSnapshotService` — idempotent upsert; soft-delete aware; chunks orgs in batches of 100.
  - **Command:** `metrics:snapshot-orgs` — defaults to yesterday; `--date` for specific dates; `--org` for single org. Safe to re-run.
  - **Scheduler:** Daily at 01:00 UTC in `routes/console.php`. Runs after midnight to capture previous day.
  - **Tests:** 10 tests: row creation, upsert idempotency, metric accuracy (clients/projects/tasks/open_tasks/invoices/revenue/outstanding/activities/users), org isolation, soft-delete exclusion, command with/without flags, default date, nonexistent org error.
  - **Docs:** ARCHITECTURE_GUARDRAILS.md (Metrics Read-Model section), OBSERVABILITY_AND_RUNBOOK.md (snapshot commands), PRODUCTION_READINESS_CHECKLIST.md (metrics checklist), PROJECT_STATUS.md.
  - **QA checklist:**
    1. Run `sail artisan migrate` → `org_daily_metrics` table created.
    2. Run `sail artisan metrics:snapshot-orgs` → snapshots all orgs for yesterday.
    3. Run `sail artisan metrics:snapshot-orgs --date=2026-03-18` → specific date.
    4. Re-run same date → upsert; row count unchanged; values updated.
    5. Run `sail artisan metrics:snapshot-orgs --org=1` → single org only.
    6. Run `./vendor/bin/sail artisan test` and `npm run build`.

- **Lifecycle Phase 2: Safe Prune Commands (rescue-mission):**
  - **Goal:** Operator-safe pruning for cold-data targets with dry-run by default and explicit `--execute` for deletion.
  - **New commands:** `lifecycle:prune-webhooks`, `lifecycle:prune-failed`, `lifecycle:prune-batches` — all dry-run by default; `--execute` required for actual deletion.
  - **Targets:** stripe_webhook_events (90d), failed_jobs (30d), job_batches (30d). Uses `config/lifecycle.php` and RetentionPolicy.
  - **Schedule:** Daily at 02:00, 02:05, 02:10 UTC in `routes/console.php`. Ensure `schedule:run` is in cron.
  - **Config:** Added `job_batches` to lifecycle.php; added `age_column_type` => `integer` for job_batches (Laravel uses unix timestamps).
  - **LifecycleReportCommand fix:** Handles integer timestamp columns (job_batches.finished_at) for aged-out count.
  - **Tests:** LifecyclePruneWebhooksTest (5), LifecyclePruneFailedTest (4), LifecyclePruneBatchesTest (3). All pass.
  - **Docs:** DATA_LIFECYCLE.md, OBSERVABILITY_AND_RUNBOOK.md, PRODUCTION_READINESS_CHECKLIST.md updated.
  - **QA checklist:**
    1. Run `sail artisan lifecycle:prune-webhooks` (no --execute) → see candidate count, no rows deleted.
    2. Insert aged webhook event (>90 days) → run with `--execute` → row deleted.
    3. Run `sail artisan lifecycle:prune-failed` and `lifecycle:prune-batches` dry-run and execute.
    4. Verify `sail artisan schedule:list` shows the three prune commands.
    5. Run `./vendor/bin/sail artisan test` and `npm run build`.

- **Data Lifecycle & Retention Guardrails (rescue-mission):**
  - **Goal:** Architecture, docs, and safe groundwork for data lifecycle management. No destructive operations.
  - **New doc:** `docs/DATA_LIFECYCLE.md` — hot-growth table inventory, retention categories (hot/warm/cold), operator rules, future archive/prune candidates, implementation roadmap (3 phases).
  - **Retention config:** `config/lifecycle.php` — per-table retention windows and categories. Advisory only; no destructive job reads these yet.
  - **RetentionPolicy service:** `app/Services/RetentionPolicy.php` — value object for programmatic access to retention config. Cutoff date computation, category helpers (`isHot()`, `isWarm()`, `isCold()`, `hasLifecycleAction()`).
  - **Lifecycle report command:** `php artisan lifecycle:report` — read-only artisan command. Shows row counts, aged-out row counts, and status for all configured tables. Never modifies data.
  - **Doc updates:** ARCHITECTURE_GUARDRAILS.md (new Data Lifecycle section), OBSERVABILITY_AND_RUNBOOK.md (lifecycle reporting section), PRODUCTION_READINESS_CHECKLIST.md (data lifecycle checklist), DB_SCHEMA.md (lifecycle reference table).
  - **Tests:** RetentionPolicyTest (5 unit tests: config parsing, cutoff dates, category helpers, all() loader, defaults). LifecycleReportTest (5 feature tests: runs successfully, shows all tables, detects aged-out rows, shows OK for fresh data, non-destructive).
  - **QA checklist:**
    1. Run `sail artisan lifecycle:report` → see table with audit_logs, activities, stripe_webhook_events, failed_jobs. Status shows OK or candidate counts.
    2. Insert an old audit_log row (>90 days) → re-run report → see ARCHIVE candidates.
    3. Verify `config/lifecycle.php` loads correctly in tinker: `config('lifecycle.tables')`.
    4. Verify `RetentionPolicy::all()` returns expected policies.
    5. Run `./vendor/bin/sail artisan test` and `npm run build`.

- **Custom Fields Phase 2 (rescue-mission):**
  - **Goal:** Stronger validation, client filtering by custom fields, groundwork for reporting/search.
  - **Validation hardening:** Select values must be one of configured options (ValidationException if invalid). Multiselect values must all be within configured options (ValidationException if any invalid). Type-safe validation for text/number/date unchanged.
  - **Client update fix:** ClientController::update now syncs custom_values via CustomFieldValueService::syncForEntity (was previously missing).
  - **Client filtering:** ClientsInertiaController::index supports `cf[slug]=value` query params. Filterable types: text (contains), number (exact), date (exact), select (exact). Tenant-scoped; only known org slugs applied.
  - **Frontend:** Clients Index.vue adds compact custom field filter section when customFields exist. Filters preserve pagination; Reset clears all including cf.
  - **Tests:** CustomFieldTest extended — invalid select rejected; invalid multiselect rejected; client filtering by custom field works; org isolation preserved for cf filter (9 tests).
  - **QA checklist:**
    1. Create select field with options [A,B,C]. Create client with tier=Invalid → validation error, no save.
    2. Create multiselect field. Submit [A, Hacked] → validation error.
    3. Clients index: with custom fields defined, see Custom filter section; filter by tier=Premium → only matching clients.
    4. Reset clears cf filters. Pagination preserves cf in URL.
    5. Org B user, cf[tier]=X → only org B clients; no cross-tenant leakage.
    6. Run `./vendor/bin/sail artisan test` and `npm run build`.

- **Custom Fields System v1 (rescue-mission):**
  - **Goal:** Core extensibility layer for tenant-defined custom fields on entities.
  - **Schema:** `custom_fields` (org, entity, label, slug, type, options, is_required, sort_order), `custom_field_values` (custom_field_id, entity_type, entity_id, value_text/number/date/json). Unique per org+entity+slug; values unique per field+entity.
  - **Models:** CustomField, CustomFieldValue; Client has customFieldValues().
  - **Controllers:** CustomFieldController (index/store/update/destroy); ClientController store/update accept custom_values, CustomFieldValueService::syncForEntity.
  - **Validation:** Type-safe (text, number, date, select, multiselect); org-scoped; required-field checks.
  - **UI:** Settings → Custom Fields (list, add, edit, delete); Clients Create/Edit render dynamic fields via CustomFieldsSection.
  - **Permission:** custom-fields.manage (Owner, Manager, Super Admin).
  - **Tests:** CustomFieldTest — 403 without permission; field creation; value saving on client create; org isolation (404 cross-tenant); index 403.
  - **QA checklist:**
    1. Owner → Settings → Custom Fields → Create field (label, type text/number/date/select/multiselect, options for select).
    2. Create Client → Custom fields section visible when fields exist; values persist.
    3. Edit Client → Custom values pre-filled; save updates values.
    4. User without custom-fields.manage → 403 on Custom Fields page, no tab/link.
    5. Run `./vendor/bin/sail artisan test tests/Feature/CustomFields/` and `npm run build`.

- **Platform System Performance Dashboard (rescue-mission):**
  - **Goal:** Read-only platform operator dashboard surfacing infrastructure readiness, queue health, webhook reliability, storage pressure, and at-risk org counts from existing app/DB signals.
  - **Route:** `GET /admin/system-performance` → `platform.system-performance` (auth:platform).
  - **Controller:** `SystemPerformanceDashboardController::index` — batched read queries for readiness (DB, cache, queue config), failed jobs summary (24h/7d/total + recent 5), webhook health (processed/failed counts, orgs affected, recent 5 failures), storage pressure (orgs near/over limit with progress), at-risk orgs (past_due/unpaid billing + 3+ webhook failures), platform summary (total orgs/users/subscriptions/Stripe-linked).
  - **Metrics:** Readiness: status + per-subsystem check detail. Queue: failed_last_24h, failed_last_7d, total_failed, recent_failures. Webhooks: total_events, processed_count, failed_count, failed_last_24h/7d, orgs_with_failures_7d, recent_failures. Storage: orgs_over_limit, orgs_near_limit, total_storage_used_gb, top-10 pressure details. At-risk: billing_at_risk, webhook_at_risk, past_due_count, unpaid_count, orgs_3plus_webhook_failures.
  - **Frontend:** `Platform/SystemPerformance/Index.vue` — summary cards (system status, failed jobs 24h, webhook failures 7d, at-risk orgs), infrastructure readiness detail, queue health detail with recent failures, webhook reliability with recent failure table, storage pressure with per-org progress bars, operational risk summary, drilldown links to Org Health / Org Subscriptions / Revenue / Feature Usage.
  - **Nav:** "System Performance" added to PlatformLayout sidebar after Feature Usage and before Settings.
  - **Tests:** `SystemPerformanceDashboardTest` — 13 tests: access control (platform admin, tenant user, guest), readiness healthy + driver info, queue health counts, webhook health counts + 24h filtering, at-risk billing/webhook counts, storage zero-state, platform summary counts, full zero-state (no orgs), queue zero-state.
  - **Docs:** PROJECT_STATUS.md, OBSERVABILITY_AND_RUNBOOK.md, PRODUCTION_READINESS_CHECKLIST.md updated.
  - **QA checklist:**
    1. Platform admin → `/admin/system-performance` → summary cards, readiness detail, queue health, webhook health, storage pressure, risk summary visible.
    2. System status card shows green "healthy" when DB/cache/queue all OK.
    3. Create failed_jobs entries → failed jobs 24h/7d counts update; recent failures list populated.
    4. Create failed webhook events → webhook failures 7d count updates; recent failure table populated; orgs affected count correct.
    5. Zero state: no orgs, no failed jobs, no webhooks → all zeros, clean UI.
    6. Nav sidebar: "System Performance" link after Feature Usage.
    7. Drilldown links: Org Health, Org Subscriptions, Revenue, Feature Usage navigate correctly.
    8. Tenant user / guest → redirected to login.
    9. Run `./vendor/bin/sail artisan test` and `./vendor/bin/sail npm run build`.

- **Platform Feature Usage Dashboard (rescue-mission):**
  - **Goal:** Read-only platform operator dashboard surfacing module adoption and feature usage metrics derived from local DB.
  - **Route:** `GET /admin/feature-usage` → `platform.feature-usage` (auth:platform).
  - **Controller:** `FeatureUsageDashboardController::index` — batched queries for module adoption (clients, projects, tasks, attendance, invoices), billing setup metrics (Stripe-linked, subscriptions, active add-ons), and summary stats (orgs using any module, avg modules/org).
  - **Metrics:** Per-module: orgs_with_any, total_records, label. Billing: stripe_linked, has_subscription, has_active_subscription, has_active_addons. Summary: total_orgs, orgs_using_any_module, avg_modules_per_org, modules_tracked.
  - **Frontend:** `Platform/FeatureUsage/Index.vue` — summary cards, module adoption bar chart with per-module icons/colors, billing setup breakdown, detailed adoption table (orgs using, %, total records, avg/org), data methodology notes, drilldown links to Org Health, Org Subscriptions, Revenue.
  - **Nav:** "Feature Usage" added to PlatformLayout sidebar between Org Health and Settings.
  - **Tests:** `FeatureUsageDashboardTest` — 13 tests: access control (platform admin, tenant user, guest), client/project/task/attendance adoption counts, soft-deleted exclusion, billing setup metrics, summary orgs-using-any, zero-state (no orgs, orgs with no usage), modules tracked count, adoption labels.
  - **Docs:** PROJECT_STATUS.md, OBSERVABILITY_AND_RUNBOOK.md, PRODUCTION_READINESS_CHECKLIST.md updated.
  - **QA checklist:**
    1. Platform admin → `/admin/feature-usage` → summary cards, adoption bars, billing setup, adoption table visible.
    2. Create orgs with clients/projects/tasks → adoption counts reflect correctly.
    3. Soft-deleted records excluded from counts.
    4. Zero state: no orgs → all zeros, clean UI.
    5. Nav sidebar: "Feature Usage" link between Org Health and Settings.
    6. Drilldown links: Org Health, Org Subscriptions, Revenue navigate correctly.
    7. Tenant user / guest → redirected to login.
    8. Run `./vendor/bin/sail artisan test` and `./vendor/bin/sail npm run build`.

- **Clients Pipeline UX + performance polish (rescue-mission):**
  - **Goal:** Improve pipeline responsiveness, usability, and enterprise feel without redesigning the module.
  - **Performance:** Replaced repeated `byCol()` filter calls (12 full-array passes per render) with a single computed `clientsByStatus` map — O(n) grouping once, O(1) column lookups. Eliminates redundant recomputation on every render cycle.
  - **Backend:** Added `q` search param to `ClientsPipelineController::index`; filters by `company_name` via `ilike`. Returns `filters.q` prop for frontend hydration. Pagination via `withQueryString()` preserves search across pages.
  - **Frontend (Pipeline.vue rewrite):**
    - Uses `PageShell` for consistent layout with all other CRM pages.
    - Added `inactive` column (was missing despite being a valid backend status).
    - Color-coded status badges per column (lead=sky, active=emerald, inactive=zinc, paused=amber, churned=rose).
    - Debounced search input (300ms) with clear button.
    - Optimistic drag-drop: card moves immediately to target column; reverts on server error.
    - Drag-over visual feedback (column highlight).
    - Dragged card gets opacity treatment.
    - Pagination uses `router.get` buttons (not `<Link>` to avoid full page replace).
    - Empty-state messages per column.
    - Total client count in subtitle.
  - **Tests:** Extended ClientsPipelinePaginationTest (now 9 tests): search filters by company name, search preserves query in pagination links, status update works, invalid status rejected, empty search returns all, filters prop returned.
  - **QA checklist:**
    1. Pipeline page: type in search → results filter after 300ms; clear button resets.
    2. All 5 columns visible (Lead, Active, Inactive, Paused, Churned) with color badges and counts.
    3. Drag card from Lead to Active → card moves immediately; server persists; page refresh shows new status.
    4. Drag card to same column → no request fired.
    5. Column highlights on drag-over; dragged card shows reduced opacity.
    6. Paginate with search → page 2 preserves `q=` in URL.
    7. PageShell header with breadcrumb, title, subtitle, search, List view button.
    8. Run `./vendor/bin/sail artisan test` and `./vendor/bin/sail npm run build`.

- **Attendance history filters (rescue-mission):**
  - **Goal:** Add practical attendance history filters to improve day-to-day usability and review workflows.
  - **Backend:** AttendanceController::index now validates query params: date_from, date_to (nullable|date), user_id (nullable|integer, org-scoped when canViewAll; rejected when view-own tries another user), status (open|closed|approved), approved (yes|no). Applied filters: date range on clock_in_at, status, approved (approved_at IS [NOT] NULL). Tenant scoping preserved; pagination uses withQueryString() so filters persist.
  - **Frontend:** Attendance/Index.vue adds approved dropdown (Any, Approved, Not approved); Apply and Clear buttons; all filters wired to query params. User dropdown only when canViewAll.
  - **Tests:** AttendanceFiltersTest — date range, status, approved filters work; user filter only for canViewAll; view-own user gets 302+validation error on crafted user_id; query string persists across pagination; invalid status/approved return 302.
  - **QA checklist:**
    1. Attendance page: set date from/to, status, approved → Apply → filtered results.
    2. Clear → all filters reset, full history (or own for view-own).
    3. User with attendance.view: user dropdown visible; filter by another user → see that user's records.
    4. User with attendance.view-own only: no user dropdown; craft ?user_id=999 → redirect with validation error.
    5. Paginate with status=closed → pagination links preserve status=closed.
    6. Run `./vendor/bin/sail artisan test` and `./vendor/bin/sail npm run build`.

- **Role cloning in tenant Role Maker (rescue-mission):**
  - **Backend:** `POST /org/{organization}/settings/roles/{role}/clone` — clones team-scoped roles only. Creates new role with name `{original}-copy` (or `-copy-2`, `-copy-3` if exists). Copies all permissions from source. Global roles cannot be cloned; cross-tenant clone blocked. Audit: `cloned` action with source/new role ids and names.
  - **Frontend:** "Clone role" button for editable team-scoped roles in Roles.vue; after clone, new role is auto-selected (uses `created_role_id` flash). Success feedback via flash message.
  - **Tests:** RoleCloneTest — tenant admin can clone, cloned role gets same permissions, unique name with suffix when copy exists, cannot clone global role, cross-tenant blocked, user without roles.manage gets 403.
  - **QA checklist:**
    1. Settings → Roles → select team-scoped role → click "Clone role" → new role appears with "-copy" suffix, same permissions, auto-selected.
    2. Clone same role again → second clone named "-copy-2".
    3. Select global role (Owner, Manager, etc.) → "Clone role" button not shown.
    4. Run `./vendor/bin/sail artisan test` and `./vendor/bin/sail npm run build`.

- **Scale pass three: dropdown bounds, eager load limits, N+1 fix, consolidated revenue query, storage cache (rescue-mission):**
  - **Dropdown/filter bounds:** Replaced unbounded `->get()` with `->limit(200)->get()` for clients, users, projects dropdowns in ProjectController (index/create/edit/show), TaskController (index projects), InvoiceController (create clients/projects), AttendanceController (user filter), ActivityController (user filter), ClientsInertiaController (assignment users).
  - **Project show eager load limits:** Tasks, files, comments, activities now use `->limit(200)` / `->limit(100)` in ProjectController::show to bound memory on heavy projects.
  - **Clients show N+1 fix:** Replaced per-contact `User::where('email', ...)->exists()` with batched lookup: collect contact emails, single `User::whereIn('email', ...)->where('client_id', ...)->pluck('email')`, map in memory.
  - **Monthly revenue consolidation:** DashboardController::monthlyRevenueSeries now uses single grouped query with `date_trunc('month', paid_at)` instead of 6 separate sum queries.
  - **StorageUsageService per-request cache:** `currentUsageBytes()` caches result keyed by organization_id for the request lifetime; repeated calls (from isOverLimit, wouldExceedLimit, currentUsageGb) avoid redundant SUM queries.
  - **Tests:** ScalePassThirdTest (project show bounded, clients portal access batch, monthly revenue format, dropdowns bounded), StorageUsageServiceTest extended (repeated calls consistent).
  - **QA checklist:**
    1. Projects index/create/edit/show: clients and users dropdowns load; Tasks index: projects filter loads; Invoices create: clients/projects load; Attendance/Activity: user filter loads.
    2. Project show with many tasks/files/comments: page loads; only up to limits shown.
    3. Client show with multiple contacts: has_portal_access correct for portal vs non-portal contacts.
    4. Dashboard monthly revenue chart: 6 months shown; values match paid invoices.
    5. Run `./vendor/bin/sail artisan test` and `./vendor/bin/sail npm run build`.

- **Scale pass two: indexes, pipeline pagination, portal bounding, ResolveTenant cleanup (rescue-mission):**
  - **Indexes:** Added migration with 6 composite indexes: `idx_projects_org_status`, `idx_projects_org_pm`, `idx_project_files_org_project`, `idx_stripe_webhook_events_org`, `idx_clients_org_status`, `idx_activities_subject`.
  - **Clients Pipeline:** Replaced unbounded `->get()` with `paginate(50)->withQueryString()`; Pipeline.vue now consumes `clients.data`/`clients.links` and renders pagination controls.
  - **Portal Dashboard:** Replaced unbounded project load with `limit(50)` to bound memory for clients with many projects (low-risk; no pagination UI).
  - **ResolveTenant:** Removed per-request `Schema::hasColumn('users', 'active_organization_id')` check; column is canonical.
  - **Tests:** ClientsPipelinePaginationTest (paginated, query string, visibility), PortalDashboardProjectsBoundedTest, ResolveTenantActiveOrgTest.
  - **QA checklist:**
    1. Clients Pipeline with >50 clients: 50 per page, pagination links work, filters preserved.
    2. Portal dashboard with 60+ projects: only 50 rendered.
    3. Switch org via nav: user's active_organization_id updates.
    4. Run `./vendor/bin/sail artisan test` and `./vendor/bin/sail npm run build`.

- **Task board scale hardening + assignee index (rescue-mission):**
  - **Goal:** Apply highest-priority scale fixes from audit for task visibility and task board loading.
  - **Indexes:** Added PostgreSQL GIN index `tasks_assignees_gin_idx` on `tasks.assignees` (`jsonb_path_ops`) to accelerate `whereJsonContains('assignees', ...)` visibility checks; added composite index `tasks_organization_id_status_idx` on `(organization_id, status)` for tenant/status board access patterns.
  - **Task board pagination:** `TaskBoardController::index` now uses `paginate(50)->withQueryString()` (previously unbounded `->get()`), preserving existing tenant scope, `visibleTo`, eager loads, and ordering.
  - **Frontend:** `Tasks/Board.vue` now reads paginated props (`tasks.data`, `tasks.links`) and renders pagination links using existing app pagination style.
  - **Tests:** `TaskPermissionTest` extended to verify board pagination count (`50` per page), total (`tasks.total`), visibility enforcement remains intact, and query strings are preserved in pagination links.
  - **QA checklist:**
    1. Open `/org/{org}/tasks/board` with >50 visible tasks; confirm only 50 render per page.
    2. Navigate board pagination links; confirm links keep existing query string parameters.
    3. Verify non-admin user only sees tasks permitted by `visibleTo` rules.
    4. Run `./vendor/bin/sail artisan test` and `./vendor/bin/sail npm run build`.

- **Project files tenant isolation hardening (rescue-mission):**
  - **Goal:** Make `project_files` tenant-safe at schema level and enforce direct org scoping on file paths.
  - **Schema:** Added migration to introduce `project_files.organization_id`, backfill from `projects.organization_id`, enforce NOT NULL, index `organization_id`, and add FK to `organizations`.
  - **Write path:** `ProjectFileController::store` now writes `organization_id` from resolved tenant route context.
  - **Read/mutate paths:** `ProjectFileController` now resolves files by `id + organization_id + project_id`; portal file list/download also enforce direct `organization_id` matching.
  - **Billing usage read-model:** `StorageUsageService` now scopes storage sums directly by `project_files.organization_id` (no project join required).
  - **Tests:** Added `ProjectFileTenantIsolationTest` (org_id set on create, cross-tenant mismatched file blocked, same-tenant download/toggle/delete still work); updated storage quota/usage tests to include `organization_id` in direct `project_files` inserts.
  - **QA checklist:**
    1. Upload file to `/org/{org}/projects/{project}` and verify `project_files.organization_id` equals org id.
    2. Attempt file download with mismatched `organization_id` row -> request returns 404.
    3. Same-tenant file download/toggle/delete continues working.
    4. Run `./vendor/bin/sail artisan test` and `./vendor/bin/sail npm run build`.

- **HRM security & onboarding reliability (rescue-mission):**
  - **Goal:** Remove weak default password behavior and eliminate silent role-assignment failures in employee onboarding flows.
  - **Password security:** `HRMController::store()` no longer uses `Hash::make('password')`. It now sets a strong random password server-side and triggers password reset link delivery for safe first-time credential setup.
  - **No password leakage:** Success messaging no longer exposes raw credentials.
  - **Reliability:** `store()` and `update()` now run profile changes + role assignment in a transaction, so role sync failures roll back the operation.
  - **Failure visibility:** Role-assignment failures are logged with support context (`organization_id`, `user_id`, attempted role, exception class/message) and return safe user-facing errors.
  - **Tests:** Added `HRMSecurityReliabilityTest` covering no literal default password, no password leak in flash, role assignment failure rollback on create, and rollback on update.
  - **QA checklist:**
    1. Create employee via HRM → success message contains no raw password.
    2. Trigger role assignment failure (e.g., temporary permission-layer fault) → operation fails with safe error and no half-created/half-updated user.
    3. Create employee and verify first-time credential setup relies on password reset link flow.

- **Form alignment: Clients & Projects (rescue-mission):**
  - **Goal:** Fix broken forms and align validation across Clients and Projects.
  - **A) CSV Import:** Vue `Import.vue` now uses `file` (not `csv`) to match controller; `ClientsImportController` validates `file` (required, csv/txt, max 10MB); controller accepts `Organization` from route model binding.
  - **B) QuickCreate:** Added `primary_contact_name` and `primary_contact_email` (required by ClientController::store); form submits successfully.
  - **C) Projects Create:** Create.vue sends `status`, `description`, `due_date`, `user_ids`; removed `project_manager_id` (controller maps `user_ids[0]` to project_manager_id).
  - **D) Project status enum:** `PROJECT_STATUS_VALUES` constant in ProjectController; store(), update(), updateStatus() all use `Rule::in()`; Create.vue and Edit.vue use same options (Planned, Active, In Progress, Blocked, Completed, On Hold, Cancelled).
  - **E) Client pipeline:** `ClientsPipelineController::update` now uses `Rule::in(['lead','active','inactive','paused','churned'])` instead of `string|max:50`.
  - **Tests:** `FormAlignmentTest` — CSV import, QuickCreate, project create, invalid project status rejected, invalid client pipeline status rejected.
  - **QA checklist:**
    1. Clients → Import → upload CSV with `file` field → success.
    2. QuickCreate modal → fill company, primary contact name, primary contact email → Create Client succeeds.
    3. Projects → Create → enter title, status, description, due date, team members → Create project succeeds.
    4. Project create with invalid status (e.g. "Invalid") → validation error.
    5. Client Pipeline drag-and-drop with invalid status → validation error.
    6. Run `./vendor/bin/sail artisan test` and `./vendor/bin/sail npm run build`.

- **Attendance data integrity (rescue-mission):**
  - **Goal:** Fix race condition double clock-in, timezone mismatch for "today", and approval of open records.
  - **Double clock-in:** Migration adds partial unique index on `(organization_id, user_id) WHERE clock_out_at IS NULL` (PostgreSQL). `clockIn()` wrapped in transaction; catches unique constraint violation and returns user-friendly error.
  - **Timezone:** "Today" and per-day record checks now use `Carbon::now($organization->timezone)`. `hasTodayRecord` uses timezone-aware date comparison.
  - **Approve validation:** `approve()` requires `clock_out_at IS NOT NULL` and `status === 'closed'`; otherwise throws `ValidationException` with clear message.
  - **Defense-in-depth:** `AttendancePolicy::approve()` now checks `attendance.organization_id === user.active_organization_id`.
  - **UX:** Clock in/out buttons use `isClocking` flag; disabled during request to prevent double-submit.
  - **Tests:** `AttendanceIntegrityTest` — double clock-in prevented (app + DB when pgsql), timezone for today check, open cannot be approved, closed can be approved.
  - **QA checklist:**
    1. Clock in → immediately click again → button disabled, no duplicate record.
    2. Org with timezone `America/New_York` → "today" and per-day logic use NY date.
    3. Open record (no clock-out) → Approve button hidden in UI; direct POST approve → validation error.
    4. Closed record → Approve succeeds.
    5. Run `./vendor/bin/sail artisan test` and `./vendor/bin/sail npm run build`.

- **Global role protection (rescue-mission):**
  - **Goal:** Prevent tenants from modifying permissions on global roles (Owner, Manager, Employee, Client, Super Admin). These roles have `team_id = null` and are system-managed.
  - **Backend:** `UpdateRolePermissionsRequest` and `UpdateRolePermissionsMatrixRequest` now reject any role with `team_id === null` with 403 "Global roles cannot be modified." Super Admin protection fixed to match seeded role name `'Super Admin'`.
  - **Controller:** Roles payload includes `is_editable: role.team_id !== null` for frontend use.
  - **Frontend:** When a global role is selected, permission matrix is disabled (read-only), Save button hidden, and banner shows "Global roles are system-managed and cannot be modified." Edit/Delete remain hidden for global roles.
  - **Tests:** `GlobalRoleProtectionTest` — tenant cannot modify global role via save or matrix update (403); tenant can modify tenant-scoped roles; roles index includes is_editable flag.
  - **QA checklist:**
    1. Log in as Owner → Settings → Roles → select Owner/Manager/Employee/Client/Super Admin → see banner, matrix disabled, no Save bar.
    2. Select a team-scoped role → matrix editable, Save/Discard appear when changes made.
    3. Attempt POST roles.save with global role_id (e.g. via DevTools) → 403.
    4. Run `./vendor/bin/sail artisan test` and `./vendor/bin/sail npm run build`.

- **Cross-tenant validation hardening (rescue-mission):**
  - **Goal:** Close remaining org-scoping validation gaps in Clients, Projects, and HRM.
  - **Implemented:** `ClientController` now validates `fronter_id` / `closer_id` / `assigned_account_manager_id` against `organization_user` membership for the current org. `ProjectController::update` now validates `client_id` with org-scoped `Rule::exists`. `HRMController::update` now validates `department_id` with org-scoped `Rule::exists`.
  - **Role assignment safety:** HRM role resolution is now scoped to current team roles plus explicitly allowed global roles (`Owner`, `Manager`, `Employee`, `Client`) to prevent accidental cross-tenant role lookup.
  - **Supportability:** `ClientsInertiaController` assignment dropdown now resolves users from `organization_user` membership instead of `active_organization_id`, so valid multi-org members are included.
  - **Tests:** `TenantIsolationHardeningTest` extended for cross-tenant assignment rejection (client user, project client, HRM department) and dropdown membership coverage.

- **HRM module RBAC enforcement (rescue-mission):**
  - **Goal:** Harden HRM with clear RBAC enforcement and workflow safety, aligned with Granular Role Maker.
  - **Permissions:** hrm.view (index), hrm.create (store), hrm.edit (update), hrm.delete (destroy), hrm.manage (all). UserPolicy also accepts users.* as fallback.
  - **UserPolicy:** viewAny, view, create, update, delete, assignRoles — now accept both hrm.* and users.*.
  - **Controller:** Pass canCreate, canEdit, canDelete; scopeBindings on update/destroy; department_id validated against org.
  - **UI:** Add Employee hidden when !canCreate; Edit/Delete hidden when lacking permission; Nav/Command Palette gated by canViewHrm.
  - **Tests:** HRMPermissionTest — view with hrm/view with users, no view 403, create can create, no create 403, no edit 403, edit can update, no delete 403, delete can remove, self-remove 403, cross-tenant 404.
  - **QA checklist:**
    1. User with only hrm.view → list OK; Add/Edit/Delete hidden; direct POST store → 403.
    2. User with hrm.create → Add Employee visible, can create.
    3. User without hrm.edit → Edit hidden; PUT update → 403.
    4. User without hrm.delete → Delete hidden; DELETE → 403.
    5. Run `./vendor/bin/sail artisan test` and `./vendor/bin/sail npm run build`.

- **Attendance module RBAC enforcement (rescue-mission):**
  - **Goal:** Harden Attendance module with clear RBAC enforcement and workflow safety.
  - **Permissions:** attendance.view (index/history), attendance.create (clock in/out), attendance.edit (update status/notes), attendance.delete (destroy), attendance.manage (approve, bulk). Legacy: attendance.view-own, attendance.clock-in, attendance.clock-out, attendance.approve.
  - **AttendancePolicy:** viewAny, view, clockIn, clockOut, approve, update, delete — permission-based; is_super_admin bypass; attendance.create OR clock-in/clock-out for create path; attendance.edit OR manage for update; attendance.delete OR manage for delete.
  - **Workflow:** Normal employee can only create/update own via clock in/out; HR/admin with attendance.edit or manage can edit others; users with only view-own cannot filter by other users.
  - **Controller:** index passes canCreate, canEdit, canDelete, canManage, canViewAll; user filter restricted when !canViewAll; destroy() added with scopeBindings.
  - **UI:** Clock in/out hidden when !canCreate; Edit/Approve/Delete hidden by permission; user filter hidden when !canViewAll.
  - **Tests:** AttendancePermissionTest — view can list, view-own can list, no view 403, create can clock in, clock-in/out backward compat, no create 403 on clock in/out, normal user cannot edit others, HR with edit can edit others, no delete 403, HR with manage can delete, cross-tenant 404.
  - **QA checklist:**
    1. User with only attendance.view-own → own records only; no user filter; clock in/out if has create/clock-in/clock-out.
    2. User with attendance.view → user filter visible; can filter by any org user.
    3. User without attendance.create → clock in/out hidden; POST clockIn/clockOut → 403.
    4. User without attendance.edit → Edit hidden; PATCH update → 403.
    5. User with attendance.manage → Approve visible on closed records; Delete visible.
    6. Run `./vendor/bin/sail artisan test` and `./vendor/bin/sail npm run build`.

- **Tasks module RBAC enforcement (rescue-mission):**
  - **Goal:** Enforce permission checks for Tasks CRUD using Granular Role Maker permissions.
  - **Permissions:** tasks.view (list/board/show), tasks.create (store), tasks.edit (update/move/submit/review), tasks.delete (destroy), tasks.manage (bulk/special), tasks.review (review submitted tasks).
  - **TaskPolicy:** viewAny, view, create, update, delete, submit, review, manage — permission-based; is_super_admin bypass; tasks.edit or tasks.update for update/submit; tasks.review for review.
  - **UI:** Index passes canCreate; Projects/Show passes canCreateTask; TaskDrawer gets can_update/can_submit/can_review/can_delete from API; Board passes can_update per task.
  - **Sub-actions:** submit and review require tasks.edit/tasks.update or tasks.review; move/status via update.
  - **Tests:** TaskPermissionTest — view can list/board, no view 403, create can create, no edit 403 on update/submit/review, no delete 403 on destroy, no view 403 on show, no create 403 on store.
  - **QA checklist:**
    1. User with only tasks.view → list/board OK; New Task, quick-add hidden; direct POST store → 403.
    2. User with tasks.create → New Task visible, quick-add on Projects/Show.
    3. User without tasks.edit → Update/move/submit/review disabled in drawer/board; PUT update, POST submit/review → 403.
    4. User without tasks.delete → Delete hidden in drawer; DELETE → 403.
    5. Run `./vendor/bin/sail artisan test` and `./vendor/bin/sail npm run build`.

- **Projects module RBAC enforcement (rescue-mission):**
  - **Goal:** Enforce permission checks for Projects CRUD using Granular Role Maker permissions.
  - **Permissions:** projects.view (list/show/board/calendar), projects.create (create/store), projects.edit (edit/update/files/status), projects.delete (destroy), projects.manage (bulk/special).
  - **ProjectPolicy:** viewAny, view, create, update, delete, manage — permission-based; is_super_admin bypass; projects.edit or projects.update for update.
  - **UI:** Index passes canCreate; Show passes canEdit, canDelete, canManage; New Project/Edit/Delete/file actions hidden when lacking permission.
  - **Sub-actions:** Board, Calendar use viewAny; file upload/delete/toggle require update; comments require view; pipeline status update requires update.
  - **Tests:** ProjectPermissionTest — view can list/open, no view 403, create can create, no edit 403 on update, no delete 403 on destroy, no view 403 on show, no create 403 on store, no edit 403 on pipeline update.
  - **QA checklist:**
    1. User with only projects.view → list/board/calendar OK; New Project hidden; direct POST store → 403.
    2. User with projects.create → New Project visible, can create.
    3. User without projects.edit → Edit/Delete/file actions hidden on Show; PUT update → 403; pipeline status update → 403.
    4. User without projects.delete → Delete hidden; DELETE → 403.
    5. Run `./vendor/bin/sail artisan test` and `./vendor/bin/sail npm run build`.

- **Clients module RBAC enforcement (rescue-mission):**
  - **Goal:** Enforce permission checks for Clients CRUD using Granular Role Maker roles.
  - **Permissions:** clients.view (list/show), clients.create (create/store), clients.edit (edit/update), clients.delete (destroy), clients.manage/clients.export (bulk export).
  - **ClientPolicy:** viewAny, view, create, update, delete — permission-based; is_super_admin bypass; clients.edit or clients.update for update.
  - **UI:** Index passes canCreate, canImport, canExport; Show/Edit pass canEdit, canDelete; buttons hidden when lacking permission.
  - **Export:** ClientController::exportCsv added (clients.export route); requires clients.export or clients.manage.
  - **Tests:** ClientPermissionTest — view can list, no permission 403, create can create, no edit 403 on update, no delete 403 on destroy.
  - **QA checklist:**
    1. User with only clients.view → list OK, Create/Import/Export hidden; direct POST create → 403.
    2. User with clients.create → New Client visible, can create.
    3. User without clients.edit → Edit/Delete hidden on Show; PUT update → 403.
    4. User without clients.delete → Delete hidden; DELETE → 403.
    5. Run `./vendor/bin/sail artisan test` and `./vendor/bin/sail npm run build`.

- **Granular Role Maker UI (rescue-mission):**
  - **Goal:** Tenant admins can manage roles and permissions via a full CRUD UI.
  - **Implementation:** Role list with permission count; create/edit/delete roles; permission matrix with module groups (Clients, Projects, Tasks, Attendance, Announcements, Notifications, Activity, Settings, Billing, etc.); "Select All" per module; validation for unique name, no delete if assigned.
  - **Backend:** `RolePermissionController` — store, updateRole (PATCH), destroy (DELETE), save (permissions); uses Spatie `syncPermissions()`, `team_id` = `organization_id`; `UpdateRoleRequest`, `DestroyRoleRequest` for validation.
  - **Frontend:** `Roles.vue` — Edit name modal, Delete confirm modal, permission count in list; `PermissionMatrix.vue` — toggleModuleAll (Select All / Deselect All) per module.
  - **Tests:** `RoleManagementTest` — tenant admin create/edit permissions/edit name/delete; role cannot be deleted if assigned; permissions scoped to org team; 403 without roles.manage.
  - **Docs:** PROJECT_STATUS.md, ARCHITECTURE_GUARDRAILS.md updated.
  - **QA checklist:**
    1. Log in as Owner → Settings → Roles → see role list with permission counts.
    2. Create role → name + Save → select permissions in matrix → Save → role reflects changes.
    3. Edit role name (team-scoped only) → Save → name updates.
    4. Delete role (not assigned) → confirm → role removed.
    5. Try delete role assigned to user → blocked with message.
    6. Run `./vendor/bin/sail artisan test` and `./vendor/bin/sail npm run build`.

- **Storage quota enforcement for file uploads (rescue-mission):**
  - **Goal:** Enforce `storage_gb` entitlement when users upload project files. Uses existing `StorageUsageService`.
  - **Implementation:** `StorageUsageService::wouldExceedLimit(Organization $org, int $additionalBytes)` added for pre-upload validation. `ProjectFileController::store` checks before accepting upload; if `currentUsageBytes + fileSize` exceeds limit, returns 422 with "Storage limit reached for your plan."
  - **Tests:** `StorageQuotaEnforcementTest` — upload allowed under quota, upload blocked over quota, org with larger storage_gb can upload when smaller org cannot.
  - **Docs:** ENTITLEMENTS_AND_BILLING.md, PRODUCTION_READINESS_CHECKLIST.md, PROJECT_STATUS.md updated.
  - **QA checklist:**
    1. Org with 5GB limit (starter), no files → upload 1MB → success.
    2. Org with 1GB limit, 1GB existing files → upload 1 byte → 422 "Storage limit reached for your plan."
    3. Org with 50GB limit (pro) → upload 5MB → success when smaller org at limit would block.
    4. Run `./vendor/bin/sail artisan test` and `./vendor/bin/sail npm run build`.

- **Platform-admin webhook/billing support tooling (rescue-mission):**
  - **Goal:** READ-ONLY operational billing/webhook support tools for platform admins. No webhook replay, no Stripe mutations, no tenant-side UI changes.
  - **Schema:** Added `organization_id` (nullable) to `stripe_webhook_events`; `WebhookController` stores org when resolvable from payload.
  - **Platform Org Subscriptions:** New Webhook column shows: status badge (OK/Failed/Received), last processed timestamp (relative), recent failed count (7 days). Support links: View → platform org detail; Billing → tenant billing page (opens new tab; customer must log in).
  - **Tests:** `OrgSubscriptionsOverviewTest` — platform admin sees webhook indicators, recent failed count, fallback when no webhook events, tenant/guest blocked.
  - **Docs:** OBSERVABILITY_AND_RUNBOOK.md, PROJECT_STATUS.md, PRODUCTION_READINESS_CHECKLIST.md updated.
  - **QA checklist:**
    1. Platform admin → Org Subscriptions → table shows Webhook column with status, timestamp, failed count.
    2. Org with processed webhooks → "OK" badge, relative time.
    3. Org with failed webhooks in last 7 days → "Failed" or "N failed" badge.
    4. Org with no webhook events → "—" for webhook column.
    5. Billing link (Stripe-linked orgs only) opens `/org/{slug}/billing` in new tab.
    6. Run `./vendor/bin/sail artisan test` and `./vendor/bin/sail npm run build`.

- **Platform-admin manual billing overrides (rescue-mission):**
  - **Goal:** Internal operator control-plane for Super Admin/Support to override billing state without mutating Stripe.
  - **Routes:** PATCH `/admin/organizations/{organization}/subscription`, POST/PATCH/DELETE `/admin/organizations/{organization}/addons[/{addon}]` — platform-only (`auth:platform`), scopeBindings.
  - **Subscription overrides:** plan_key (PlanCatalog), status, seat_limit (nullable). Upserts `organization_subscriptions`; `organizations.plan` untouched.
  - **Add-on overrides:** Create/update/deactivate via validated addon_key (FeatureCatalog), mode (augment|set), quantity, value_int, active, dates. Deactivation = `active=false`.
  - **Audit:** `platform_subscription_override`, `platform_addon_created`, `platform_addon_updated`, `platform_addon_deactivated` in audit_logs.
  - **Frontend:** SubscriptionsIndex.vue — Override modal (plan selector, seat_limit, add-on create/edit/deactivate). Compact controls, success/error feedback.
  - **Tests:** PlatformBillingOverridesTest — 13 tests (plan override, seat limit set/clear, addon CRUD, tenant blocked, upsert, entitlements, org scoping, audit).
  - **Docs:** OBSERVABILITY_AND_RUNBOOK.md, ENTITLEMENTS_AND_BILLING.md, PRODUCTION_READINESS_CHECKLIST.md updated.
  - **QA checklist:**
    1. Platform admin → Org Subscriptions → Override → change plan/seat_limit/addons → changes reflect in table and tenant billing.
    2. Tenant user cannot PATCH/POST/DELETE platform override routes (403).
    3. Cross-org: cannot mutate another org's addon (404).
    4. Audit logs show platform_subscription_override, platform_addon_* actions.
    5. Run `./vendor/bin/sail artisan test` and `./vendor/bin/sail npm run build`.

- **Platform Admin org subscriptions overview (rescue-mission):**
  - **Route:** `GET /admin/organizations/subscriptions` — platform admin only (`auth:platform`).
  - **Controller:** `SubscriptionsController::index` — paginated read-only billing overview with search, status, plan filters.
  - **Data:** organization name/slug, `has_stripe_id`, canonical `plan_key`/`status` from `organization_subscriptions` (fallback to `org.plan` when no subscription row), seats included/limit/active count, add-ons summary, key entitlements (api_rpm, storage_gb, exports_per_day).
  - **Frontend:** `Platform/Organizations/SubscriptionsIndex.vue` — table with filters, Card layout, link to org show. Nav: "Org Subscriptions" in PlatformLayout sidebar.
  - **Tests:** `OrgSubscriptionsOverviewTest` — platform admin access, tenant/guest blocked, canonical billing data, fallback when no subscription, pagination, search, status filter, addons/entitlements.
  - **Files changed:** `app/Http/Controllers/Platform/Organizations/SubscriptionsController.php` (new), `routes/platform.php`, `resources/js/Pages/Platform/Organizations/SubscriptionsIndex.vue` (new), `resources/js/Layouts/PlatformLayout.vue`, `tests/Feature/Platform/OrgSubscriptionsOverviewTest.php` (new), `docs/PROJECT_STATUS.md`, `docs/OBSERVABILITY_AND_RUNBOOK.md`, `docs/PRODUCTION_READINESS_CHECKLIST.md`.
  - **QA checklist:**
    1. Log in as platform admin → open Org Subscriptions → table shows all orgs with billing data.
    2. Search by name/slug, filter by status/plan → results update.
    3. Org with no subscription row → shows plan from org.plan, status "none".
    4. Org with addons → addons summary and entitlements displayed.
    5. Run `./vendor/bin/sail artisan test` and `./vendor/bin/sail npm run build`.

- **Production observability & runbook layer (rescue-mission):**
  - **Readiness endpoint:** Added `GET /_readiness` (no auth) returning compact JSON with DB connectivity, cache availability, and queue config validation. Returns 200 `healthy` or 503 `degraded`. Designed for load-balancer probes; protected via reverse-proxy in production.
  - **Webhook observability hardened:** `WebhookController` failure logs upgraded from `Log::warning` to `Log::error` with structured context: `stripe_event_id`, `event_type`, `organization_id` (best-effort resolved from Stripe customer), `exception_class`. Signature/payload failures use namespaced log messages (`stripe.webhook.signature_invalid`, `stripe.webhook.payload_invalid`, `stripe.webhook.config_missing`) with request IP.
  - **Billing failure logging improved:** `SubscriptionController` checkout, portal, and invoice-fetch failure logs now include `organization_slug`, `stripe_customer_id`, `exception_class` alongside existing `organization_id`. Log messages use structured dot-notation keys (`stripe.subscription.initiation_failed`, `stripe.portal.launch_failed`, `stripe.invoices.fetch_failed`).
  - **Docs created/updated:**
    - `docs/OBSERVABILITY_AND_RUNBOOK.md` (new): webhook failure triage, billing failure triage, export limit troubleshooting, API rate-limit troubleshooting, failed jobs inspection/retry, log reference, triage flowchart.
    - `docs/PRODUCTION_READINESS_CHECKLIST.md`: added Observability & Operations section with status for readiness, logging, alerting, and queue monitoring.
    - `docs/QA_RELEASE_PLAYBOOK.md`: added Health & Readiness smoke test and Webhook Failure Observability smoke test sections.
  - **Tests:** `ReadinessEndpointTest` (readiness returns healthy structure, database check), `WebhookObservabilityTest` (signature failure returns 400, processing failure logs context and marks event failed in DB).
  - **Files changed:** `app/Http/Controllers/HealthCheckController.php` (new), `app/Http/Controllers/WebhookController.php`, `app/Http/Controllers/Admin/SubscriptionController.php`, `routes/web.php`, `tests/Feature/Observability/ReadinessEndpointTest.php` (new), `tests/Feature/Observability/WebhookObservabilityTest.php` (new), `docs/OBSERVABILITY_AND_RUNBOOK.md` (new), `docs/PRODUCTION_READINESS_CHECKLIST.md`, `docs/QA_RELEASE_PLAYBOOK.md`, `docs/PROJECT_STATUS.md`.
  - **QA checklist:**
    1. `curl http://localhost:8080/_readiness` → 200 JSON `{"status":"healthy"}` with all checks passing.
    2. `curl http://localhost:8080/up` → 200 (unchanged).
    3. POST `/webhooks/stripe` with bad signature → 400; `stripe.webhook.signature_invalid` in logs.
    4. Trigger webhook processing failure → `Stripe webhook processing failed` in logs with `stripe_event_id`, `organization_id`, `exception_class`; `stripe_webhook_events.status = failed`.
    5. Checkout with missing Stripe config → 422; `stripe.subscription.initiation_failed` in logs.
    6. Run `./vendor/bin/sail artisan test` and `./vendor/bin/sail npm run build`.

- **Runtime usage/limits enforcement layer (rescue-mission):**
  - **API RPM from canonical entitlements:** `ThrottleOrgApi` now resolves per-org limit from `EntitlementsService::value('api_rpm', $org)` while preserving existing keying (`org + api key + IP`) and safe fallback to `config('api.rate_limit_per_minute')`.
  - **Unauthorized API behavior unchanged:** invalid/missing API key requests still fail in `AuthenticateOrganizationApiKey` with `401` before rate-limit handling.
  - **Storage quota foundation:** added `StorageUsageService` as canonical storage read model (`currentUsageBytes`, `currentUsageGb`, `limitGb`, `isOverLimit`) using current known source `project_files.size` joined by org projects.
  - **Export limit proof hook:** added `DailyExportLimitService` and enforced `exports_per_day` on `ReportController::exportCsv`; over-limit requests now return safe `429` with message.
  - **New numeric entitlement key:** `exports_per_day` added cleanly to `FeatureCatalog`, `PlanCatalog`, and enterprise billing config defaults.
  - **Tests:** added `ApiRpmEntitlementTest`, `StorageUsageServiceTest`, `ExportLimitEnforcementTest`; updated `ApiRateLimitTest` to pin deterministic entitlement limit.
  - **Files changed:** `app/Http/Middleware/ThrottleOrgApi.php`, `app/Http/Controllers/ReportController.php`, `app/Services/Billing/StorageUsageService.php`, `app/Services/Billing/DailyExportLimitService.php`, `app/Support/FeatureCatalog.php`, `app/Support/PlanCatalog.php`, `app/Models/Platform/OrganizationFeature.php`, `config/billing.php`, `tests/Feature/Billing/ApiRpmEntitlementTest.php`, `tests/Feature/Billing/StorageUsageServiceTest.php`, `tests/Feature/Billing/ExportLimitEnforcementTest.php`, `tests/Feature/Api/ApiRateLimitTest.php`, docs updates.
  - **QA checklist:**
    1. Create two org API keys with different `api_rpm` feature override values and verify each org hits its own minute limit independently.
    2. Verify unauthorized API request still returns `401` and is not converted to `429`.
    3. Add project file rows for an org and confirm `StorageUsageService` usage + over-limit logic align with `storage_gb`.
    4. Set `exports_per_day=1`, run `/org/{org}/export/csv/clients` twice: first succeeds, second returns `429`.
    5. Run `./vendor/bin/sail artisan test` and `./vendor/bin/sail npm run build`.

- **Tenant self-serve billing portal + invoice/receipt UX (rescue-mission):**
  - **Portal authorization + safety:** `GET /org/{org}/billing/portal` now requires `billing.manage` (not `billing.view`), validates Stripe runtime config (`cashier.secret`, `services.stripe.key`), and requires a linked Stripe customer (`organizations.stripe_id`).
  - **Portal execution:** Action uses Cashier billing portal redirect and catches failures, returning a safe flash error instead of breaking billing page flow.
  - **Invoice normalization:** Billing page now receives canonicalized Cashier-backed invoice payload with defensive fallbacks: `id`, `number`, `currency`, `status`, `total_minor`, `subtotal_minor`, date fields, and optional hosted links (`hosted_invoice_url`, `invoice_pdf`, `receipt_url`).
  - **Safe link handling:** Invoice/receipt links are sanitized to HTTPS Stripe hosts before being exposed to UI.
  - **Billing UI updates:** `Billing/Index.vue` adds compact **Manage billing** action for authorized users and an enriched invoice history table with conditional actions for view/download/receipt links while preserving existing canonical billing sections.
  - **Tests:** Added `BillingPortalAndInvoicesTest` for portal auth, safe failures (missing config/customer), normalized invoice payload exposure, and empty invoice list handling.
  - **Docs:** Updated `ENTITLEMENTS_AND_BILLING.md`, `INTEGRATIONS_STANDARDS.md`, `QA_RELEASE_PLAYBOOK.md`, and this status file.
  - **QA checklist:**
    1. User without `billing.manage` cannot access `/org/{org}/billing/portal` (403).
    2. Missing Stripe config -> Manage billing redirects back with safe error flash.
    3. No Stripe customer (`stripe_id` missing) -> Manage billing redirects back with safe error flash.
    4. Configured org with Stripe customer -> Manage billing redirects to Stripe portal.
    5. Billing invoice history shows number/date/total/status and only valid link actions.
    6. Org without invoices shows clean empty state.
    7. Run `./vendor/bin/sail artisan test` and `./vendor/bin/sail npm run build`.

- **Stripe webhook lifecycle sync + idempotent canonical reconciliation (rescue-mission):**
  - **Webhook ingress:** Extended existing `POST /webhooks/stripe` endpoint (no new webhook path) with Stripe signature verification and safe malformed/invalid handling.
  - **CSRF:** Existing exemption for `webhooks/stripe` retained in `bootstrap/app.php`.
  - **Idempotency store:** Added `stripe_webhook_events` table + model with unique `stripe_event_id`, processing status, notes, payload snapshot, and `processed_at`.
  - **Event coverage:** `customer.subscription.created|updated|deleted`, `checkout.session.completed` (subscription mode), `invoice.payment_succeeded`, `invoice.payment_failed`.
  - **Canonical sync:** Added `StripeWebhookSyncService` to reconcile Stripe lifecycle into `organization_subscriptions` (`plan_key`, `status`, `trial_ends_at`, `current_period_ends_at`, `seats_included`) while keeping `organizations.plan` untouched.
  - **Plan reconciliation:** Reverse mapping from Stripe `price.id` back to internal `plan_key` uses `config/billing.php` (`stripe_prices`). Unknown price IDs are logged/audited (`webhook_plan_mismatch`) without crashing status sync.
  - **Audit logging:** Added webhook-driven billing audit events: `subscription_status_changed`, `subscription_canceled`, `payment_succeeded`, `payment_failed`, and mismatch notes.
  - **Backward compatibility:** Preserved legacy invoice checkout webhook behavior (`checkout.session.completed` with `invoice_id` metadata) so portal invoice payments continue to work.
  - **Tests:** Added `StripeWebhookSyncTest` covering invalid signature, duplicate event idempotency, subscription update/delete sync, payment failure status sync, and subscription checkout completion sync.
  - **Docs updated:** `ENTITLEMENTS_AND_BILLING.md`, `INTEGRATIONS_STANDARDS.md`, `SECURITY_MODEL.md`, `QA_RELEASE_PLAYBOOK.md`.
  - **QA checklist:**
    1. Send webhook with invalid signature → `400`, no canonical changes.
    2. Send same event ID twice → second delivery acknowledged with no duplicate mutation.
    3. Send `customer.subscription.updated` for mapped price → canonical status/period/plan sync.
    4. Send `customer.subscription.deleted` → canonical status becomes `canceled`.
    5. Send `invoice.payment_failed` → canonical status becomes `past_due` (non-terminal states).
    6. Send `checkout.session.completed` in subscription mode with `plan_key` metadata → canonical record upserts.
    7. Verify `stripe_webhook_events` row status is `processed`.
    8. Run `./vendor/bin/sail artisan test` and `./vendor/bin/sail npm run build`.

- **Stripe subscription initiation + canonical sync (rescue-mission):**
  - **Goal delivered:** Authorized tenant admins can initiate Stripe-backed subscriptions by internal `plan_key` while preserving `organization_subscriptions` as canonical billing state.
  - **Config:** Added `config/billing.php` `stripe_prices` map (`starter`, `pro`, `enterprise`) and env entries for plan price IDs.
  - **Backend:** `SubscriptionController::checkout` now validates `plan_key`, enforces `billing.manage`, verifies Stripe config/mapping, starts/switches subscription through `StripeSubscriptionService`, and upserts canonical `organization_subscriptions` (`plan_key`, `status`, `seats_included`, period/trial fields when available).
  - **Cashier integration:** Service creates/links Stripe customer if missing, swaps active/trialing subscriptions (no proration), creates direct subscription when a default payment method exists, and falls back to Stripe Checkout when payment method is missing.
  - **Audit:** Added `subscription_initiated` audit event on `subscription`.
  - **Frontend:** Billing plans now expose `has_stripe_price`; UI shows `Subscribe` / `Switch to plan` actions for users with `billing.manage`, disables self-serve plans without Stripe price, and shows a clear Stripe-not-configured message.
  - **Tests:** Added `StripeSubscriptionStartTest` covering 403 unauthorized, safe 422 on missing Stripe config/price, canonical upsert + customer-link behavior via mocked billing service, and billing page plan-action availability flags.
  - **Docs:** Updated `ENTITLEMENTS_AND_BILLING.md` (Stripe initiation section + canonical clarification) and `INTEGRATIONS_STANDARDS.md` (Stripe integration standards/scope).
  - **QA checklist:**
    1. Owner with `billing.manage`: open billing page and confirm Subscribe/Switch actions appear only on plans with configured Stripe prices.
    2. Remove Stripe config or plan price mapping and attempt Subscribe → safe 422/message and no crash.
    3. Org without `stripe_id`: start Pro subscription → Stripe initiation returns checkout URL or immediate success; canonical `organization_subscriptions` reflects selected `plan_key` and status.
    4. Employee without `billing.manage`: POST `/org/{org}/billing/checkout` returns 403.
    5. Run `./vendor/bin/sail artisan test tests/Feature/Billing/StripeSubscriptionStartTest.php`.
    6. Run full checks: `./vendor/bin/sail artisan test` and `./vendor/bin/sail npm run build`.

- **Billing write model: internal control-plane for plan/addons (rescue-mission):**
  - **Permission:** Added `billing.update`; Owner/Super Admin get it via all-permissions; wired in Roles matrix.
  - **Routes:** PATCH billing/plan, POST billing/addons, PATCH/DELETE billing/addons/{addon} with scopeBindings.
  - **Controller:** updatePlan (create/update organization_subscriptions), storeAddon, updateAddon, destroyAddon (deactivate via active=false). Validation: plan_key from PlanCatalog; addon_key from FeatureCatalog numeric keys; mode augment/set.
  - **Audit:** plan_changed, addon created/updated/deactivated via AuditLogger.
  - **Frontend:** When canUpdateBilling, Billing page shows plan selector + Save, add-on form, add-on table with Edit/Deactivate. Read-only when user lacks billing.update.
  - **Tests:** BillingWriteModelTest — plan change, 403 unauthorized, addon create/update/deactivate, entitlements resolution, org scoping (404 cross-tenant).
  - **Docs:** ENTITLEMENTS_AND_BILLING.md (internal control plane, write model rules, audit), PERMISSIONS.md (billing.update).
  - **Files changed:** `database/seeders/RolesAndPermissionsSeeder.php`, `routes/web.php`, `app/Http/Controllers/Admin/SubscriptionController.php`, `app/Http/Controllers/RolePermissionController.php`, `app/Support/FeatureCatalog.php`, `resources/js/Pages/Billing/Index.vue`, `tests/Feature/Billing/BillingWriteModelTest.php`, `docs/ENTITLEMENTS_AND_BILLING.md`, `docs/PERMISSIONS.md`, `docs/PROJECT_STATUS.md`.
  - **QA checklist:**
    1. Owner visits billing → sees plan selector, Save plan, + Add add-on, Edit/Deactivate on add-ons.
    2. Change plan → Save → redirect with success toast; entitlements/seats reflect new plan.
    3. Create add-on (storage_gb, augment, value 10) → appears in table; entitlements.storage_gb increases.
    4. Edit addon mode/value → Save → table and entitlements update.
    5. Deactivate addon → row shows Active: No; entitlements no longer include that addon.
    6. Employee (no billing.update) → page is read-only, no admin controls.
    7. Cross-org: org B owner cannot PATCH org B billing/addons/{addon-of-org-A} → 404.
    8. Run `./vendor/bin/sail artisan test tests/Feature/Billing/` and `npm run build`.

- **Billing page: expose canonical billing/entitlements (read-only UI) (rescue-mission):**
  - **Backend:** SubscriptionController now enriches the Billing page payload with canonical data from `organization_subscriptions`, `SeatCounter`, `EntitlementsService`, and `organization_addons`. Props: `subscription` (plan_key, status, trial_ends_at, current_period_ends_at, seats_included, seat_limit), `seats` (active_count, can_add_seat), `entitlements` (resolved map), `addons` (list with mode, value_int, active, dates). Plan key resolution: subscription.plan_key → org.plan → default. Fallbacks preserved.
  - **Frontend:** Billing/Index.vue refactored to use PageShell with read-only sections: Current Plan & Status (canonical), Seats (active/included/limit/can_add_seat), Effective Entitlements (table), Add-ons (table with mode/value/active/period). Existing Stripe blocks (Manage subscription, Upgrade, Available plans, Invoice history) unchanged. CRM card-neo styling.
  - **Tests:** BillingPageDataTest — canonical subscription data, subscription.plan_key over org.plan, active seat count, entitlements and addons, 403 unauthorized.
  - **Docs:** ENTITLEMENTS_AND_BILLING.md — added "Billing Admin Read Model" section.
  - **Files changed:** `app/Http/Controllers/Admin/SubscriptionController.php`, `resources/js/Pages/Billing/Index.vue`, `tests/Feature/Billing/BillingPageDataTest.php`, `docs/ENTITLEMENTS_AND_BILLING.md`, `docs/PROJECT_STATUS.md`.
  - **QA checklist:**
    1. Visit `/org/{org}/billing` as Owner → page shows Plan (canonical), Status, Period ends, Seats card, Entitlements table, Add-ons table.
    2. Org with organization_subscriptions row → plan_key/status/seats from subscription; org with only org.plan → fallback to org.plan.
    3. Org with addons → Add-ons table shows key, mode, value, active, period; entitlements reflect augment/set.
    4. User without billing.view (e.g. Employee) → 403.
    5. Run `./vendor/bin/sail artisan test tests/Feature/Billing/` and `./vendor/bin/sail npm run build`.

- **Final UI stabilization: rail + profile normalization (rescue-mission):**
  - **IconRail hover collapse hit area fixed:** rail nav scroll container now clips horizontally (`overflow-x: clip`, `overflow-x: hidden` fallback), so hidden/collapsed labels no longer extend hover hit-testing into page content.
  - **Collapsed active pill alignment fixed:** default rail button gap removed in collapsed state; spacing now applies only in expanded state so icons remain centered inside pills when collapsed.
  - **Bottom profile/auth area stabilized:** profile button remains pinned at rail bottom (`mt-auto`) and dropdown opens at a stable horizontal anchor based on collapsed rail width (not fluctuating expanded rail geometry).
  - **Profile dropdown/logout hardening:** click-outside behavior preserved; logout simplified to backend-driven redirect flow without frontend fallback redirect logic.
  - **Breadcrumb guard for non-org pages:** layout breadcrumb logic now safely checks route existence and falls back to `#` for modules without an index route (e.g. `Profile/Edit` -> `profile.index` absent), preventing route generation errors on `/profile`.
  - **Profile page normalized to CRM layout/theme:** `/profile` now uses `PageShell` with CRM-style header and dark glass section cards; no tabs introduced and route scope unchanged.
  - **Profile partials themed for CRM dark UI:** the three profile sections now use dark typography and local form/input styling overrides only within profile files (shared Breeze components unchanged).
  - **Files changed:** `resources/js/Components/ui/IconRail.vue`, `resources/js/Layouts/AuthenticatedLayout.vue`, `resources/js/Pages/Profile/Edit.vue`, `resources/js/Pages/Profile/Partials/UpdateProfileInformationForm.vue`, `resources/js/Pages/Profile/Partials/UpdatePasswordForm.vue`, `resources/js/Pages/Profile/Partials/DeleteUserForm.vue`, `docs/PROJECT_STATUS.md`.
  - **QA checklist:**
    1. Rail expands only on hover/focus-within and collapses immediately when cursor leaves the visible rail width.
    2. In collapsed rail state, active pill/icon alignment remains centered (no right drift from label gap).
    3. `/profile` renders with CRM `PageShell` header + dark glass cards (no Breeze light-card layout).
    4. Top breadcrumb renders on `/profile` without route-generation errors from missing `profile.index`.
    5. Bottom profile menu remains usable; dropdown opens consistently and Sign out succeeds.

- **Navigation + auth/profile UX regression fix (rescue-mission):**
  - **Profile page regression fixed:** `/profile` now consistently renders the Inertia page (`Profile/Edit`) within the shared authenticated app layout. The page was updated to use `defineOptions({ layout: AuthenticatedLayout })` instead of nested Breeze-style layout markup.
  - **Why users saw `OK` on white page:** logout redirected to `/`, and `/` is intentionally a plain `OK` health response in this app. Logout now redirects to `route('login')`, so users land on the login page reliably.
  - **Logout frontend safety fallback:** IconRail logout now posts to `logout` and then uses an `onFinish` guard to `router.visit(login)` if the browser is not already on `/login`.
  - **IconRail labels visible on hover/focus-within:** label reveal was hardened so text is not clipped in collapsed/expanding states (`max-width` transition + overflow-safe container). Keyboard focus still expands the rail.
  - **Active nav highlight is SPA-reactive:** `isActive(...)` now prefers Ziggy `route().current(...)` checks and falls back to reactive `usePage().url` path matching (no `window.location`-only logic), so highlights update correctly in persistent Inertia layouts.
  - **Ghost/missing item spacing fixed:** section separator kept as a deliberate thin divider (`rail-section-sep`) with consistent margins; removed visual appearance of a blank nav item.
  - **Files changed:** `resources/js/Pages/Profile/Edit.vue`, `app/Http/Controllers/Auth/AuthenticatedSessionController.php`, `resources/js/Components/ui/IconRail.vue`, `tests/Feature/ProfileTest.php`, `tests/Feature/Auth/AuthenticationTest.php`.
  - **QA checklist:**
    1. Visit `/profile` while authenticated -> page renders Profile forms (not plain `OK`).
    2. Click Sign out from IconRail menu -> redirected to `/login`.
    3. Hover rail -> labels become visible beside icons; no main-content layout jump.
    4. Keyboard Tab into rail -> rail expands and labels are readable.
    5. Navigate between Clients/Projects/Tasks/Billing via Inertia links -> active pill updates immediately.
    6. Divider between nav sections looks intentional (thin line), not an empty/ghost slot.
    7. Non-admin users still do not see Settings/Billing links.

- **IconRail active-state hardening + profile menu (rescue-mission):**
  - **Root cause fixed — active state not reactive**: `isCurrent()` was reading `window.location.pathname` (not reactive). Since `AuthenticatedLayout` is a persistent Inertia layout (never unmounts on navigation), the active state never updated after first render. Fix: replaced with `page.url` from Inertia's reactive `usePage()`, which updates on every SPA navigation. All nav items (including Billing) now highlight correctly.
  - **isActive(...routeNames) helper**: replaced `isCurrent()` / `isCurrentAny()` with a single `isActive(...routeNames: string[])` variadic helper. Each item can list its own patterns: e.g., `isActive('clients.index', 'clients.show', 'clients.create', 'clients.edit')` — highlights when on any Clients page.
  - **Premium pill highlight**: active items now render as inset rounded pills (`margin: 0 6px; border-radius: 0.75rem`) instead of a full-bleed rectangle. Active state gets a subtle glow (`box-shadow: 0 2px 14px rgba(62,83,255,0.38)`). Icon column re-centres via `margin-left` transition so icons shift left-aligned when rail expands.
  - **Profile / Auth menu**: a user avatar button (initials gradient circle) is pinned to the bottom of the rail via `flex-1` spacer. Clicking it opens a fixed-positioned dropdown (Teleported to body so `overflow:hidden` on the rail doesn't clip it). Dropdown contains: Profile (→ `/profile`), Settings (admin-only, → settings.index), Sign out (Inertia `router.post('/logout')`). Click-outside closes the dropdown via capture-phase `document.addEventListener`. Keyboard-accessible via `:aria-expanded`.
  - **Nav separator**: replaced `rail-sep mx-auto` (which behaved inconsistently with `align-items:stretch`) with `rail-section-sep` — a proper full-width `1px` line with symmetric margins (`margin: 6px 12px`) that aligns with the pill buttons.
  - **Files changed:** `resources/js/Components/ui/IconRail.vue`.
  - **QA checklist:**
    1. Navigate to Billing → Billing nav item should highlight (gradient pill).
    2. Navigate Clients → Index, Show, Create, Edit → Clients item stays highlighted throughout.
    3. Navigate via keyboard (Tab into rail items) → active item should be highlighted, focus reveals labels.
    4. Click user avatar at bottom of rail → dropdown opens to the right.
    5. Dropdown: click Profile → navigates to /profile; click Settings → navigates to settings; click Sign out → logs out (POST /logout).
    6. Click anywhere outside dropdown → dropdown closes.
    7. Non-admin user: Settings + Billing items hidden from rail; Settings hidden from dropdown.
    8. Section separator (thin line) is visible between Announcements and Employees sections — not a blank gap.
    9. Hover expand + label animation still work.

- **UI Polish: IconRail hover-expand + compact headers (rescue-mission):**
  - **IconRail hover-expand:**
    - Desktop rail (`position: fixed`, `--rail-width: 76px`) now expands to `216px` on `:hover` / `:focus-within` with a `240ms cubic-bezier(0.32,0.72,0,1)` width transition.
    - Each nav item now has an icon column (`rail-btn__icon`, fixed `76px`) and a label (`rail-btn__label`) that animates in with `opacity + translateX` once the rail expands.
    - Labels are hidden by default (opacity 0, translateX −6px) and reveal with a staggered `160ms/180ms` transition.
    - Keyboard focus (`focus-within`) also expands the rail — full accessibility support.
    - `aria-current="page"` added to the active nav item for screen-reader / AT support.
    - **No layout shift**: main content area (`bordermainradius { margin-left: 5rem }`) is untouched; expanded rail is an overlay.
    - Active state (`current` gradient) preserved in both collapsed and expanded modes.
  - **Compact headers:**
    - `PageHeader.vue`: padding reduced from `1.5/1.75rem` → `0.75/1rem`; title from `text-2xl sm:text-3xl` → `text-xl sm:text-2xl`; subtitle margin tightened to `mt-0.5`.
    - `ChromeTabs.vue`: `--chrome-tab-height` reduced from `2.25rem` → `2rem`; tab padding tightened to `0.875rem`.
    - `PageShell.vue`: outer `space-y-6` → `space-y-4`; sticky area `space-y-6` → `space-y-3`.
  - **Files changed:** `resources/js/Components/ui/IconRail.vue`, `PageHeader.vue`, `ChromeTabs.vue`, `PageShell.vue`.
  - **QA checklist:**
    1. **Hover expand**: mouse over the left rail on any authenticated page → rail should smoothly expand to ~216px showing labels beside icons.
    2. **Label animation**: labels fade in (no flash/jump); icons do NOT move during expansion.
    3. **Keyboard focus**: Tab into any rail item → rail expands, label is visible.
    4. **Active state**: current page item shows the gradient bg in both collapsed and expanded states.
    5. **No layout shift**: main content area does not move when rail expands.
    6. **Mobile drawer**: unchanged — hamburger + drawer still works on < lg screens.
    7. **Compact headers**: visit Settings, Clients, Projects — headers should be visibly shorter while keeping hierarchy (title > subtitle).
    8. **Sticky headers**: visit a page with `sticky=true` PageShell — header should stick on scroll with no overlap.
    9. **ChromeTabs**: tabs should be slightly shorter (`2rem` height) but still readable and premium.
    10. **Build**: `npm run build` produces no errors.

- **Hotfix: Billing page 500 — subscription() collision (rescue-mission):**
  - **Cause:** Organization defined `subscription()` HasOne to OrganizationSubscription, colliding with Cashier Billable's `subscription('default')`. Code expecting Cashier Subscription received our HasOne relation, then called `->active()` → BadMethodCallException.
  - **Fix:** Renamed relation to `billingSubscription()`. SeatCounter, EntitlementsService updated. SubscriptionController now uses Cashier `subscription('default')` correctly.
  - **Tests:** BillingPageDoesNotCrashTest — GET billing page as user with billing.view → 200.
  - **QA:** Visit `/org/{org}/billing` as Owner → no 500, page loads.

- **Add-on semantics + plan key canonical (rescue-mission):**
  - **Add-on modes:** `organization_addons.mode` — `augment` (adds to base; default) or `set` (overrides with value_int; multiple set → highest wins). Migration adds column with default `augment`.
  - **EntitlementsService:** Apply addons with mode-aware logic. augment: sum; set: override with value_int, highest wins for same key.
  - **Plan key canonical:** `organization_subscriptions.plan_key` is source of truth. Fallback: `organizations.plan` (legacy) when no subscription. `organizations.plan` is legacy/display-only.
  - **Organization model:** Comment added that plan is legacy; fillable unchanged for backward compat.
  - **SeatCounter:** Uses same plan resolution (subscription first, org.plan fallback).
  - **Tests:** AddonsSemanticsTest — augment increases; set overrides; multiple set choose highest; subscription.plan_key over org.plan; org.plan fallback when no subscription; mode defaults augment.
  - **Docs:** ENTITLEMENTS_AND_BILLING.md (add-on modes, set conflict rule, plan key source of truth), DB_SCHEMA.md (mode column), PRODUCTION_READINESS_CHECKLIST.md (billing semantics stability).
  - **QA checklist:**
    1. Seed an org subscription with plan_key=starter.
    2. Create addon storage_gb with augment +10 → EntitlementsService reports base + 10.
    3. Create addon api_rpm with set=600 → EntitlementsService returns 600.
    4. Ensure existing behavior: addons without mode default to augment.

- **Seat limit enforcement (rescue-mission):**
  - **Enforcement:** `SeatCounter::assertCanAddSeat(Organization $org)` throws `ValidationException` (422) with message "Seat limit reached for your plan. Upgrade or add seats to invite more users." when org is at staff seat limit.
  - **Enforcement points:** HRMController::store (create employee), RegisteredTenantController::store (tenant registration), RegisteredUserController::store (legacy registration). PortalAccessController::store (enable portal access) — **not** enforced (portal users excluded from seat count).
  - **Staff vs portal:** Staff = `users.client_id` IS NULL, counts toward limit. Portal = `users.client_id` set, never blocked.
  - **Tests:** SeatEnforcementTest — allows staff when under limit; blocks staff at limit (422); allows portal when at staff limit; tenant scoping (org B limit does not affect org A).
  - **Docs:** ENTITLEMENTS_AND_BILLING.md (enforcement points, staff/portal definitions), QA_RELEASE_PLAYBOOK.md (seat limit QA scenario).
  - **QA:** Run `./vendor/bin/sail artisan test tests/Feature/Billing/SeatEnforcementTest.php`. Manual: org at 2-seat limit → add employee via HRM → 422; enable portal access for contact → success.

- **Billing & Entitlements foundation (rescue-mission):**
  - **Schema:** `organization_subscriptions` (plan_key, status, trial_ends_at, current_period_ends_at, seats_included, seat_limit) and `organization_addons` (addon_key, quantity, value_int, active, starts_at/ends_at). All tenant-scoped by organization_id.
  - **Plan catalog:** `App\Support\PlanCatalog` — starter, pro, enterprise with default entitlements; enterprise overrides via config/billing.php.
  - **Entitlements resolver:** `App\Services\Billing\EntitlementsService` — resolution order: plan defaults → org overrides (organization_features.features) → add-ons (augment numerics). Methods: forOrg(), enabled(), value(). Per-request cache.
  - **Seat counting:** `App\Services\Billing\SeatCounter` — countActiveSeats() (tenant app users only; portal users with client_id set excluded), canAddSeat(). Organization::canAddSeat() delegates to SeatCounter.
  - **Feature catalog:** Added `api_rpm` to FeatureCatalog; PlanCatalog aligned with attendance, sms, api_access, storage_gb, api_rpm.
  - **No Stripe code** in this PR; future webhook will sync organization_subscriptions.
  - **Tests:** EntitlementsResolutionTest (plan defaults, org overrides, addons augment, tenant scoping), SeatCounterTest (count tenant users, exclude portal users, canAddSeat under/at limit, seat_limit override, tenant scoping).
  - **Docs:** ENTITLEMENTS_AND_BILLING.md (pricing model, definitions, resolution order, schema, future Stripe plan), PRODUCTION_READINESS_CHECKLIST.md (billing/entitlements checklist).
  - **QA:** Run `./vendor/bin/sail artisan test tests/Feature/Billing/`. Run full suite and `npm run build`. No UI changes; enforcement helpers available for future use.

- **Public API Rate Limiting:**
  - **Middleware:** `ThrottleOrgApi` — key `org:{orgId}:key:{apiKeyId}:ip:{ip}`, 60 req/min (config: `API_RATE_LIMIT_PER_MINUTE`).
  - **Applied to:** `/api/*` routes with `org_api_key` + `throttle_org_api` (auth runs first; 401 does not consume quota).
  - **429 response:** `{"message":"Too many requests.","code":"rate_limited"}` — no org/key leak.
  - **Config:** `config/api.php` — rate_limit_per_minute.
  - **Tests:** `ApiRateLimitTest` — within limit succeeds; exceed returns 429 with code; per-test org/key isolation.
  - **Docs:** SECURITY_MODEL.md (Rate Limiting), INTEGRATIONS_STANDARDS.md (429 + backoff), QA_RELEASE_PLAYBOOK.md (rate limit smoke check).
  - **QA:** Valid token → multiple requests within 60/min → 200. Exceed limit → 429 with `code: "rate_limited"`. Run `./vendor/bin/sail artisan test tests/Feature/Api/ApiRateLimitTest.php`.

- **Public API Authentication (Bearer tokens):**
  - **Middleware:** `AuthenticateOrganizationApiKey` — reads `Authorization: Bearer crmb_xxx`, validates via prefix + sha256 compare against `organization_api_keys`, rejects revoked keys, sets `app('scoped.organization')` and request attributes.
  - **last_used_at:** Updated only when null or older than 5 minutes (throttle).
  - **Route:** GET `/api/ping` — returns `ok`, `organization` (id, slug, name), `api_key_prefix`, `timestamp`. Protected by `org_api_key` + `throttle:org-api` middleware.
  - **Tests:** `ApiKeyAuthTest` — 401 missing/malformed/invalid/revoked; 200 valid token; last_used throttle; no raw token in response; org A key never returns org B.
  - **Docs:** SECURITY_MODEL.md (Public API Authentication), INTEGRATIONS_STANDARDS.md (API Keys usage), QA_RELEASE_PLAYBOOK.md (API smoke test).
  - **QA:** Create key in Settings → API Keys. `curl -H "Authorization: Bearer <token>" /api/ping` → 200, org info. No header → 401. Revoke key → 401. Run `./vendor/bin/sail artisan test tests/Feature/Api/ApiKeyAuthTest.php`.
  - **Next blockers:** Expand API endpoints (clients, projects, etc.); scoped permission checks for API.

- **Tenant-scoped API Keys (Settings):**
  - **DB:** Migration `organization_api_keys` — name, prefix, hashed_key (sha256), last_used_at, created_by_user_id, revoked_at. Never store plaintext.
  - **Model:** OrganizationApiKey with organization(), createdBy(), scope active().
  - **Permissions:** api_keys.view, api_keys.create, api_keys.delete. Owner/Manager get all three; Employee has none.
  - **Controller:** SettingsApiKeysController — store (returns plaintext once), destroy (revoke). SettingsController::index adds apiKeys + canViewApiKeys to props when user has api_keys.view.
  - **Routes:** POST /org/{org}/settings/api-keys, DELETE /org/{org}/settings/api-keys/{apiKey} (scopeBindings).
  - **UI:** Settings tab "API Keys" — list (name, prefix, created_at, created_by, last_used_at, revoked_at), Create modal, one-time token display with Copy, Revoke with confirmation.
  - **Audit:** entity api_key, actions created/revoked; changes include prefix/name only, no secrets.
  - **Tests:** SettingsApiKeysTest — 403 without perms, org scoping (404 cross-tenant revoke), create returns token once/DB stores hashed, audit logs.
  - **QA:** `/org/{org}/settings` → API Keys tab. Create key → copy token → verify list shows prefix → revoke with confirmation. Run `./vendor/bin/sail artisan test tests/Feature/Settings/SettingsApiKeysTest.php`.

- **Client Create/Edit persistence + append-only notes (rescue-mission):**
  - **DB alignment:** Controller and Vue now use canonical column names matching migrations: `fronter_id`, `closer_id`, `gbp_status`, `gbp_access`, `notes_sales`, `notes_cst`, `notes_tech`. Legacy aliases (`google_business_profile_status`, `notes_by_*`) accepted in validation and normalized for backward compatibility.
  - **ClientController store:** Validates `fronter_id`, `closer_id` (not legacy `fronter`/`closer` arrays); `gbp_status`/`gbp_access` with enum rules; `notes_sales`/`notes_cst`/`notes_tech`. Maps legacy input to canonical before save. Only fillable keys passed to `Client::create()`.
  - **ClientController update:** Same validation; supports `new_note_sales`, `new_note_cst`, `new_note_tech` for append-only. When provided, prepends `[Y-m-d H:i] User (#id): ` and appends to existing notes.
  - **Create.vue / Edit.vue:** Use `gbp_status`, `gbp_access`, `notes_sales`, `notes_cst`, `notes_tech`. GBP options: gbp_status `not_created`, `created`, `pending`, `verified`, `suspended`; gbp_access `no_access`, `access_pending`, `access_granted`.
  - **Edit.vue notes:** Existing notes shown read-only; separate "Add note" textareas map to `new_note_*` for timestamped append-only entries.
  - **CSV export (ClientController index):** Fixed to use `gbp_status`, `gbp_access`, `notes_sales`, `notes_cst`, `notes_tech`.
  - **Tests:** `ClientPersistenceTest` — fronter_id/closer_id persist and appear in edit; gbp_status/gbp_access persist; notes_sales persist; new_note_sales appends with timestamp+author.
  - **QA:** Create client with fronter, closer, account manager, GBP status, GBP access, notes → Save → Edit page shows all values. Edit client → Add note in "Add note (append-only)" → Save → Show/Edit shows appended entry with timestamp and author prefix. Run `./vendor/bin/sail artisan test tests/Feature/Clients/`.

- **Client Pipeline status validation (rescue-mission):**
  - **Problem:** UI sends lowercase Pipeline status (`lead`, `active`, `inactive`, `paused`, `churned`) but backend expected Titlecase (`Lead`, `Active`, `Inactive`) and lacked Paused/Churned, causing "selected status is invalid".
  - **ClientController (store + update):** Validation now accepts both lowercase and Titlecase for `status`. Allowed: `lead`, `active`, `inactive`, `paused`, `churned` (and `Lead`, `Active`, `Inactive`, `Paused`, `Churned` for legacy). Status is normalized to lowercase before save.
  - **Tests:** `ClientStatusValidationTest` — store with `status=active` and `status=Active` succeeds and stores `active`; `status=invalid_value` returns 422; update normalization verified.
  - **QA:** Create client at `/org/{org}/clients/create` — select Pipeline status (Lead, Active, Inactive, Paused, Churned) and save; no "selected status is invalid". Edit client — change Pipeline status and save; persisted as lowercase in DB. Run `./vendor/bin/sail artisan test tests/Feature/Clients/ClientStatusValidationTest.php`.

- **Tenant isolation hardening (rescue-mission):**
  - **DepartmentController:** Replaced `app('tenant')` with `$request->route('organization')` in store action; org must be non-null (404 otherwise); create/store always set `organization_id` from route.
  - **TaskController::store:** Validates `project_id` with tenant-scoped `Rule::exists('projects','id')->where('organization_id', $org->id)` to prevent cross-tenant project injection; Task always uses `organization_id` from route regardless of payload.
  - **Scoped route bindings:** Added `scopeBindings()` to projects (resource), clients (show/edit/update/destroy/pipeline), tasks (show/update/destroy/submit/review). Cross-tenant requests return 404 (no existence leak).
  - **Organization model:** Added `tasks()` relationship for scoped binding.
  - **TaskController::show:** Signature updated to accept `Organization $organization` for correct route-parameter order with scopeBindings.
  - **Tests:** `TenantIsolationHardeningTest` — task store rejects cross-tenant project_id (422); department store scopes org; scoped bindings return 404 for cross-tenant task/client/project.
  - **QA:** Run `./vendor/bin/sail artisan test` and `./vendor/bin/sail npm run build`. Verify departments create; tasks create with valid project; cross-tenant URLs (e.g. `/org/org-a/tasks/{id-of-task-in-org-b}`) return 404.
  - **Notes:** ResolveTenant unchanged; all tenant routes remain under `/org/{organization:slug}/...`; `app('scoped.organization')` is the canonical binding.

- **Modules / Feature Flags (Tenant Settings):**
  - New "Modules" tab in Settings UI with DB-backed feature flags (organization_features table).
  - FeatureCatalog (app/Support/FeatureCatalog.php) defines catalog: key, label, description, type (boolean/number), default.
  - Features::enabled() and Features::value() use organization_features when org provided; config fallback when org null.
  - POST /org/{org}/settings/features (settings.features) gated by settings.update; audit log with keys features.<key>.
  - EnsureFeatureEnabled middleware now uses DB-backed Features::enabled (no code change; Features.php updated).
  - Tests: 403 without settings.update, org-scoped update, audit log, index passes features/catalog, Features::enabled DB check.

- **Tenant Settings v2 — audit logging + secret-safe integrations:**
  - AuditLogger for SettingsController::update; logs `settings.updated` with changed keys only (no raw secrets).
  - `slack_webhook_url` and SMTP password encrypted at rest (Laravel Crypt); masked in UI.
  - Test Slack webhook and Test SMTP endpoints (POST `/org/{org}/settings/test-slack`, `/test-smtp`) gated by `settings.update`.
  - Settings/Index.vue: Connected/Not set states, masked secrets, test buttons with success/error toasts.
  - Tests: org-scoped settings, 403 unauthorized, audit log on update, secrets not leaked in props.
  - Docs: SETTINGS_AUDIT.md, PROJECT_STATUS.md.

- **UI foundation: ChromeTabs now top-of-page via PageShell:**
  - **PageShell.vue** (`resources/js/Components/ui/PageShell.vue`): Reusable page shell with layout order: (1) ChromeTabs at top (if tabs provided), (2) PageHeader, (3) content slot. Props: tabs, modelValue (v-model), header (breadcrumb, title, subtitle), **sticky** (boolean, default false). Supports header-actions slot. No tabs prop = header above content only.
  - **Sticky top bar mode (sticky=true):** When enabled, ChromeTabs (if present) and PageHeader stick to the top of the viewport while scrolling. Content scrolls normally beneath. Improves "Chrome luxury" feel on tabbed and scroll-heavy pages. Pages enabled: Settings/Index, Settings/Roles, Projects/Show, Clients/Show, Tasks/Board.
  - **Tabbed pages:** Settings/Index, Settings/Roles, Projects/Show, Clients/Show. Tabs render above header; header-actions slot holds Edit/Delete/Create Role/etc.
  - **Non-tab pages (PageShell without tabs):** Clients/Index, Clients/Create, Clients/Edit, Projects/Index, Projects/Create, Projects/Edit, Tasks/Index, Tasks/Board, Attendance/Index, Announcements/Index, Reports/Index. Same header structure; action buttons in header-actions.
  - **Settings/Index.vue:** Organization, Branding, Work Hours, etc. — RBAC tab gating, unsaved-changes guard.
  - **Settings/Roles.vue:** Quick Matrix | Advanced tabs at top; Create Role in header-actions; unsaved-changes handling unchanged.
  - **Projects/Show.vue:** Overview | Tasks | Files | Internal notes — Back to projects, Edit, Delete in header-actions.
  - **Clients/Show.vue:** Overview | Projects | Contacts | Notes — ← Clients, Edit, Delete in header-actions.
  - **QA (tabbed):** (1) `/org/{org}/settings` — ChromeTabs above header; tabs switch correctly; API Keys tab when permitted. (2) `/org/{org}/settings/roles` — Quick Matrix/Advanced tabs at top; mode switch works; unsaved-changes bar and leave confirmation unchanged. (3) `/org/{org}/projects/{id}` — tabs above header; Overview/Tasks/Files/Notes switch; Back/Edit/Delete visible. (4) `/org/{org}/clients/{id}` — ChromeTabs above header; Overview/Projects/Contacts/Notes switch; ← Clients, Edit, Delete visible.
  - **QA (sticky mode):** On Settings, Settings/Roles, Projects/Show, Clients/Show, Tasks/Board — scroll down: tabs (when present) and header remain visible at top; content scrolls beneath without overlap; header-actions buttons remain clickable; verify on small screens (mobile).
  - **QA (non-tab):** Clients/Index — Pipeline, New Client, Import, Export in header. Clients/Create, Clients/Edit — Back to Clients. Projects/Index — List, Board, Calendar, New Project. Projects/Create, Projects/Edit — Back to Projects. Tasks/Index — List, Board, View projects. Tasks/Board — List, Board. Attendance/Index — Clock in / Clock out in header. Announcements/Index — New announcement in header. Reports/Index — Export CSV, Include deleted (when canExport), else permission message. Form validation and submit unchanged.
  - **Tasks Board optimization:** Board.vue uses a computed `tasksByColumnKey` map instead of repeated `tasksInColumn(column.key)` filtering per column. Single O(n) pass groups tasks by status; template uses O(1) lookups. Drag/drop and status move unchanged.

- **Tenant Settings (Enterprise v1):** Tabs: Organization (name, slug read-only, timezone, week_start, locale, currency), Branding (logo), Work Hours (work_week, start/end time), Notification Defaults (inapp, email), Integrations (Slack webhook, SMTP). FormRequest validation. Settings stored in organizations table + settings table (Setting::put). Org-scoped. See docs/SETTINGS_AUDIT.md.
- **Activity module enterprise polish:** activity.view permission added; ActivityController gated by activity.view (was Task::viewAny). Nav (IconRail, CommandPalette) hides Activity if user lacks permission. AuditLogger added for: Clients import, Announcements (create/update/delete), Attendance (clock-in/out), Invoice paid (webhook). Subject links in Activity/Index.vue (client, project, task, etc.). Tests: 403 without activity.view; audit log with organization_id.
- **Reports page + RBAC + export alignment:** Reports/Index.vue created (dark/glass UI, quick cards for Clients/Projects/Tasks/Attendance/Invoices). Permissions reports.view, reports.export added; index requires reports.view, export requires reports.export. Export columns fixed to primary_contact_email, primary_contact_phone. See QA steps below.
- **Full module + DB audit:** docs/MODULE_AUDIT.md, docs/DB_SCHEMA.md. Next PRs: Permission canonicalization; Unprotected controllers.

---

## Activity QA Steps

1. **Activity page:** `/org/{org-slug}/activity` (e.g. `/org/acme/activity`)
   - Requires `activity.view` permission. User without it gets 403.
   - Nav (rail + mobile + Command Palette) hides Activity link if user lacks permission.
2. **Filters:** Actor, entity, action, date range — Apply/Reset work.
3. **Subject links:** Click Client #N, Project #N, Task #N to navigate to the subject.
4. **Verify audit entries:** Create client → created; create project → created; create task → created; clock in/out → clocked_in/clocked_out; create announcement → created; import clients → imported.

## Reports QA Steps

1. **Reports page:** `/org/{org-slug}/reports` (e.g. `/org/acme/reports`)
   - Must be logged in with reports.view permission.
   - PageShell with breadcrumb, title, subtitle. Export CSV and Include deleted in header-actions when user has reports.export; else "You need reports.export permission" message.
   - Quick cards (Clients, Projects, Tasks, Attendance, Invoices) with counts. Export section with description.
2. **Export CSV:** `/org/{org-slug}/export/csv/clients`
   - Requires reports.export.
   - CSV columns: Company, Primary Contact Email, Primary Contact Phone.
   - Add `?include_deleted=1` to include soft-deleted clients.
- **Runtime blockers fix:** Clients show 500 fixed (Relation type-hint in load closure); Task drawer fixed (Accept: application/json, status-based error messages 403/404/500).
- **Repo Cleanup + Documentation Baseline:** Removed Zone.Identifier files, backups from git; added .gitignore patterns; created docs baseline (PROJECT_CONTEXT, PROJECT_STATUS, PERMISSIONS, DECISIONS, RUNBOOK_LOCAL).
- **Create Role UX:** Auto-slug from display name, "Customize key" override, toast "Role created. Now choose permissions and click Save."
- **Roles Save flow:** Fixed Leave modal blocking Save; POST visits no longer trigger unsaved-changes guard; dirty state resets on success.

---

## Settings QA Steps

1. **Settings page:** `/org/{org-slug}/settings` (e.g. `/org/acme/settings`)
   - Requires `settings.view` to view, `settings.update` to save.
2. **Tabs (ChromeTabs via PageShell):** Tabs render at the top of the page, above the "Settings" header. Organization, Branding, Work Hours, Notification Defaults, Integrations, Modules, API Keys (when permitted). Chrome-style overlapping tabs; keyboard: Tab to focus, ←/→ to switch.
3. **Organization:** Name, slug (read-only), timezone, week start, locale, currency.
4. **Branding:** Logo upload (max 1 MB).
5. **Work Hours:** Mon–Fri / Sun–Thu, start/end time (for attendance/reporting).
6. **Notification Defaults:** In-app, email toggles (defaults for new users).
7. **Integrations:** Slack webhook URL, SMTP (host, port, from, user, password). Secrets encrypted at rest; masked in UI.
8. **Modules:** Feature flags (attendance, sms, api_access, storage_gb). Toggle booleans, set numeric values. Save via "Save feature flags" button.
9. **Save:** Loading state + success toast. Settings are org-scoped.
10. **API Keys:** Tab visible when user has api_keys.view. Create key (modal with name), plaintext shown once with Copy; list shows name, prefix, created_at, created_by, last_used_at, revoked_at. Revoke with confirmation. No secrets in props.

### Blank Secret Fields — QA

- **Save with Slack/SMTP secrets left blank:** Configure Slack webhook and SMTP password, save. Then change another setting (e.g. timezone) leaving Slack URL and SMTP password fields blank → Save. Returns 302, success toast. Slack and SMTP secrets remain set (`smtp_pass_set` true, Slack shows "Connected").
- **Clear Slack:** Click "Clear" next to Slack webhook → Save. Secret is removed.

### Tenant Settings v2 — QA (Audit Logging + Secret-Safe Integrations)

- **Update timezone/locale → Save:** See success toast and audit entry in Activity (`entity: settings`, `action: updated`, `changes.keys`).
- **Set Slack webhook → Save:** UI shows masked `••••••••` + "Connected". "Test Slack" button sends test message; success/error toast.
- **Set SMTP host/user/pass → Save:** Password not visible on reload. "Test SMTP" button verifies connection; success/error toast.
- **Verification:** `sail artisan test` and `sail npm run build`.

### API Keys — QA

- **API Keys tab:** `/org/{org-slug}/settings` → API Keys tab (visible only with api_keys.view). Create key with name → plaintext token shown once → Copy → Done. List shows name, prefix, created_at, created_by, last_used_at, revoked_at. Revoke with confirmation. Verify audit entries in Activity (entity: api_key, actions: created, revoked; no secrets in changes).
- **RBAC:** User without api_keys.view does not see API Keys tab. Without api_keys.create gets 403 on POST. Without api_keys.delete gets 403 on DELETE.
- **Tenant isolation:** Cross-tenant revoke returns 404.

### Modules / Feature Flags — QA

- **Modules tab:** `/org/{org-slug}/settings` → Modules tab. Toggle attendance, sms, api_access; set storage_gb. Click "Save feature flags" → success toast.
- **RBAC:** User without settings.update gets 403 on POST /settings/features.
- **Audit:** Changes logged with entity settings, action updated, keys like features.sms, features.storage_gb.

## Roles & Permissions — Matrix UX

- **Page:** `/org/{org}/settings/roles`
- **Modes:** Quick Matrix (default), Advanced — now via PageShell ChromeTabs at top-of-page (local mode)
- **Features:** Permission catalog, sticky bar, leave confirmation, team-scoped badges, Create Role in header-actions
- **QA:** Tabs (Quick Matrix | Advanced) render above header; mode switch works; unsaved-changes bar and leave confirmation unchanged; Create Role, Save, Discard; persist, save flow, create role.

## Tasks — QA

- **Pages:** Tasks/Index (list), Tasks/Board (Kanban). Both use PageShell; List/Board switcher in header-actions.
- **Board optimization:** Computed `tasksByColumnKey` groups tasks once; counts and v-for use O(1) lookups. No repeated filtering per column.
- **QA:** Open `/org/{org}/tasks` (List) and `/org/{org}/tasks/board` (Board). With tasks: verify column counts match task distribution; use status dropdown to move task; confirm counts update correctly. Open task drawer; filters work on Index.

## Platform: Revenue / MRR Dashboard

- **Page:** `/admin/revenue` (route: `platform.revenue`)
- **Purpose:** Read-only revenue and subscription health overview for platform operators. Estimated MRR from canonical plan data.
- **KPI cards:** Estimated MRR, total orgs (with Stripe-linked count), active/trialing count, at-risk count (past_due + unpaid), total seats, canceled, incomplete, no-subscription counts.
- **MRR estimation:** Based on active + trialing orgs only. Starter=$0/mo, Pro=$79/mo, Enterprise=custom (excluded with note). Clearly labeled "estimated."
- **Plan distribution:** Stacked bar + legend with plan counts and percentages.
- **MRR breakdown:** Per-plan count, unit price, subtotal. Enterprise note when present.
- **Status table:** All subscription statuses with count and percentage of total.
- **Drilldown links:** Org Health, Org Subscriptions, Past Due Orgs (pre-filtered).
- **Nav:** Sidebar "Revenue" link between Org Subscriptions and Org Health.
- **Tests:** `RevenueDashboardTest` — access control, subscription counts, plan distribution, MRR estimation, enterprise note, Stripe linkage, seat counts, fallbacks (no orgs, no subscriptions, legacy plans).
- **QA:** Visit `/admin/revenue`. Verify KPI cards reflect org data. Create pro/starter/enterprise subscriptions; verify MRR breakdown. Verify plan distribution bar. Click drilldown links.

## Platform: Feature Usage Dashboard

- **Page:** `/admin/feature-usage` (route: `platform.feature-usage`)
- **Purpose:** Read-only feature/module adoption overview for platform operators. Helps founders/operators understand how organizations use CRM Beast modules.
- **Summary cards:** Total orgs, orgs using any module (count + %), avg modules per org, billing configured (active subscriptions + Stripe-linked).
- **Module adoption:** Clients, Projects, Tasks, Attendance, Invoices — bar chart with orgs_with_any, total_records, and per-module icons. Soft-deleted records excluded.
- **Billing setup:** Stripe-linked count, any subscription, active/trialing subscription, active add-ons — with percentages.
- **Adoption table:** Detailed per-module breakdown: orgs using, % of total, total records, avg records per adopting org.
- **Methodology notes:** Explains what "usage" means, data source (live DB), soft-delete exclusion, avg calculation.
- **Drilldown links:** Org Health, Org Subscriptions, Revenue.
- **Nav:** "Feature Usage" in sidebar between Org Health and Settings.
- **Tests:** `FeatureUsageDashboardTest` — 13 tests covering access control, per-module adoption, soft-delete exclusion, billing metrics, summary stats, zero-state, and labels.
- **QA:** Visit `/admin/feature-usage`. Verify cards, bars, table reflect org/module data. Create orgs with records → counts update. Soft-deleted excluded. Zero-state clean.

## Platform: System Performance Dashboard

- **Page:** `/admin/system-performance` (route: `platform.system-performance`)
- **Purpose:** Read-only infrastructure and operational health overview for platform operators. Aggregates system readiness, queue health, webhook reliability, storage pressure, and at-risk org signals in one view.
- **Readiness:** Live DB, cache, and queue config checks — same logic as `/_readiness` endpoint. Per-subsystem status badges with driver info.
- **Queue health:** Failed job counts (24h, 7d, all-time), queue driver, 5 most recent failures (queue name, timestamp).
- **Webhook reliability:** Total events, processed/failed counts, failed last 24h/7d, orgs affected by failures in last 7d, 5 most recent failures (event type, Stripe event ID, timestamp).
- **Storage pressure:** Orgs over limit, orgs near limit (≥90%), total platform storage used, top-10 per-org progress bars with used/limit/pct.
- **At-risk orgs:** Past-due count, unpaid count, orgs with 3+ webhook failures in 7d.
- **Platform summary:** Total orgs, total users, active subscriptions, Stripe-linked orgs.
- **Drilldown links:** Org Health, Org Subscriptions, Revenue, Feature Usage.
- **Nav:** Sidebar "System Performance" link after Feature Usage, before Settings separator.
- **Tests:** `SystemPerformanceDashboardTest` — 13 tests covering access control (platform admin, tenant user, guest), readiness checks, queue health counts, webhook health counts and filtering, at-risk billing/webhook counts, storage zero-state, platform summary, full zero-state, queue zero-state, readiness driver info.
- **QA:** Visit `/admin/system-performance`. Verify summary cards, readiness detail, queue/webhook sections, storage pressure bars, risk summary. Insert failed_jobs and webhook failures → counts update. Zero state → all zeros, clean UI.

## Platform: Org Health Dashboard

- **Page:** `/admin/organizations/health` (route: `platform.organizations.health`)
- **Purpose:** Read-only operational health overview for platform operators. Surfaces billing, seat, storage, and webhook health per tenant org.
- **Health signals:** Plan/status, Stripe linkage, seat usage vs limit, storage usage vs limit, webhook failure count (7d), computed health flags (healthy/warning/critical).
- **Health flag rules:** Billing critical (past_due/unpaid), billing warning (canceled/incomplete), seats critical (at limit), seats warning (>=90%), storage critical (over limit), storage warning (>=90%), webhooks critical (>=3 failed/7d), webhooks warning (any failed/7d).
- **Filters:** Search (name/slug), billing status, health state (healthy/warning/critical).
- **Drilldown links:** Org Subscriptions (pre-filtered), tenant billing (new tab).
- **N+1 prevention:** Batch-loads seats, storage, and webhook stats per page.
- **Nav:** Sidebar "Org Health" link replaces placeholder "System Health" stub.
- **Tests:** `OrgHealthDashboardTest` — access control (platform admin, tenant user, guest), health fields, flags, fallback (no subscription, no webhooks), filters, pagination.
- **QA:** Visit `/admin/organizations/health`. Verify summary cards, table rows, health badges. Filter by status/health. Click Subscriptions drilldown. Confirm pagination with >20 orgs.
