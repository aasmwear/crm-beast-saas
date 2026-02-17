# 🦁 CRM Beast: The Master Specification (v6.1) - "The 9th Wonder"
**Status:** IMMUTABLE / PRODUCTION FINAL
**Scope:** Hyper-Scale Digital Marketing SaaS Platform
**Authority:** The Decemvirate (Council of 10)
**Version Notes:** Merges Enterprise SaaS Scale with Deep Agency Business Logic. Adds Field-Level Security, Geo-Proofing, and AI-Ready Schemas.

---

## 1. The Foundation (Systems Architecture)

### 1.1 The Technology Stack (Non-Negotiable)
* **Core:** Laravel 12 (PHP 8.2+) on Docker (Sail).
* **Database:** PostgreSQL 15+ (Strict Mode). **Extensions:** `pg_trgm` (Fuzzy Search), `btree_gin` (JSONB Indexing), `postgis` (Geolocation).
* **Realtime Fabric:** **Laravel Reverb** (WebSockets) backed by Redis.
* **Frontend:** Inertia.js 2.0 + Vue 3 (Composition API, `<script setup>`, TypeScript).
* **State Management:** Pinia + LocalStorage (Drafts & Prefs).
* **Storage:** AWS S3 / MinIO (Local) with Atomic Event Notifications.

### 1.2 The "Dual-Layer" Multi-Tenancy Doctrine
* **Layer 1: The Platform (Super Admin)**
    * **Route Scope:** `domain.com/admin/...`
    * **Auth Guard:** `web` (SuperAdminMiddleware).
    * **Context:** Global visibility.
* **Layer 2: The Application (Tenant)**
    * **Route Scope:** `domain.com/org/{organization:slug}/...`
    * **Auth Guard:** `web` (Standard).
    * **Context:** **STRICT ISOLATION.** Every query MUST have `where('organization_id', $org->id)`.
* **Middleware (`ResolveTenant`):**
    1.  **Identification:** Resolves `{slug}`.
    2.  **Gatekeeper:** Checks `MaintenanceMode`.
    3.  **Bouncer:** Checks `SubscriptionStatus` (Active/Grace/Locked).
    4.  **Injector:** Sets `setPermissionsTeamId($id)` & Injects `org` prop.

---

## 2. The Platform Layer (The SaaS Engine)

### 👑 Module 0: God Mode (Super Admin)
**Controller Namespace:** `App\Http\Controllers\Platform`
**Features:**
1.  **The Watchtower:** Global MRR, Active Tenants, Storage/User Quota Heatmaps.
2.  **Tenant Operations:** Impersonation (Audit Logged), Soft/Hard Nuke (Reaper Job).
3.  **Global Broadcasting:** System-wide alerts via Reverb.

### 💳 Module 0.5: Modular Billing & Subscriptions
**Table:** `subscriptions` (Cashier/Stripe).
**Table:** `organization_features` (JSONB).
* **Schema:** `{"attendance": true, "sms": false, "api_access": true}`.
**Logic:**
* **Grace Period:** 7 Days (Banner shown).
* **Lockout:** Day 8+ (Read Only).
* **Churn:** Day 30+ (Scheduled for Reaper).

---

## 3. The Core Application (Tenant Layer)

### 🏛️ Module A: Organizations & Settings
**Table:** `organizations`
* `id`, `name`, `slug` (Unique Index), `owner_id` (FK).
* `settings` (JSONB): Branding, Timezone (Critical), Work Days.
* `storage_usage` (BigInt), `storage_limit` (BigInt).

### 👥 Module B: User Management & RBAC (Granular)
**Table:** `users`
* `id`, `name`, `email`, `phone` (E.164), `avatar_path`.
* `active_organization_id` (FK).

**Table:** `roles` (Spatie Extended)
* `id`, `name`, `guard_name`, `team_id`.
* **The "Field-Level Security" Vault:** `field_permissions` (JSONB).
    * *Schema:* `{"projects": {"budget": "hidden", "price": "readonly"}, "clients": {"phone": "read_write"}}`.
    * *Logic:* API Resources/Controllers must parse this JSON to filter sensitive data from responses.

**Table:** `departments`
* `id`, `name`, `code` (e.g., 'CST').
* `is_pm_capable` (Boolean). *Replaces hardcoded 'CST' rule. Only users in these depts appear in PM dropdowns.*

### 🤝 Module C: Clients (The Agency Engine)
*Refined for Digital Marketing Specifics.*
**Table:** `clients`
* `organization_id` (FK).
* **Identity:** `company_name` (Index), `website`, `logo`.
* **Market Segment:** `niche` (String), `industry` (String).
* **Agency Status:**
    * `status` (Enum: Lead, Active, Churned, Paused).
    * `client_activation_status` (Enum: Inactive, Active, Paused, Cancelled).
* **SEO Data (Critical):**
    * `gbp_status` (Enum: Not_Created, Created, Pending, Verified, Suspended).
    * `gbp_access` (Enum: No_Access, Access_Granted, Access_Pending).
* **Strict Ownership (Foreign Keys):**
    * `fronter_id` (FK -> User).
    * `closer_id` (FK -> User).
    * `assigned_account_manager_id` (FK -> User).
* **Department Intelligence (Rich Text):**
    * `notes_sales` (Sales Dept Only).
    * `notes_cst` (Account Managers Only).
    * `notes_tech` (Dev/SEO Team Only).
* **Geo-Data:** `address`, `lat`, `lng`.

**Logic:**
* **Creation:** Dropdowns for Fronter/Closer/AM must filter based on Department roles.
* **Snapshotting:** On 'Churned', dump entire record to `client_snapshots`.

### 🚀 Module D: Projects (Execution)
**Table:** `projects`
* `id`, `organization_id`, `client_id`.
* **Identity:** `title`, `project_code` (Unique "ACME-001"), `description`.
* **Financials:** `budget_cents` (BigInt), `price_cents` (BigInt), `billable` (Bool).
* **Sync Status:**
    * `gbp_status` (Enum, inherits from Client or Overrides).
    * `client_activation_status` (Reference).
* **Management:** `project_manager_id` (FK). *Must validate user->department->is_pm_capable.*
* **Department Notes:** `notes_cst`, `notes_sales`, `notes_tech` (Synced with Client or Independent).
* **Performance Cache:** `assignee_ids_cache` (JSONB).

**Concurrency:** Atomic Code Gen using Redis Locks.

### ✅ Module E: Tasks (The Factory)
**Table:** `tasks`
* `project_id` (FK).
* **Core:** `title`, `description` (Rich Text), `priority` (Enum).
* **Status:** `status` (Enum: Todo, In_Progress, Review, Done).
* **Ordering:** `sort_order` (Lexorank String).
* **Submission Flow:**
    * `submission_note` (Text).
    * `submission_files` (JSONB paths).
    * `review_status` (Enum: Pending, Approved, Rejected).
    * `reviewed_by_id` (FK).
    * `reviewed_at` (Timestamp).

**Table:** `task_user` (Pivot)
* `task_id`, `user_id`, `is_primary` (Bool).

**Realtime:** Laravel Reverb events for `TaskMoved`, `TaskUpdated`.

### ⏱️ Module F: Attendance (Geo-Proofing)
**Table:** `attendance_records`
* `user_id`, `organization_id`.
* `business_date` (Date).
* `clock_in` (Timestamp), `clock_out` (Timestamp).
* `clock_in_ip`, `clock_out_ip`.
* **Geo-Proof:** `clock_in_lat`, `clock_in_lng`.
* **Visual Proof:** `proof_image_path` (String, nullable). *Selfie URL.*
* `total_minutes` (Int).
* `status` (Enum: Present, Late, Absent, Half_Day).

**Logic:**
* **Midnight Split:** Virtual splitting for reporting.
* **Ghost Protocol:** Auto-close shifts > 16h.

### 📢 Module G: Internal Comm & Announcements
**Table:** `announcements`
* `target_type` (Enum: Global, Dept, Project).
* `is_pinned` (Bool).
* `read_receipts` (Pivot Table).

**Table:** `project_messages`
* `project_id`, `user_id`, `body`, `attachments` (JSONB).
* *Realtime Chat via Reverb.*

---

## 4. Cross-Cutting Intelligence

### 4.1 The "Visibility Engine" (RLS v2)
* **Logic:** A user sees a Client IF:
    * `fronter_id` == User OR
    * `closer_id` == User OR
    * `account_manager_id` == User OR
    * User is PM of a linked Project OR
    * User has `view_all_clients` permission.
* **Optimization:** Use a PostgreSQL View or Materialized View (`user_client_access`) to prevent massive `OR` queries.

### 4.2 Field-Level Security (The "Vault")
* **Middleware:** `FilterResponseFields`.
* **Logic:** After Controller returns data, Middleware checks User Role -> `field_permissions`. If `budget` is forbidden, it unsets that key from the JSON response before sending to Vue.

### 4.3 Notification Intelligence
* **Triggers:** New Activity (Client Update, Task Assign, Mention).
* **Debounce:** Group "10 Task Updates" into 1 Notification if within 5 mins.
* **Channels:** Database (Bell), Email (Digest), Slack (Webhook).

---

## 5. UI/UX Standards

### 5.1 The "Beast" Interface
* **Theme:** Dark Glass (`bg-slate-900` + `backdrop-blur`).
* **Components:**
    * `StatusBadge`: Maps Enums to Colors (GBP Verified = Green, Suspended = Red).
    * `PermissionGrid`: Matrix for Admin to toggle Entity x Action x Field.
* **Interactivity:**
    * **Drafts:** `localStorage` saves unsubmitted forms.
    * **Shortcuts:** `Ctrl+K` Command Palette.

---

## 6. Execution Roadmap (The 9th Wonder)

**Phase 1: The Platform & Billing**
* [ ] Scaffold `SuperAdmin`.
* [ ] Implement `organization_features` logic.

**Phase 2: The Core Refactor (Deep Agency Logic)**
* [ ] **Clients:** Add Agency Fields (`gbp_status`, `niche`, `notes_x`). Migrate to Strict FKs.
* [ ] **Projects:** Add `budget_cents`, `gbp_status` sync.
* [ ] **Roles:** Add `field_permissions` JSONB column.
* [ ] **Departments:** Add `is_pm_capable` flag.

**Phase 3: The Realtime Engine**
* [ ] Reverb Install.
* [ ] Task Board & Project Chat WebSockets.

**Phase 4: The Legal & Data Layer**
* [ ] Attendance Geo-Proofing + Midnight Split.
* [ ] Field-Level Security Middleware.
* [ ] Tenant Reaper Job.

**Phase 5: Final Polish**
* [ ] PWA & Offline Mode.
* [ ] Command Palette.