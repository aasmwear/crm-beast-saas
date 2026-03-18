# Production Readiness Checklist

> Use this checklist before releasing. Not all items are required for MVP; mark N/A where appropriate.

---

## Billing & Entitlements

- [ ] **Subscription schema** — `organization_subscriptions` and `organization_addons` migrations applied; one subscription per org.
- [ ] **Plan catalog** — PlanCatalog defines starter/pro/enterprise; config/billing.php used for enterprise overrides.
- [ ] **Entitlements resolver** — EntitlementsService resolves in order: plan defaults → organization_features overrides → add-ons. Per-request cache only (no global cache in foundation PR).
- [ ] **Seat counting** — SeatCounter counts only tenant app users (organization_user where users.client_id IS NULL); portal users excluded.
- [ ] **Seat enforcement** — Organization::canAddSeat() / SeatCounter::canAddSeat() available; no UI blocking in foundation PR unless minimal.
- [ ] **Tenant scoping** — All subscription/addon/entitlement queries filtered by organization_id.
- [ ] **Tests** — EntitlementsResolutionTest and SeatCounterTest pass; tenant isolation verified.
- [ ] **Stripe (future)** — Webhook handler to sync organization_subscriptions from Stripe (customer.subscription.*); no Stripe code in foundation PR.
- [ ] **Docs** — ENTITLEMENTS_AND_BILLING.md describes pricing model, resolution order, and webhook plan.
- [ ] **Billing semantics stability** — Add-on modes (augment vs set), plan key canonical (subscription first, org.plan fallback), set conflict rule (highest wins).
- [ ] **API runtime limit enforcement** — `ThrottleOrgApi` uses canonical `api_rpm` entitlement per org with safe config fallback.
- [x] **Storage quota read model** — `StorageUsageService` reports usage/limit/over-limit from canonical entitlements and known storage sources.
- [x] **Storage quota enforcement** — `ProjectFileController::store` blocks uploads when projected usage would exceed `storage_gb` entitlement (422 with "Storage limit reached for your plan.").
- [ ] **Export quota hook** — `ReportController::exportCsv` enforces `exports_per_day` limit via `DailyExportLimitService`.
- [ ] **Tests** — ApiRpmEntitlementTest, StorageUsageServiceTest, and ExportLimitEnforcementTest pass.

---

## Observability & Operations

- [x] **Liveness endpoint** — `GET /up` returns 200 (Laravel built-in).
- [x] **Readiness endpoint** — `GET /_readiness` verifies DB, cache, queue config. Returns 200/503 JSON.
- [x] **Readiness protection** — Endpoint unauthenticated by design; document reverse-proxy restriction for production.
- [x] **Webhook failure logging** — `WebhookController` logs `stripe_event_id`, `event_type`, `organization_id` (when resolvable), `error`, `exception_class` on processing failures.
- [x] **Webhook idempotency** — `stripe_webhook_events` table stores every received event with status tracking.
- [x] **Billing initiation logging** — Checkout and portal failures log `organization_id`, `plan_key`, `stripe_customer_id`, `exception_class`.
- [x] **Invoice fetch logging** — Cashier invoice fetch failures logged with org/customer context (non-fatal).
- [x] **Failed jobs table** — `failed_jobs` migration in place; driver `database-uuids`; standard `queue:failed` / `queue:retry` commands available.
- [x] **Runbook** — `docs/OBSERVABILITY_AND_RUNBOOK.md` covers webhook triage, billing failures, export limits, API rate-limits, failed jobs, log reference.
- [ ] **Alerting** — No external alerting integration yet (Sentry/Bugsnag/PagerDuty). Log-based monitoring only.
- [ ] **Log aggregation** — No centralized log platform yet. Relies on `storage/logs/laravel.log` + database audit tables.
- [ ] **Uptime monitoring** — No external uptime monitor configured. `/_readiness` endpoint ready for integration.
- [ ] **Queue worker monitoring** — No worker health/heartbeat check. Queue driver is `database`; workers must be managed externally (Supervisor/systemd).
- [x] **Platform org billing overview** — Read-only `/admin/organizations/subscriptions` for Super Admin / platform staff. Shows plan, status, Stripe linkage, seats, add-ons, entitlements. Search and filter by status/plan.
- [x] **Platform webhook support indicators** — Org Subscriptions page shows per-org webhook status (OK/Failed), last processed timestamp, recent failed count (7d). `stripe_webhook_events.organization_id` stores org when resolvable. Support links: platform org detail, tenant billing (new tab).
- [x] **Platform-admin manual billing overrides** — Super Admin/Support can override plan_key, seat_limit, and add-ons from Org Subscriptions page. Internal operator controls; do **not** mutate Stripe. All actions audited. Changes reflected immediately in entitlements and tenant billing read model.
- [x] **Platform Revenue / MRR Dashboard** — Read-only `/admin/revenue` for platform operators. Estimated MRR from canonical plan data (active+trialing orgs, Pro=$79/mo, Enterprise excluded). Subscription status counts, plan distribution, total seats, Stripe linkage. Drilldown links to Org Health, Org Subscriptions, Past Due. Feature-tested for access control and metric accuracy.
- [x] **Platform Org Health Dashboard** — Read-only `/admin/organizations/health` for platform operators. Surfaces billing status, seat usage, storage usage, webhook failure indicators, and computed health flags (healthy/warning/critical) per tenant org. Batch-loads to avoid N+1. Filters by search, billing status, health state. Drilldown links to Org Subscriptions and tenant billing. Feature-tested for access control and data integrity.

- [x] **Platform System Performance Dashboard** — Read-only `/admin/system-performance` for platform operators. Surfaces live readiness snapshot (DB, cache, queue), failed jobs summary (24h/7d/total + recent), webhook reliability (processed/failed, orgs affected, recent failures), storage pressure (orgs over/near limit with progress bars), at-risk org counts (billing + webhook), platform summary. Drilldown links to all other operator dashboards. Feature-tested for access control and metric accuracy.

- [x] **Platform Feature Usage Dashboard** — Read-only `/admin/feature-usage` for platform operators. Surfaces module adoption (clients, projects, tasks, attendance, invoices) and billing setup metrics. Per-module: orgs_with_any and total_records. Summary: orgs using any module, avg modules/org. Billing: Stripe-linked, subscriptions, active add-ons. Drilldown links to Org Health, Org Subscriptions, Revenue. Feature-tested for access control and metric accuracy.

---

---

## Data Lifecycle & Retention

- [x] **Retention policy documented** — `docs/DATA_LIFECYCLE.md` defines hot/warm/cold categories, retention windows, operator rules, and implementation roadmap.
- [x] **Retention config** — `config/lifecycle.php` declares per-table retention windows (audit_logs 90d, activities 90d, stripe_webhook_events 90d, failed_jobs 30d, etc.).
- [x] **Lifecycle report command** — `php artisan lifecycle:report` shows row counts and aged-out candidates. Read-only, non-destructive.
- [x] **RetentionPolicy service** — Value object for programmatic access to retention config (cutoff dates, category checks).
- [x] **Webhook event pruning** — `lifecycle:prune-webhooks` (dry-run default, `--execute` to delete); scheduled daily.
- [x] **Failed jobs scheduled pruning** — `lifecycle:prune-failed` (dry-run default, `--execute`); scheduled daily.
- [x] **Queue batches pruning** — `lifecycle:prune-batches` (dry-run default, `--execute`); scheduled daily.
- [ ] **Audit log archival** — `audit_logs_archive` table + migration job (Phase 3).
- [ ] **Activity archival** — `activities_archive` table + migration job (Phase 3).
- [ ] **Notification pruning** — Delete read notifications > 180 days (Phase 3).

---

## Other sections

_Add additional checklist sections (Security, Performance, etc.) as needed._
