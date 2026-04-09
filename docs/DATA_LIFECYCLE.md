# CRM Beast — Data Lifecycle & Retention Policy

> Defines which tables grow fastest, what to keep hot vs archive, and the rules for future lifecycle jobs.

---

## Hot-Growth Operational Tables

| Table | Growth Pattern | Rows/day (est. 100-user org) | Org-Scoped | Soft Deletes |
|-------|---------------|------------------------------|------------|--------------|
| `audit_logs` | 1 row per audited action (CRUD, settings, billing, imports) | ~200–1000 | Yes | No |
| `activities` | 1 row per project/task/invoice event | ~100–500 | Yes | No |
| `notifications` | 1 row per user-facing notification | ~50–300 | Yes | No |
| `notification_events` | 1 row per org-level event | ~20–100 | Yes | No |
| `stripe_webhook_events` | 1 row per Stripe webhook | ~5–50 | Yes (nullable) | No |
| `failed_jobs` | 1 row per job that exhausts retries | ~0–5 | No | No |
| `attendance` | 1 row per clock-in session per user per day | ~100 | Yes | Yes |
| `custom_field_values` | 1 row per entity × custom field | Tied to entity count | Via field→org | No |
| `comments` | 1 row per comment on project/task | ~10–100 | Yes (`organization_id`) | No |

---

## Retention Categories

### Category A: Hot (keep forever in primary table)

Data that users actively query, display in dashboards, or need for real-time operations.

| Table | Reason |
|-------|--------|
| `clients`, `projects`, `tasks` | Core business entities |
| `invoices`, `invoice_items` | Financial records, legal retention |
| `attendance` | HR/payroll; regulatory retention in many jurisdictions |
| `custom_field_values` | User-defined data tied to live entities |
| `organizations`, `users`, roles/permissions | Tenant identity and access |

### Category B: Warm (archive after window)

Operationally useful for recent period; historical rows can move to archive tables or cold storage.

| Table | Hot Window | Archive Strategy |
|-------|-----------|-----------------|
| `audit_logs` | 90 days | Move rows with `created_at` older than 90 days to `audit_logs_archive` via `lifecycle:archive-audit-logs --execute` |
| `activities` | 90 days | Move rows with `created_at` older than 90 days to `activities_archive` via `lifecycle:archive-activities --execute` |
| `notifications` | 180 days (read rows) | Laravel `notifications` table: **`lifecycle:prune-notifications`** deletes only rows with **`read_at` set** and **`created_at`** older than 180 days; **unread rows are never pruned** |
| `notification_events` | 60 days | **`lifecycle:prune-notification-events`** deletes rows with **`created_at`** older than 60 days (org-level feed) |
| `comments` | 180 days | Move rows with `created_at` strictly older than 180 days to **`comments_archive`** via **`lifecycle:archive-comments --execute`**. Hot `comments` is what project pages read; archived rows are retained for compliance/history but **not** shown in UI until a future read path exists. |

### Category C: Cold (safe to prune after window)

Data that serves no business purpose after a defined period. Can be deleted outright.

| Table | Prune Window | Notes |
|-------|-------------|-------|
| `stripe_webhook_events` | 90 days | Stripe retains events on their side; local copy is for debugging |
| `failed_jobs` | 30 days | Laravel `queue:prune-failed` can handle this |
| `job_batches` | 30 days | Laravel `queue:prune-batches` can handle this |
| `sessions` | 7 days | Stale sessions (if using database driver) |

---

## Operator Rules

1. **Never delete audit_logs without archiving first.** Audit trail is a compliance asset.
2. **Never prune attendance in bulk.** HR/payroll data has legal retention requirements that vary by jurisdiction (typically 3–7 years).
3. **Never hard-delete soft-deleted clients/projects/tasks within 30 days.** Allow recovery window.
4. **Webhook events older than 90 days are safe to prune.** Stripe Dashboard is the source of truth.
5. **Failed jobs older than 30 days are safe to flush.** If not retried within 30 days, the root cause should have been resolved or documented.
6. **Always run lifecycle operations during low-traffic windows.**
7. **Always log lifecycle actions to `audit_logs`** (count of rows affected, table, operation).
8. **Lifecycle jobs must be tenant-scoped** — never operate cross-tenant in a single pass without explicit scoping.
9. **All lifecycle operations must be idempotent** — safe to re-run without side effects.

---

## Future Lifecycle Candidates

| Table | Priority | Strategy |
|-------|----------|----------|
| `notifications` → prune | Done | `lifecycle:prune-notifications` (read + `created_at` age; see mechanisms) |
| `notification_events` → prune | Done | `lifecycle:prune-notification-events` |
| `stripe_webhook_events` → prune | Done | `lifecycle:prune-webhooks` (Phase 2) |
| `failed_jobs` → prune | Done | `lifecycle:prune-failed` (Phase 2) |
| `audit_logs` / `activities` / `comments` archive | Done | Phase 3 — see below |

---

## Current Lifecycle Mechanisms

| Mechanism | Scope | Destructive | Notes |
|-----------|-------|-------------|-------|
| `TenantReaperJob` | Whole org deletion (churn) | Yes | Deletes all org-scoped data in transaction |
| `Client::SoftDeletes` | Individual clients | No | `deleted_at` set; recoverable |
| `Attendance::SoftDeletes` | Individual attendance records | No | `deleted_at` set; recoverable |
| `CustomField cascadeOnDelete` | Field → values | Yes | Values auto-deleted when field definition is removed |
| **`lifecycle:report` command** | Read-only reporting | No | Reports row counts and age distribution per table |
| **`lifecycle:prune-webhooks`** | stripe_webhook_events | Yes (with `--execute`) | Dry-run default; 90d retention; Stripe Dashboard is source of truth |
| **`lifecycle:prune-failed`** | failed_jobs | Yes (with `--execute`) | Dry-run default; 30d retention; uses Laravel failer |
| **`lifecycle:prune-batches`** | job_batches | Yes (with `--execute`) | Dry-run default; 30d retention; uses Laravel batch repo |
| **`lifecycle:prune-notifications`** | `notifications` (Laravel DB) | Yes (with `--execute`) | Dry-run default; **read** rows only; `created_at` older than 180d; **`--organization=`** optional |
| **`lifecycle:prune-notification-events`** | `notification_events` | Yes (with `--execute`) | Dry-run default; 60d on `created_at`; **`--organization=`** optional |
| **`lifecycle:archive-audit-logs`** | audit_logs → audit_logs_archive | Moves rows (with `--execute`) | Dry-run default; 90d retention; batched; idempotent |
| **`lifecycle:archive-activities`** | activities → activities_archive | Moves rows (with `--execute`) | Dry-run default; 90d retention; batched; idempotent |
| **`lifecycle:archive-comments`** | comments → comments_archive | Moves rows (with `--execute`) | Dry-run default; **180d** retention on `created_at`; batched; idempotent; **`--organization=`** optional |

### Hot vs archive (Phase 3)

| Table | Role |
|-------|------|
| `audit_logs` | **Hot** — recent operational audit trail; app reads/writes here |
| `audit_logs_archive` | **Archive** — same columns as hot plus `archived_at`; no FKs to org/user (plain IDs); indexed by `organization_id`, `created_at`, `entity`+`entity_id` |
| `activities` | **Hot** — recent activity feed |
| `activities_archive` | **Archive** — same columns as hot plus `archived_at`; indexed by `organization_id`, `created_at`, `subject_type`+`subject_id` |
| `comments` | **Hot** — discussion comments on projects/tasks; app reads/writes here |
| `comments_archive` | **Archive** — same columns as hot plus `archived_at`; plain `organization_id` (no FK); indexed by `organization_id`+`created_at`, `commentable_type`+`commentable_id`, `archived_at` |

Tenant UIs and APIs continue to query **hot** tables only until a later phase adds unified or archive-aware reads.

---

## Retention Config

Application-level retention windows are defined in `config/lifecycle.php`. The `lifecycle:report` command reads them to flag tables that exceed their retention window (for `notifications`, “aged out” counts **read** rows with `created_at` before the cutoff only). The `lifecycle:prune-*` commands use them for cutoff date computation. The `lifecycle:archive-*` commands use the same **retention_days** and **created_at_column** for each warm table.

### Safe Prune Commands (Phase 2)

All prune commands default to **dry-run** (read-only). Use `--execute` to perform deletion:

```bash
# Dry-run: show candidates, no deletion
sail artisan lifecycle:prune-webhooks
sail artisan lifecycle:prune-failed
sail artisan lifecycle:prune-batches
sail artisan lifecycle:prune-notifications
sail artisan lifecycle:prune-notification-events

# Execute: actually delete aged-out rows
sail artisan lifecycle:prune-webhooks --execute
sail artisan lifecycle:prune-failed --execute
sail artisan lifecycle:prune-batches --execute
sail artisan lifecycle:prune-notifications --execute
sail artisan lifecycle:prune-notification-events --execute
```

Scheduled runs (in `routes/console.php`): cold prunes daily at **02:00 / 02:05 / 02:10**; **`lifecycle:prune-notifications`** at **02:15** and **`lifecycle:prune-notification-events`** at **02:20** (after the other daily prunes). Ensure `schedule:run` is in cron. Times follow **`APP_TIMEZONE`** unless the entry sets `->timezone(...)` (archive jobs use UTC explicitly).

### Notification / notification_events prunes (warm, delete-only)

- **`lifecycle:prune-notifications`** — Only deletes rows where **`read_at` IS NOT NULL** and **`created_at`** is before the cutoff (**180 days** in config). Unread notifications are never deleted. Optional **`--organization={id}`** scopes to `notifications.organization_id` (rows with `NULL` org id are skipped when this flag is set).
- **`lifecycle:prune-notification-events`** — Deletes rows older than **60 days** on **`created_at`**. Optional **`--organization={id}`** scopes to `notification_events.organization_id`.

### Archive commands (Phase 3)

Dry-run by default; **`--execute`** moves eligible rows in batches (default `--batch=500`). Optional **`--organization={id}`** limits to one tenant for pilot runs.

```bash
sail artisan lifecycle:archive-audit-logs
sail artisan lifecycle:archive-activities
sail artisan lifecycle:archive-comments

sail artisan lifecycle:archive-audit-logs --execute
sail artisan lifecycle:archive-activities --execute --organization=42
sail artisan lifecycle:archive-comments --execute --organization=42
```

**Scheduled runs** (in `routes/console.php`): **`lifecycle:archive-audit-logs`** and **`lifecycle:archive-activities`** run with `--execute` **weekly on Sunday at 03:00 UTC**. **`lifecycle:archive-comments`** runs **weekly on Sunday at 03:30 UTC** (staggered) with `--execute`. Weekly cadence limits load while keeping hot tables bounded; comments use a **180-day** window vs **90 days** for audit/activities. Manual runs without `--execute` stay dry-run. Ensure `schedule:run` is in cron.

Rows must be strictly **older** than the cutoff (`now - retention_days` on `created_at`). Reruns are safe: primary keys are preserved; duplicates in hot (same `id` as archive) are removed in a reconcile pass.

---

## Implementation Roadmap

### Phase 1 (this PR)
- [x] Define retention categories and operator rules (this document)
- [x] Create `config/lifecycle.php` with retention windows
- [x] Create `RetentionPolicy` value object for programmatic access
- [x] Create `lifecycle:report` artisan command (read-only)
- [x] Update architecture/operations docs

### Phase 2 (done)
- [x] `lifecycle:prune-webhooks` — delete `stripe_webhook_events` older than configured window (dry-run default, `--execute` to delete)
- [x] `lifecycle:prune-failed` — prune `failed_jobs` using config retention (dry-run default, `--execute` to delete)
- [x] `lifecycle:prune-batches` — prune `job_batches` using config retention (dry-run default, `--execute` to delete)
- [x] Schedule all three prune commands daily at 02:00 / 02:05 / 02:10 in `routes/console.php`
- [x] All prune commands: dry-run by default; require `--execute` to perform deletion
- [x] `lifecycle:prune-notifications` / `lifecycle:prune-notification-events` — warm-table deletes (see Phase 3 checklist); scheduled 02:15 / 02:20

### Phase 3 (archive foundation — done)
- [x] `audit_logs_archive` + `activities_archive` + `comments_archive` tables (migrations)
- [x] `lifecycle:archive-audit-logs` / `lifecycle:archive-activities` / `lifecycle:archive-comments` (dry-run default, `--execute`, `--batch`, `--organization`)
- [x] `WarmTableArchiver` service — batched move, idempotent, reconcile duplicate hot rows (extended for `comments`)
- [x] Schedule archive commands weekly: audit + activities Sunday **03:00 UTC**; comments Sunday **03:30 UTC** (`--execute`) in `routes/console.php`
- [x] Notification pruning — `lifecycle:prune-notifications` / `lifecycle:prune-notification-events` (dry-run default, `--execute`, scheduled daily 02:15 / 02:20)
- [ ] Soft-delete hard-purge job (30-day grace period)
- [ ] Platform dashboard: data lifecycle health panel
- [ ] Archive restore / unified read path (optional future)
