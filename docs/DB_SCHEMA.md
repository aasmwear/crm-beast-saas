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
| clients | id, organization_id, company_name, fronter_id, closer_id, ... | FK, cascade | (org_id, assigned_account_manager_id) |
| client_contacts | id, client_id, name, email, phone | via client | client_id |

### Projects

| Table | Key Columns | organization_id | Indexes |
|-------|-------------|-----------------|---------|
| projects | id, organization_id, client_id, title, project_manager_id | FK, cascade | (org_id, client_id), (org_id, project_code) unique |
| project_messages | id, organization_id, project_id, author_id | FK, cascade | (org_id, project_id) |
| project_files | id, project_id, user_id | via project | — |
| project_user | id, project_id, user_id | via project | — |
| comments | id, organization_id, user_id, morphs(commentable) | FK, cascade | (org_id, created_at) |

### Tasks

| Table | Key Columns | organization_id | Indexes |
|-------|-------------|-----------------|---------|
| tasks | id, organization_id, project_id, assignees (json) | FK, cascade | (org_id, project_id) |

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
| invoices | id, organization_id, client_id, project_id, number | FK, cascade | (org_id, client_id) |
| invoice_items | id, invoice_id | via invoice | — |
| subscriptions | (Cashier) | — | — |
| subscription_items | (Cashier) | — | — |
| organization_subscriptions | id, organization_id, plan_key, status, trial_ends_at, current_period_ends_at, seats_included, seat_limit | FK, unique org | plan_key, status |
| organization_addons | id, organization_id, addon_key, quantity, value_int, mode (augment\|set), active, starts_at, ends_at | FK, cascade | (org_id, addon_key) |

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
| audit_logs | id, organization_id, actor_id, action, entity, entity_id | FK, cascade | (org_id, entity, entity_id) |
| activities | id, organization_id, user_id, morphs(subject), description | FK, cascade | (org_id, created_at) |

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

5. **project_files has no organization_id** — Scoped via project_id only. OK if project is always org-scoped; add index on project_id if missing.

6. **ReportController export** — Uses `client->getAttribute('email')` and `client->getAttribute('phone')` but Client has `primary_contact_email`, `primary_contact_phone`. Export columns likely empty.

7. **client_contacts** — No organization_id; scoped via client. OK if client is always org-scoped.

8. **invoice_items** — No organization_id; scoped via invoice. OK.

9. **Naming mismatch: notifications_center vs notifications** — Migration history shows rename from custom `notifications` to `notification_events`; Laravel `notifications` is separate. Ensure no orphan `notifications_center` table.

10. **Spatie team_id** — Roles can have team_id = null (global) or org id. Ensure all tenant role checks use team_id.

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
