# CRM Beast — QA Release Playbook

## API Smoke Test

### /api/ping (Organization API Key Auth)

1. **Create a key:** `/org/{org}/settings` → API Keys tab → Create key (name e.g. "Smoke test") → Copy the plaintext token (starts with `crmb_`).
2. **Valid token:** `curl -H "Authorization: Bearer <token>" https://<host>/api/ping`
   - Expect 200, JSON with `ok: true`, `organization` (id, slug, name), `api_key_prefix`, `timestamp`.
   - Verify response does NOT contain the raw token.
3. **Missing header:** `curl https://<host>/api/ping` → 401.
4. **Malformed header:** `curl -H "Authorization: Basic x" https://<host>/api/ping` → 401.
5. **Revoke key:** Revoke in UI, retry valid token → 401.
6. **Cross-tenant:** Create key for org A, confirm response returns org A only (not org B).

### Rate limit (429)

7. **Within limit:** Make 2–3 requests with valid token → all 200.
8. **Exceed limit:** Set `API_RATE_LIMIT_PER_MINUTE=3`, make 4 requests → 4th returns 429 with `message: "Too many requests."`, `code: "rate_limited"`.
9. **No leak:** 429 response must not reveal org/key; same generic format for any throttled request.

---

## Seat limit (staff users)

1. **Setup:** Create org with subscription at 2 seats (e.g. `organization_subscriptions.seats_included = 2`). Add 2 staff users (no `client_id`) to the org.
2. **Block staff add:** As Owner/Admin, go to `/org/{org}/hrm` → Add employee. Submit form with new email.
   - Expect 422 with validation error: `Seat limit reached for your plan. Upgrade or add seats to invite more users.`
   - User must NOT be created.
3. **Allow portal add:** With same org at seat limit, go to a client → Contacts → Enable portal access for a contact with valid email.
   - Expect success; portal user created and attached. Portal users do not count toward seat limit.
4. **Tenant scoping:** Org A at limit; Org B under limit. As admin of Org B, add employee to Org B → success. Org B is independent.

---

## Stripe webhook sync smoke test

1. **Prepare Stripe config:** Set `STRIPE_SECRET`, `STRIPE_WEBHOOK_SECRET`, and plan price IDs in `config/billing.php` env values.
2. **Invalid signature test:** POST to `/webhooks/stripe` with bad `Stripe-Signature`.
   - Expect `400` and no canonical subscription mutation.
3. **Duplicate delivery test:** Send same Stripe event (`id` identical) twice.
   - First request processes normally (`200`).
   - Second request returns `200` and does not re-apply changes.
4. **Subscription updated:** Send `customer.subscription.updated` for known Stripe customer + known price ID.
   - Expect `organization_subscriptions` status/period fields synced.
5. **Subscription deleted:** Send `customer.subscription.deleted`.
   - Expect canonical status `canceled`.
6. **Payment failed:** Send `invoice.payment_failed`.
   - Expect canonical status transitions to `past_due` (unless already terminal by design).
7. **Checkout completed (subscription mode):** Send `checkout.session.completed` with `mode=subscription` and plan metadata.
   - Expect canonical `plan_key/status` upsert.
8. **Verify idempotency table:** `stripe_webhook_events` contains one row per unique Stripe event ID with processed status.

---

## Billing portal + invoice history QA

1. **Permission gate (portal):** Log in as user without `billing.manage` and open `/org/{org}/billing/portal`.
   - Expect `403`.
2. **Stripe config missing:** Unset Stripe runtime config (`STRIPE_SECRET` and/or `STRIPE_KEY`) and click **Manage billing**.
   - Expect redirect back to Billing with safe flash error; no crash.
3. **No Stripe customer:** Keep Stripe keys configured but use org with no `stripe_id`. Click **Manage billing**.
   - Expect redirect back with safe flash error about missing billing customer.
4. **Happy path portal redirect:** Use org with `stripe_id` and configured Stripe keys. Click **Manage billing**.
   - Expect redirect to Stripe billing portal URL.
5. **Invoice table render:** Open `/org/{org}/billing` for org with Cashier invoices.
   - Expect invoice rows show number, date, total, and status.
   - Action links render only when URLs exist: **View invoice**, **Download PDF**, **View receipt**.
6. **Empty state:** Org with no Stripe invoices should show Billing page normally with "No invoices yet."
7. **Verification commands:** Run `./vendor/bin/sail artisan test tests/Feature/Billing/BillingPortalAndInvoicesTest.php`, then full `./vendor/bin/sail artisan test`, and `./vendor/bin/sail npm run build`.

---

## Health & Readiness Smoke Test

1. **Liveness (`/up`):** `curl http://localhost:8080/up` → 200 with HTML.
2. **Readiness (`/_readiness`):** `curl http://localhost:8080/_readiness` → 200 JSON with `"status":"healthy"`, all three checks (`database`, `cache`, `queue`) show `"ok":true`.
3. **Degraded state:** Stop PostgreSQL (`sail stop pgsql`), then `curl http://localhost:8080/_readiness` → 503 JSON with `"status":"degraded"`, `database.ok` is `false`. Restart PostgreSQL afterward.
4. **Root health:** `curl http://localhost:8080/` → 200 `OK`.

---

## Webhook Failure Observability Smoke Test

1. **Invalid signature logging:** POST to `/webhooks/stripe` with a fake `Stripe-Signature` header.
   - Expect 400 response.
   - Check `storage/logs/laravel.log` for `stripe.webhook.signature_invalid` with `ip` field.
2. **Processing failure logging:** Trigger a webhook event that will fail processing (e.g., unknown Stripe customer).
   - Check log for `Stripe webhook processing failed` with `stripe_event_id`, `event_type`, `organization_id` (null if unresolvable), `exception_class`.
   - Check `stripe_webhook_events` table: row exists with `status=failed` and `notes` containing error.
3. **Billing initiation failure:** Attempt checkout with missing Stripe config.
   - Check log for `stripe.subscription.initiation_failed` with org context fields.
4. **Verification:** Run `./vendor/bin/sail artisan test tests/Feature/Observability/`.

---

## Platform Webhook Support Tooling QA

1. **Platform admin access:** Log in as platform admin → Org Subscriptions (`/admin/organizations/subscriptions`).
2. **Webhook column:** Table shows Webhook column with per-org indicators: status badge (OK/Failed/Received/—), last processed relative time, recent failed count when > 0.
3. **Org with processed webhooks:** Create org with `stripe_id`, trigger webhook, then reload page → "OK" badge and relative timestamp (e.g. "5m ago").
4. **Org with no webhook events:** Org never received webhooks → "—" in Webhook column.
5. **Support links:** "View" → platform org detail; "Billing →" (Stripe-linked orgs only) opens `/org/{slug}/billing` in new tab. Customer must log in to see tenant billing.
6. **Verification:** Run `./vendor/bin/sail artisan test tests/Feature/Platform/OrgSubscriptionsOverviewTest.php`.
