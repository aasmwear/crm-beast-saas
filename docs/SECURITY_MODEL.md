# CRM Beast — Security Model

## Public API Authentication

Public API requests are authenticated via **Authorization: Bearer** tokens stored in `organization_api_keys`.

### Token Format

- Prefix: `crmb_`
- Full token: `crmb_` + 32 random characters (stored plaintext never; returned once on creation)
- Prefix stored in DB: first 8 characters of full token (used for lookup)

### Validation Flow

1. Extract `Authorization: Bearer {token}` header.
2. Require token to start with `crmb_` and be at least 8 characters.
3. Compute prefix = first 8 chars; hash = `sha256(token)`.
4. Lookup `organization_api_keys` by prefix where `revoked_at IS NULL`.
5. Compare stored `hashed_key` with computed hash using `hash_equals()` (timing-safe).
6. On success: bind `app('scoped.organization')`, set request attributes (`org_api_key`, `organization`).
7. On failure: return 401 with generic `{"message":"Unauthorized"}` — do not reveal whether prefix exists.

### Revoked Keys

Keys with `revoked_at` set are excluded from lookup. No distinction in 401 response between invalid/revoked/unknown token.

### last_used_at Throttling

To avoid write storms under high traffic, `last_used_at` is updated only when:
- `last_used_at` is null, or
- `last_used_at` is older than 5 minutes

Otherwise the column is left unchanged.

### Security Rules

- Raw tokens are never stored or logged.
- Audit logs record only prefix and name on create/revoke.
- Tenant context is resolved from the key; no cross-tenant leakage.

## Rate Limiting (Public API)

Requests to `/api/*` routes protected by `org_api_key` are rate limited by `ThrottleOrgApi` middleware:
- **Key:** `org:{organization_id}:key:{api_key_id}:ip:{client_ip}`
- **Limit:** 60 requests per minute (configurable via `API_RATE_LIMIT_PER_MINUTE` env)

Per-organization and per-key scoping prevents one tenant from consuming quota of another. IP is included as a secondary dimension.

**429 response (when exceeded):**
```json
{ "message": "Too many requests.", "code": "rate_limited" }
```

Rate limiting runs after authentication; unauthenticated requests (401) do not consume quota.

## Stripe Webhook Security

Stripe webhook ingress is protected by signature verification and replay-safe idempotency.

### Signature verification

- Webhook endpoint: `POST /webhooks/stripe`.
- CSRF is explicitly exempted for this route in `bootstrap/app.php`.
- Request signatures are verified using Stripe's `Stripe-Signature` header and `STRIPE_WEBHOOK_SECRET`.
- Invalid signature or malformed payload is rejected (`400`/`422`) and not processed.

### Replay and duplicate protection

- Processed events are persisted in `stripe_webhook_events` with unique `stripe_event_id`.
- Duplicate event IDs are acknowledged and ignored safely (no double mutation of canonical billing state).
