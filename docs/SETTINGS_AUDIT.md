# Tenant Settings — Audit Findings

**Date:** 2025-02  
**Scope:** Tenant routes `/org/{organization:slug}/...`

---

## Step A — Audit Results

### 1) Current Settings Routes + Controller Actions

| Route | Method | Name | Controller | RBAC |
|-------|--------|------|------------|------|
| `/org/{org}/settings` | GET | settings.index | SettingsController::index | `settings.view` |
| `/org/{org}/settings` | PUT/POST | settings.update | SettingsController::update | `settings.update` |
| `/org/{org}/settings/test-slack` | POST | settings.testSlack | SettingsController::testSlack | `settings.update` |
| `/org/{org}/settings/test-smtp` | POST | settings.testSmtp | SettingsController::testSmtp | `settings.update` |

**Note:** `admin.settings.save`, `org.settings.update`, `notifications.settings.update` are referenced in Vue files but **routes do not exist**. Tenant settings use `settings.index` and `settings.update` only.

### 2) Current Settings/Index.vue Sections and Keys

| Section | Keys Read/Written | Storage |
|---------|-------------------|---------|
| Brand Identity | name, logo | organizations.name, organizations.logo_path |
| Localization | timezone, week_start | organizations.timezone, organizations.week_start |
| Roles & Permissions | — | Link to roles.index (no form) |

**All current keys** are stored in the **organizations** table. No usage of the `settings` table in tenant SettingsController.

### 3) Storage: Organizations vs Settings Table

**Organizations table columns (existing):**
- `name`, `slug`, `logo_path`, `timezone`, `week_start`
- `settings` (JSON, nullable) — available for extra config

**Settings table (organization_id, key, value):**
- Used by `Setting::get()` / `Setting::put()`
- Unique per (organization_id, key)
- Currently used by Admin/SettingsController (platform-level, different route group)

**Decision for v1:**
- **Organizations columns:** name, slug (read-only), logo_path, timezone, week_start
- **Setting::put (settings table):** locale, currency, work_hours, notifications.defaults, slack_webhook_url, smtp_* (integrations)

### 4) User Notification Prefs (Existing)

- `users.notification_prefs` (JSON) — per-user, not org-level
- NotificationsController::settings / updateSettings — user-level prefs
- **Org-level defaults** for new users → new key `notifications.defaults` in settings table

### 5) Integrations (Orphaned)

- Admin/Settings.vue has Slack, Drive, SMTP, Zapier — but `admin.settings.save` route does not exist
- Admin/SettingsController expects `$request->route('organization')` — inconsistent with admin prefix
- **v1:** Add integrations section to **tenant** Settings, store via Setting::put, org-scoped

---

## Step B — Implemented (Tenant Settings v1)

### Settings Keys (settings table)

| Key | Type | Description |
|-----|------|-------------|
| `locale` | string | e.g. en, es |
| `currency` | string | 3-letter code, e.g. USD |
| `work_hours` | JSON | work_week, start_time, end_time |
| `notifications.defaults` | JSON | channels.inapp, channels.email, types |
| `slack_webhook_url` | string | Slack incoming webhook |
| `smtp_host`, `smtp_port`, `smtp_user`, `smtp_pass`, `smtp_from` | string | SMTP config |

### Organization table (columns)

- name, slug (read-only), logo_path, timezone, week_start

---

## Step C — Tenant Settings v2 (Audit Logging + Secret-Safe Integrations)

### Audit Logging

- **SettingsController::update** logs via `AuditLogger::log()` with:
  - `entity`: `settings`
  - `action`: `updated`
  - `entity_id`: organization_id
  - `changes`: `['keys' => [...]]` — changed keys only, **no raw secrets**

### Secret-Safe Storage

- **Encrypted at rest** (Laravel `Crypt::encryptString`): `slack_webhook_url`, `smtp_pass`
- **Never returned to frontend**: Secrets are masked (`••••••••`) or omitted; `slack_webhook_connected` and `smtp_pass_set` booleans indicate presence
- **Setting::putEncrypted** / **Setting::getDecrypted** for server-side use only

### Test Actions

- **Test Slack webhook** (`settings.testSlack`): POST, gated by `settings.update`; sends test message to configured webhook
- **Test SMTP** (`settings.testSmtp`): POST, gated by `settings.update`; verifies SMTP connection using stored credentials
