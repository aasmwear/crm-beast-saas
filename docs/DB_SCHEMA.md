# CRM Beast — Database Schema Reference

**Audit date:** 2025-02  
**Source:** Migrations + code inspection

---

## Tables by Module

### Tenancy

| Table | Key Columns | organization_id | Indexes |
|-------|-------------|-----------------|---------|
| organizations | id, name, slug | — | slug unique |
| organization_domains | id, organization_id, domain | FK | domain unique |
| organization_user | id, organization_id, user_id | FK | (org, user) unique |

### Clients

| Table | Key Columns | organization_id | Indexes |
|-------|-------------|-----------------|---------|
| clients | id, organization_id, company_name, fronter_id, closer_id, ... | FK, cascade | (org_id, assigned_account_manager_id), (org_id, status) |
| client_contacts | id, client_id, name, email, phone | via client | client_id |
| custom_fields | id, organization_id, entity, label, slug, type, options, is_required, sort_order | FK, cascade | (org_id, entity, slug) unique, (org_id, entity) |
| custom_field_values | id, organization_id, custom_field_id, entity_type, entity_id, value_text/number/date/json | FK org + FK field (cascade) | unique (custom_field_id, entity_type, entity_id); **(organization_id, entity_type, entity_id)** for tenant-scoped lookups |

### Projects

| Table | Key Columns | organization_id | Indexes |
|-------|-------------|-----------------|---------|
| projects | id, organization_id, client_id, title, project_manager_id | FK, cascade | (org_id, client_id), (org_id, project_code) unique, (org_id, status), (org_id, project_manager_id) |
| project_messages | id, organization_id, project_id, author_id | FK, cascade | (org_id, project_id) |
| project_files | id, organization_id, project_id, user_id | FK, cascade | organization_id, (org_id, project_id) |
| project_user | id, project_id, user_id | via project | — |
| comments | id, organization_id, user_id, morphs(commentable) | FK, cascade | (org_id, created_at), **(organization_id, commentable_type, commentable_id)** |

### Tasks

| Table | Key Columns | organization_id | Indexes |
|-------|-------------|-----------------|---------|
| tasks | id, organization_id, project_id, assignees (jsonb) | FK, cascade | (org_id, project_id), (org_id, status), GIN(assignees jsonb_path_ops) |

### Attendance

| Table | Key Columns | organization_id | Indexes |
|-------|-------------|-----------------|---------|
| attendance | id, organization_id, user_id, clock_in_at, clock_out_at | FK, NOT NULL | (org_id, user_id, clock_in_at), user_id, clock_in_at, clock_out_at |

### Announcements

| Table | Key Columns | organization_id | Indexes |
|-------|-------------|-----------------|---------|
| announcements | id, organization_id, author_id, scope, title, body | FK, cascade | (org_id, scope) |

### Invoices / Billing

| Table | Key Columns | organization_id | Indexes |
|-------|-------------|-----------------|---------|
| invoices | id, organization_id, client_id, project_id, number, paid_at | FK, cascade | (org_id, client_id) |
| invoice_items | id, invoice_id | via invoice | — |
| subscriptions | (Cashier) | — | — |
| subscription_items | (Cashier) | — | — |
| organization_subscriptions | id, organization_id, plan_key, status, trial_ends_at, current_period_ends_at, seats_included, seat_limit | FK, unique org | plan_key, status |
| organization_addons | id, organization_id, addon_key, quantity, value_int, mode (augment\|set), active, starts_at, ends_at | FK, cascade | (org_id, addon_key) |
| stripe_webhook_events | id, organization_id, stripe_event_id, type, status | FK, nullOnDelete | stripe_event_id unique, organization_id |

### Settings

| Table | Key Columns | organization_id | Indexes |
|-------|-------------|-----------------|---------|
| settings | id, organization_id, key, value | FK, cascade | (org_id, key) unique |

### Notifications

| Table | Key Columns | organization_id | Indexes |
|-------|-------------|-----------------|---------|
| notifications | id (uuid), type, morphs(notifiable), organization_id, data (jsonb) | column + JSON fallback | (org_id, read_at, created_at), (notifiable_id, notifiable_type) |
| notification_events | (legacy, if renamed) | FK | (org_id, type) |

### Activity / Audit

| Table | Key Columns | organization_id | Indexes |
|-------|-------------|-----------------|---------|
| audit_logs | id, organization_id, actor_id, action, entity, entity_id, changes (json) | FK, cascade | (org_id, entity, entity_id) |
| audit_logs_archive | Same as audit_logs + archived_at | unsigned org id (no FK) | (org_id, created_at), (entity, entity_id), archived_at |
| activities | id, organization_id, user_id, morphs(subject), description, properties (json) | FK, cascade | (org_id, created_at), (subject_type, subject_id) |
| activities_archive | Same as activities + archived_at | unsigned org id (no FK) | (org_id, created_at), (subject_type, subject_id), archived_at |

### HRM / Users

| Table | Key Columns | organization_id | Indexes |
|-------|-------------|-----------------|---------|
| users | id, active_organization_id, client_id, manager_id, department_id | active_org FK | — |
| departments | id, organization_id, name, code | FK, cascade | (org_id, code) unique |

### Spatie Permissions

| Table | Key Columns | team_id (= org) | Indexes |
|-------|-------------|-----------------|---------|
| permissions | id, name, guard_name | — | — |
| roles | id, name, guard_name, team_id | nullable/FK | — |
| model_has_permissions | — | — | — |
| model_has_roles | — | — | — |
| role_has_permissions | — | — | — |

---

## Top 10 DB Risks

1. ~~**activities table has no organization_id**~~ — **FIXED:** `organization_id` added, backfilled, indexed. Use `Activity::forOrganization($orgId)`.

2. ~~**comments table has no organization_id**~~ — **FIXED:** `organization_id` added, backfilled, indexed. Use `Comment::forOrganization($orgId)`.

3. ~~**attendance.organization_id is nullable**~~ — **FIXED:** `organization_id` is NOT NULL, backfilled, composite index added.

4. ~~**notifications table**~~ — **FIXED:** `organization_id` column added, backfilled from JSON. Index (org_id, read_at, created_at). Query uses column with JSON fallback.

5. ~~**project_files has no organization_id**~~ — **FIXED:** `organization_id` added, backfilled from projects, indexed, FK-enforced. File queries should scope by `(organization_id, project_id)`.

6. ~~**Task assignee visibility scans are unindexed**~~ — **FIXED:** GIN index added on `tasks.assignees` for `whereJsonContains('assignees', ...)` visibility queries.

7. ~~**Task board can load all tasks without pagination**~~ — **FIXED:** task board now paginates at 50 rows per page with query-string-preserving links.

8. **client_contacts** — No organization_id; scoped via client. OK if client is always org-scoped.

9. **invoice_items** — No organization_id; scoped via invoice. OK.

10. **Naming mismatch: notifications_center vs notifications** — Migration history shows rename from custom `notifications` to `notification_events`; Laravel `notifications` is separate. Ensure no orphan `notifications_center` table.

---
---

## Data Lifecycle

Hot-growth tables have defined retention categories in `config/lifecycle.php`. Run `php artisan lifecycle:report` for current status. See `docs/DATA_LIFECYCLE.md` for full policy.

| Table | Category | Retention Window |
|-------|----------|-----------------|
| `audit_logs` | warm | 90 days → `lifecycle:archive-audit-logs --execute` → `audit_logs_archive` |
| `activities` | warm | 90 days → `lifecycle:archive-activities --execute` → `activities_archive` |
| `notifications` | warm | 180 days on `created_at` (read rows only) → `lifecycle:prune-notifications --execute` |
| `notification_events` | warm | 60 days on `created_at` → `lifecycle:prune-notification-events --execute` |
| `stripe_webhook_events` | cold | 90 days → prune |
| `failed_jobs` | cold | 30 days → prune |
| `comments` | warm | 180 days → archive with parent |

---

# Note: Orphans default to first org only if subject/commentable missing.

## Validation Commands

```bash
# List routes
./vendor/bin/sail php artisan route:list

# Migration status
./vendor/bin/sail php artisan migrate:status

# Run tests
./vendor/bin/sail php artisan test

# Build
./vendor/bin/sail npm run build
```
