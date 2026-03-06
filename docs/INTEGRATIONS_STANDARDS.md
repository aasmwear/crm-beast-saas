# CRM Beast — Integrations Standards

## Stripe Billing (Cashier)

### Scope of current integration

- Implemented: Stripe customer creation/linking + subscription initiation from tenant billing page.
- Implemented: plan-key to Stripe price mapping via `config/billing.php` (`stripe_prices`).
- Implemented: webhook reconciliation of subscription lifecycle into canonical `organization_subscriptions` with idempotency.
- Implemented: Stripe billing portal redirect (`/org/{org}/billing/portal`) for `billing.manage` users.
- Implemented: Billing page invoice history normalization from Cashier invoice data and safe Stripe-hosted links (hosted invoice, PDF, receipt when available).
- Not yet implemented: dunning/retry workflows, usage metering expansion, major portal UI redesign.

### Configuration

Required runtime config:

- `STRIPE_KEY`
- `STRIPE_SECRET`
- `STRIPE_PRICE_STARTER_MONTHLY` (optional)
- `STRIPE_PRICE_PRO_MONTHLY` (recommended)
- `STRIPE_PRICE_ENTERPRISE_MONTHLY` (optional/manual by default)

If Stripe key/secret or plan price mapping is missing, billing initiation returns a safe 422 response and the UI shows Stripe as unavailable.

Portal access also requires:

- `cashier.secret` and `services.stripe.key`
- Existing Stripe customer on organization (`organizations.stripe_id`)

If requirements are missing, portal action redirects back with a safe error message.

### Canonical model rule

- Stripe is the external billing processor.
- CRM Beast canonical enforcement must continue to use `organization_subscriptions` (`plan_key`, `status`, seats/period fields).
- Subscription initiation updates canonical state immediately; webhook sync reconciles and hardens canonical state.

### Webhook handling standards

- Endpoint: `POST /webhooks/stripe`.
- Signature verification is mandatory via `STRIPE_WEBHOOK_SECRET`.
- Event idempotency is mandatory via unique `stripe_event_id` persistence in `stripe_webhook_events`.
- Duplicate events must return `200` and skip reprocessing.
- Unknown Stripe price IDs must not crash processing; log/audit mismatch and continue safe status sync.

## API Keys

### Creating Keys

Keys are created in **Settings → API Keys** (requires `api_keys.create`). The plaintext token is shown once on creation; store it securely. The database stores only `prefix` and `hashed_key` (sha256).

### Calling the API

**Endpoint:** `GET /api/ping`

**Headers:**
```
Authorization: Bearer crmb_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
```

**Success response (200):**
```json
{
  "ok": true,
  "organization": { "id": 1, "slug": "acme", "name": "Acme Corp" },
  "api_key_prefix": "crmb_abc",
  "timestamp": "2026-03-04T12:00:00.000000Z"
}
```

**Unauthorized (401):**
```json
{ "message": "Unauthorized" }
```

**Rate limited (429):**
```json
{ "message": "Too many requests.", "code": "rate_limited" }
```

When `code` is `rate_limited`, back off and retry after the window resets (typically 1 minute). Implement exponential backoff for repeated 429s.

### Storing Keys

- Store in secrets manager or environment variables; never commit to source control.
- Use least-privilege: keys are tenant-scoped; each key accesses only its organization's data.

### Rotation and Revoke

- **Revoke:** Settings → API Keys → Revoke. Sets `revoked_at`; key stops working immediately.
- **Rotation:** Create a new key, update integrations to use it, then revoke the old key.
- Revoked keys cannot be restored.
