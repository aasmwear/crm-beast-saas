# CRM Beast — Integrations Standards

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
