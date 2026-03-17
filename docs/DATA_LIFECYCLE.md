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
| `comments` | 1 row per comment on project/task | ~10–100 | Yes | No |

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
| `audit_logs` | 90 days | Move rows older than 90 days to `audit_logs_archive` (future) |
| `activities` | 90 days | Move rows older than 90 days to `activities_archive` (future) |
| `notifications` | 60 days | Summarize read notifications older than 60 days; delete after 180 days |
| `notification_events` | 60 days | Same as notifications |
| `comments` | 180 days | Keep with parent entity; archive when project is archived |

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

## Future Archive Candidates (not in this PR)

| Table | Priority | Strategy |
|-------|----------|----------|
| `audit_logs` → `audit_logs_archive` | P1 | Move rows > 90 days; keep summary counts in main table |
| `activities` → `activities_archive` | P1 | Move rows > 90 days; keep recent feed fast |
| `notifications` → prune | P2 | Delete read notifications > 180 days |
| `stripe_webhook_events` → prune | P2 | Delete processed events > 90 days |
| `failed_jobs` → prune | P3 | Laravel built-in `queue:prune-failed --hours=720` |

---

## Current Lifecycle Mechanisms

| Mechanism | Scope | Destructive | Notes |
|-----------|-------|-------------|-------|
| `TenantReaperJob` | Whole org deletion (churn) | Yes | Deletes all org-scoped data in transaction |
| `Client::SoftDeletes` | Individual clients | No | `deleted_at` set; recoverable |
| `Attendance::SoftDeletes` | Individual attendance records | No | `deleted_at` set; recoverable |
| `CustomField cascadeOnDelete` | Field → values | Yes | Values auto-deleted when field definition is removed |
| **`lifecycle:report` command** | Read-only reporting | No | Reports row counts and age distribution per table |

---

## Retention Config

Application-level retention windows are defined in `config/lifecycle.php`. These are advisory — no destructive job uses them yet. The `lifecycle:report` command reads them to flag tables that exceed their retention window.

---

## Implementation Roadmap

### Phase 1 (this PR)
- [x] Define retention categories and operator rules (this document)
- [x] Create `config/lifecycle.php` with retention windows
- [x] Create `RetentionPolicy` value object for programmatic access
- [x] Create `lifecycle:report` artisan command (read-only)
- [x] Update architecture/operations docs

### Phase 2 (next PR)
- [ ] `lifecycle:prune-webhooks` — delete processed `stripe_webhook_events` older than configured window
- [ ] Schedule `queue:prune-failed --hours=720` in `routes/console.php`
- [ ] Schedule `queue:prune-batches --hours=720` in `routes/console.php`
- [ ] Add `--dry-run` / `--execute` flags to prune commands

### Phase 3 (future)
- [ ] `audit_logs_archive` table + migration job
- [ ] `activities_archive` table + migration job
- [ ] Notification pruning job (read + aged out)
- [ ] Soft-delete hard-purge job (30-day grace period)
- [ ] Platform dashboard: data lifecycle health panel
