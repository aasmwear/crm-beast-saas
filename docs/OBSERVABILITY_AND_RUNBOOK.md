# CRM Beast — Observability & Operations Runbook

> Operational playbook for triaging failures in billing, webhooks, queues, exports, and API rate-limiting.

---

## Health & Readiness

### Endpoints

| Endpoint | Purpose | Auth | Expected |
|---|---|---|---|
| `GET /up` | Laravel framework health (liveness) | None | 200 with HTML |
| `GET /_readiness` | App readiness (DB, cache, queue config) | None (protect via proxy/firewall) | 200 JSON `{"status":"healthy"}` or 503 `{"status":"degraded"}` |

### Readiness response structure

```json
{
  "status": "healthy",
  "checks": {
    "database": { "ok": true, "driver": "pgsql" },
    "cache": { "ok": true, "driver": "file" },
    "queue": { "ok": true, "driver": "database" }
  },
  "timestamp": "2026-03-10T12:00:00+00:00"
}
```

### When `/_readiness` returns 503

1. Check `checks` object for `"ok": false` entries.
2. **database** failed → verify PostgreSQL is running (`sail up -d pgsql`), check `DB_*` env vars.
3. **cache** failed → check cache driver (e.g. Redis connectivity, filesystem permissions on `storage/framework/cache`).
4. **queue** failed → verify `QUEUE_CONNECTION` env is set to a valid driver (`sync`, `database`, `redis`, etc.).

### Production protection

`/_readiness` is unauthenticated by design (load-balancer probes can't carry session cookies). In production, restrict access to internal networks via reverse-proxy rules:

```nginx
location = /_readiness {
    allow 10.0.0.0/8;
    allow 172.16.0.0/12;
    deny all;
    proxy_pass http://app;
}
```

---

## Stripe Webhook Failure Triage

### Log patterns to search for

| Log message | Severity | Meaning |
|---|---|---|
| `stripe.webhook.config_missing` | ERROR | `STRIPE_WEBHOOK_SECRET` not set |
| `stripe.webhook.signature_invalid` | WARNING | Signature mismatch — spoofed request or wrong secret |
| `stripe.webhook.payload_invalid` | WARNING | Malformed JSON from Stripe |
| `Stripe webhook processing failed` | ERROR | Business-logic processing threw an exception |

### Triage steps

1. **Check `stripe_webhook_events` table:**
   ```bash
   sail artisan tinker --execute="
     \App\Models\StripeWebhookEvent::where('status','failed')
       ->latest()->take(10)->get(['id','stripe_event_id','type','status','notes','created_at']);
   "
   ```

2. **Identify the failing event type** — common problem types:
   - `customer.subscription.updated` — price-to-plan mapping missing in `config/billing.php` `stripe_prices`
   - `checkout.session.completed` — metadata `plan_key` invalid or org not linked to Stripe customer
   - `invoice.payment_failed` — org subscription in terminal state

3. **Check structured log context** — the `organization_id` and `stripe_event_id` fields in the error log entry help narrow the org.

4. **Replay a failed event:**
   - From Stripe Dashboard: go to Developers → Webhooks → Event → "Resend"
   - Locally: use `stripe trigger <event_type>` with Stripe CLI
   - After fixing the root cause, the idempotency guard (`stripe_webhook_events` unique constraint) will block re-processing. To force reprocessing:
     ```bash
     sail artisan tinker --execute="
       \App\Models\StripeWebhookEvent::where('stripe_event_id','evt_xxx')->delete();
     "
     ```

5. **Signature failures spike** → rotating webhook secret? Update `STRIPE_WEBHOOK_SECRET` in env and restart app.

---

## Stripe Billing Failure Triage

### Subscription initiation failures

**Log pattern:** `stripe.subscription.initiation_failed`

Context fields: `organization_id`, `organization_slug`, `plan_key`, `stripe_customer_id`, `error`, `exception_class`.

**Common causes:**
- Missing Stripe config (`STRIPE_SECRET` / `STRIPE_KEY` unset) → 422 to user, error logged
- Price ID not mapped for plan → check `config/billing.php` `stripe_prices.{plan_key}`
- Stripe customer creation failed → check Stripe API status, verify org `stripe_id`

### Portal redirect failures

**Log pattern:** `stripe.portal.launch_failed`

Context fields: `organization_id`, `organization_slug`, `stripe_customer_id`, `error`, `exception_class`.

**Common causes:**
- No `stripe_id` on organization → user sees safe flash error
- Stripe API down / rate-limited → check [status.stripe.com](https://status.stripe.com)
- Billing portal configuration not created in Stripe Dashboard

### Invoice fetch failures

**Log pattern:** `stripe.invoices.fetch_failed`

Non-critical — the billing page renders normally with an empty invoice list. Fix the underlying Stripe connection issue; invoices will populate on next page load.

---

## Export Limit Troubleshooting

Exports are rate-limited per org per day via `DailyExportLimitService`.

### Symptoms
- User gets 429 on `/org/{org}/export/csv/{entity}`

### Diagnosis
```bash
sail artisan tinker --execute="
  \$org = \App\Models\Organization::where('slug','acme')->first();
  echo app(\App\Services\Billing\EntitlementsService::class)->value('exports_per_day', \$org);
"
```

### Resolution options
1. **Wait for daily reset** — counter resets at midnight UTC.
2. **Increase limit** — add/update an `exports_per_day` add-on for the org via billing admin or adjust plan defaults in `PlanCatalog`.
3. **Emergency override** — clear the rate-limit cache key (pattern: `exports:{org_id}:YYYY-MM-DD`).

---

## API Rate-Limit Troubleshooting

API requests are throttled per org+key+IP via `ThrottleOrgApi` middleware.

### Symptoms
- API consumer gets 429 `{"message":"Too many requests.","code":"rate_limited"}`

### Diagnosis
```bash
sail artisan tinker --execute="
  \$org = \App\Models\Organization::where('slug','acme')->first();
  echo app(\App\Services\Billing\EntitlementsService::class)->value('api_rpm', \$org);
"
```

### Resolution options
1. **Increase limit** — adjust `api_rpm` entitlement for the org (add-on or plan upgrade).
2. **Config fallback** — `config('api.rate_limit_per_minute')` env `API_RATE_LIMIT_PER_MINUTE`.
3. **Client guidance** — implement exponential backoff; respect `Retry-After` header.

---

## Failed Jobs

### Infrastructure

- Queue driver: `database` (default)
- Jobs table: `jobs`
- Failed jobs table: `failed_jobs` (driver: `database-uuids`)
- Job batches table: `job_batches`

### Known jobs

| Job | Purpose | Tries | Timeout |
|---|---|---|---|
| `TenantReaperJob` | Destructive org deletion after churn grace period | 1 | 600s |

### Inspecting failed jobs

```bash
# List recent failures
sail artisan queue:failed --limit=20

# Show a specific failure (by UUID)
sail artisan queue:failed <uuid>

# Retry a specific failed job
sail artisan queue:retry <uuid>

# Retry all failed jobs
sail artisan queue:retry all

# Flush old failed jobs
sail artisan queue:flush
```

### TenantReaperJob failure

This job runs inside a DB transaction. If it fails:
1. Check `failed_jobs` for the exception message.
2. Common cause: foreign-key constraint violation (new table added without updating reaper delete order).
3. **Do not retry blindly** — the job is destructive. Verify the org state first.
4. Log pattern: `TenantReaper: FAILED to delete Organization {id}`

---

## Logs & Events Reference

### Where to look

| Source | Location | Contents |
|---|---|---|
| Application log | `storage/logs/laravel.log` | All `Log::*` calls (webhook failures, billing errors, queue failures) |
| `stripe_webhook_events` table | Database | Every received Stripe event: ID, type, status, notes, payload |
| `audit_logs` table | Database | Org-scoped business events (subscription changes, payment results, settings, CRUD) |
| `failed_jobs` table | Database | Jobs that exhausted retries |

### Structured log field conventions

All billing/webhook error logs include:
- `organization_id` — tenant ID (when resolvable)
- `stripe_event_id` — Stripe event ID (for webhook events)
- `event_type` — Stripe event type
- `error` — exception message
- `exception_class` — PHP exception class name

### Useful log searches

```bash
# Webhook processing failures
grep "Stripe webhook processing failed" storage/logs/laravel.log

# Billing initiation failures
grep "stripe.subscription.initiation_failed" storage/logs/laravel.log

# Portal launch failures
grep "stripe.portal.launch_failed" storage/logs/laravel.log

# All Stripe-related errors
grep "stripe\." storage/logs/laravel.log
```

---

## Quick Triage Flowchart

```
User reports billing issue
│
├─ "Can't subscribe" → Check stripe.subscription.initiation_failed logs
│   ├─ Missing config? → Set STRIPE_SECRET / STRIPE_KEY / price ID
│   └─ Stripe error? → Check status.stripe.com, retry
│
├─ "Can't manage billing" → Check stripe.portal.launch_failed logs
│   ├─ No stripe_id? → Org never subscribed via Stripe
│   └─ API error? → Check Stripe status, verify portal config
│
├─ "Subscription not updating" → Check stripe_webhook_events for failures
│   ├─ Signature invalid? → Webhook secret rotation needed
│   ├─ Processing failed? → Check event type + error in notes column
│   └─ Event never received? → Check Stripe webhook endpoint config
│
├─ "Export blocked" → Check exports_per_day entitlement
│   └─ At limit? → Wait for reset or increase entitlement
│
└─ "API throttled" → Check api_rpm entitlement
    └─ At limit? → Increase or advise backoff
```
