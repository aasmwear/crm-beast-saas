# Entitlements & Billing

> Billing & entitlements foundation (no Stripe integration in initial PR). Defines schema, resolution order, seat counting, and future webhook plan.

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

## Future: Stripe webhook plan

When Stripe is wired:

1. **Webhook handler** (e.g. `customer.subscription.updated`, `customer.subscription.deleted`) should:
   - Identify the org (e.g. via `metadata.organization_id` or Stripe customer → org mapping).
   - Upsert `organization_subscriptions`: set `plan_key` from product/price metadata, `status` from Stripe status, `current_period_ends_at`, `trial_ends_at`, and `seats_included` from plan catalog or Stripe quantity.
2. **Seat limit:** Optionally sync `seat_limit` from Stripe quantity or keep it as an admin override.
3. **No duplicate logic:** Entitlement resolution stays in `EntitlementsService`; webhook only updates `organization_subscriptions` (and optionally `organization_addons` if sold as Stripe products).

---

## Multi-tenancy

All billing and entitlement data is scoped by `organization_id`. Queries must always filter by the current tenant org.
