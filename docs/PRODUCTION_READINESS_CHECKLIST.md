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

---

## Other sections

_Add additional checklist sections (Security, Performance, Observability, etc.) as needed. This file initially focuses on billing/entitlements per PR scope._
