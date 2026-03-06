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
