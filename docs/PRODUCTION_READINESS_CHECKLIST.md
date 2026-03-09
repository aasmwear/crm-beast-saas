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
- [ ] **Storage quota read model** — `StorageUsageService` reports usage/limit/over-limit from canonical entitlements and known storage sources.
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
- [x] **Platform org billing overview** — Read-only `/admin/organizations/subscriptions` for Super Admin / platform staff. Shows plan, status, Stripe linkage, seats, add-ons, entitlements. Search and filter by status/plan. No edit/override yet.

---

## Other sections

_Add additional checklist sections (Security, Performance, etc.) as needed._
