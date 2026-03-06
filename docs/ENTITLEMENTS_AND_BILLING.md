# Entitlements & Billing

> Billing & entitlements foundation with Stripe/Cashier self-serve flows. Defines schema, resolution order, seat counting, billing portal access, and invoice UX data model.

---

## Pricing model

- **Base plan:** Each organization has a subscription with a `plan_key` (e.g. `starter`, `pro`, `enterprise`). Plans define default entitlements and `seats_included`.
- **Seats:** Billed (or metered) per **active seat** — tenant app users only; portal users (users with `client_id` set) do not count.
- **Add-ons:** Optional per-org add-ons (e.g. extra `storage_gb`, `api_rpm`) stored in `organization_addons` and applied on top of plan + org overrides.

---

## Definitions

| Term | Definition |
|------|------------|
| **Staff user** | A user in `organization_user` with `users.client_id` IS NULL. Counts toward seat limit. |
| **Portal user** | A user with `client_id` set (client-facing login). Excluded from seat count; never blocked by seat enforcement. |
| **Seat** | Same as staff user — a billable/limited seat. |
| **Add-on** | A row in `organization_addons` with `addon_key` matching FeatureCatalog (e.g. `storage_gb`, `api_rpm`). Mode: **augment** (adds to base) or **set** (overrides with `value_int`). |
| **Entitlement** | A resolved capability or limit for an org: boolean (e.g. `attendance`, `api_access`) or number (e.g. `storage_gb`, `api_rpm`). |

---

## Resolution order (single source of truth)

Entitlements are resolved by `App\Services\Billing\EntitlementsService` in this order:

1. **Plan defaults** — From `App\Support\PlanCatalog` using the org’s **canonical plan key** (see below).
2. **Org overrides** — From `organization_features.features` (JSON). Overrides plan defaults for that org.
3. **Add-ons** — From `organization_addons` (active only). Two modes: **augment** (adds to base) or **set** (overrides with `value_int`; multiple set for same key → highest wins). Booleans are not modified by add-ons.

Result: one resolved map of `key => bool|int` per org, cached per-request.

---

## Schema (this PR)

### `organization_subscriptions`

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | PK |
| organization_id | FK, unique | One subscription per org |
| plan_key | string, indexed | `starter`, `pro`, `enterprise` |
| status | string, indexed | `trialing`, `active`, `past_due`, `canceled` |
| trial_ends_at | timestamp nullable | |
| current_period_ends_at | timestamp nullable | |
| seats_included | int | From plan at creation time |
| seat_limit | int nullable | Optional hard cap |
| created_at, updated_at | timestamps | |

Stripe fields (e.g. `stripe_id`, `stripe_price`) can be added in a later PR.

### `organization_addons`

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | PK |
| organization_id | FK | Tenant-scoped |
| addon_key | string, indexed | Matches FeatureCatalog key (e.g. `storage_gb`, `api_rpm`) |
| quantity | int default 1 | Generic quantity; used when value_int null |
| value_int | int nullable | Numeric value (augment: delta; set: override) |
| **mode** | string default `augment` | `augment` (adds to base) or `set` (overrides; multiple set → highest wins) |
| active | boolean default true | |
| starts_at, ends_at | timestamp nullable | Optional window |
| created_at, updated_at | timestamps | |

### Plan key source of truth

- **Canonical:** `organization_subscriptions.plan_key`.
- **Fallback:** If no subscription row, use `organizations.plan` (legacy).
- **Final fallback:** `starter` (PlanCatalog::defaultPlanKey).
- `organizations.plan` is legacy/display-only; do not write for entitlements.

---

## Add-on modes

| Mode | Behavior |
|------|----------|
| **augment** | Adds `value_int` (or `quantity`) to the current value. Multiple augment addons for the same key are summed. |
| **set** | Replaces the value with `value_int`. Multiple set addons for the same key → **highest** `value_int` wins. |

**Example:** Plan storage_gb=5. Addon A: augment +10 → 15. Addon B: set 600 → 600. Addon C: set 200 (same key) → 600 (highest).

---

## Billing Admin Read Model

The tenant Billing page (`/org/{org}/billing`) exposes a read-only view of canonical billing data. Authorized users (`billing.view`) see:

| Data | Source | Description |
|------|--------|-------------|
| **subscription** | `organization_subscriptions` (or fallbacks) | `plan_key`, `status`, `trial_ends_at`, `current_period_ends_at`, `seats_included`, `seat_limit` |
| **seats** | `SeatCounter` | `active_count`, `can_add_seat` |
| **entitlements** | `EntitlementsService::forOrg()` | Resolved map of key → bool\|int |
| **addons** | `organization_addons` | List of add-on rows: `addon_key`, `mode`, `quantity`, `value_int`, `active`, `starts_at`, `ends_at` |

Plan key resolution follows the same rules as `EntitlementsService`: subscription.plan_key → organizations.plan → default (starter). Stripe-specific UI (Manage subscription, Upgrade, invoices) remains unchanged and is separate from this read model.

---

## Internal control plane (write model)

Tenant admins with `billing.update` can change plan and manage add-ons via the Billing page.

### Routes

| Method | Route | Action |
|--------|-------|--------|
| PATCH | `/org/{org}/billing/plan` | Update plan_key |
| POST | `/org/{org}/billing/addons` | Create add-on |
| PATCH | `/org/{org}/billing/addons/{addon}` | Update add-on |
| DELETE | `/org/{org}/billing/addons/{addon}` | Deactivate add-on (active=false) |

### Write model rules

- **Plan update:** Creates `organization_subscriptions` row if missing; updates `plan_key` and `seats_included` from PlanCatalog. `organizations.plan` remains legacy fallback only.
- **Add-on create:** Validates `addon_key` against FeatureCatalog numeric keys (`storage_gb`, `api_rpm`); `mode` must be `augment` or `set`.
- **Add-on deactivation:** Uses `active=false` (soft deactivation), not hard delete. Entitlements resolution ignores inactive add-ons.
- **Org scoping:** All routes use tenant-scoped route model binding; cross-org mutations return 404.

---

## Stripe subscription initiation (first monetization step)

Stripe is now used to initiate paid subscriptions, while `organization_subscriptions` remains the app's canonical billing state.

### Permission and route

| Method | Route | Permission | Purpose |
|--------|-------|------------|---------|
| POST | `/org/{org}/billing/checkout` | `billing.manage` | Start/switch Stripe-backed subscription for a selected `plan_key` |
| GET | `/org/{org}/billing/portal` | `billing.manage` | Open Stripe-hosted billing portal for customer self-serve |

### Plan mapping

- Internal plan keys remain unchanged (`starter`, `pro`, `enterprise`).
- Stripe mapping is configured in `config/billing.php` under `stripe_prices`.
- Missing/empty plan price mapping returns a safe 422 response (`This plan is not available for Stripe self-serve subscription yet.`).

### Initiation flow

1. Validate `plan_key` against `PlanCatalog`.
2. Require Stripe config (`cashier.secret` and `services.stripe.key`).
3. Create/link Stripe customer for the org (`Organization` Cashier `Billable` flow).
4. Start or switch subscription:
   - Existing active/trialing default subscription: `swap(...)` (no proration).
   - No active subscription + no default payment method: create Stripe Checkout session and return URL.
   - No active subscription + default payment method exists: create subscription directly.
5. Upsert canonical `organization_subscriptions` with `plan_key`, `status`, `seats_included`, and period/trial fields when available.
6. Audit log written as `subscription_initiated` on `subscription`.

### Billing portal + invoice history UX

- The tenant billing page now exposes a **Manage billing** action for users with `billing.manage`.
- Portal access is guarded by:
  - Stripe runtime config presence (`cashier.secret`, `services.stripe.key`)
  - Existing Stripe customer linkage (`organizations.stripe_id`)
- Safe failures redirect back to Billing with a user-facing error flash instead of throwing.
- Invoice history is sourced from Cashier invoices and normalized for UI:
  - `id`, `number`, `currency`, `status`
  - `total_minor`, `subtotal_minor`
  - `created_at`, `period_start`, `period_end`
  - Optional Stripe-hosted links: `hosted_invoice_url`, `invoice_pdf`, `receipt_url`
- Missing Stripe fields are tolerated; UI remains readable when links/amounts/dates are absent.

### Canonical state vs Stripe state

- **Canonical in-app:** `organization_subscriptions` (`plan_key`, `status`, seat metadata, period/trial dates).
- **External system of record for payment events:** Stripe.
- Subscription initiation writes a provisional canonical state immediately; webhook reconciliation hardens canonical state afterward.

---

## Stripe webhook sync (canonical reconciliation)

Stripe webhook processing runs on `POST /webhooks/stripe` and reconciles external lifecycle events back into `organization_subscriptions`.

### Idempotency

- Every Stripe event is recorded in `stripe_webhook_events` with unique `stripe_event_id`.
- Duplicate deliveries are acknowledged (`200 OK`) and skipped without re-applying state mutations.
- Event processing status is tracked (`received`, `processed`, `failed`) with timestamps/notes.

### Event coverage

- `customer.subscription.created`
- `customer.subscription.updated`
- `customer.subscription.deleted`
- `checkout.session.completed` (subscription mode only)
- `invoice.payment_succeeded`
- `invoice.payment_failed`

### Reconciliation rules

1. **Tenant mapping:** Stripe `customer` is mapped to org via `organizations.stripe_id`.
2. **Plan mapping:** Stripe `price.id` is mapped back to internal `plan_key` using `config/billing.php` (`stripe_prices`).
3. **Unknown price IDs:** Do not crash; status/period sync still runs using safe plan fallback (existing canonical plan or default), and mismatch is logged/audited.
4. **Canonical upsert fields:** `plan_key`, `status`, `current_period_ends_at`, `trial_ends_at`, `seats_included`.
5. **Legacy field policy:** `organizations.plan` remains untouched (legacy fallback only).

### Audit behavior for webhooks

- `subscription_status_changed` when canonical status transitions.
- `subscription_canceled` for deletion lifecycle.
- `payment_succeeded` / `payment_failed` for invoice payment outcomes.
- `webhook_plan_mismatch` when Stripe price cannot be mapped to an internal plan key.

### Audit behavior

| Action | Entity | Action string | Changes logged |
|--------|--------|---------------|----------------|
| Plan change | subscription | plan_changed | from, to |
| Add-on create | addon | created | addon_key, mode, value_int |
| Add-on update | addon | updated | addon_key, changed keys |
| Add-on deactivate | addon | deactivated | addon_key |

---

## Code references

- **Plan catalog:** `App\Support\PlanCatalog` — defines `starter`, `pro`, `enterprise` and their default entitlements. Enterprise can be overridden via `config/billing.php`.
- **Resolver:** `App\Services\Billing\EntitlementsService` — `forOrg(Organization $org)`, `enabled(string $key, Organization $org)`, `value(string $key, Organization $org)`.
- **Seat counting:** `App\Services\Billing\SeatCounter` — `countActiveSeats(Organization $org)`, `canAddSeat(Organization $org)`, `assertCanAddSeat(Organization $org)` (throws `ValidationException` 422 if at limit). Used by `Organization::canAddSeat()`.

---

## Seat enforcement

When adding **staff users** (not portal users), controllers call `SeatCounter::assertCanAddSeat($organization)` before attaching the user to the org. If at limit, a `ValidationException` is thrown with message:

> Seat limit reached for your plan. Upgrade or add seats to invite more users.

**Enforcement points:**

| Flow | Controller | Enforced? |
|------|------------|-----------|
| Create employee (HRM) | `HRMController::store` | Yes |
| Tenant registration (first owner) | `RegisteredTenantController::store` | Yes |
| Legacy user registration | `RegisteredUserController::store` | Yes |
| Enable portal access (client contact) | `PortalAccessController::store` | No (portal users excluded) |

**Activation / role changes:** `UserManagementController::update` only changes roles; it does not add users. No activation endpoint that converts portal→staff exists; if added later, apply `assertCanAddSeat` when the action would add an active staff seat.
- **Feature catalog:** `App\Support\FeatureCatalog` — keys and types for UI/validation; aligned with plan entitlements (`attendance`, `sms`, `api_access`, `storage_gb`, `api_rpm`).

---

## Next hardening (post-webhook baseline)

1. **Seat limits from Stripe quantity:** decide whether `seat_limit` should sync from Stripe quantity or remain manual override.
2. **Extended lifecycle coverage:** add `customer.subscription.paused/resumed` and any required edge-status mapping.
3. **Operational replay tooling:** add admin-safe replay/inspect tools for failed webhook events.

---

## Runtime enforcement (incremental)

This PR introduces the first practical runtime limits hooks tied to canonical entitlements.

### 1) API RPM enforcement (live)

- Public API middleware `ThrottleOrgApi` now resolves limit from canonical entitlements:
  - `EntitlementsService::value('api_rpm', $org)`
- Existing limiter key shape is unchanged:
  - `org:{orgId}:key:{apiKeyId}:ip:{ip}`
- If entitlement lookup fails or is missing, middleware safely falls back to `config('api.rate_limit_per_minute')`.
- Unauthorized/invalid API key behavior remains unchanged in `AuthenticateOrganizationApiKey` (`401` before throttling).

### 2) Storage quota read model (foundation)

- New canonical helper: `App\Services\Billing\StorageUsageService`.
- Methods:
  - `currentUsageBytes(Organization $org): int`
  - `currentUsageGb(Organization $org): float`
  - `limitGb(Organization $org): int|float`
  - `isOverLimit(Organization $org): bool`
- Current usage source is intentionally minimal and safe:
  - sums `project_files.size` joined through `projects.organization_id`.
- This is the agreed hook for future upload/file enforcement in controllers.

### 3) Export limit hook (proof pattern)

- Added numeric entitlement key: `exports_per_day`.
- `ReportController::exportCsv` now applies `DailyExportLimitService` before streaming.
- When limit is exceeded, request is blocked with safe `429` response:
  - `Daily export limit reached for your plan.`
- Scope intentionally limited to one export path to prove pattern without broad refactor.

---

## Multi-tenancy

All billing and entitlement data is scoped by `organization_id`. Queries must always filter by the current tenant org.
