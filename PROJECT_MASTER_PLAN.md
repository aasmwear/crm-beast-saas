# 🦁 CRM Beast: The Master Architecture (SaaS Edition)

**Version:** 2.0 (The "Super Power" Update)
**Stack:** Laravel 12 · Inertia.js (Vue 3) · PostgreSQL · Tailwind · Spatie Permissions (Teams)
**Philosophy:** "Strict Security, Flexible Workflows."

---

## 1. The Core DNA (Non-Negotiable)

### 1.1 Multi-Tenancy Architecture
- **Isolation:** Logic-based Multi-tenancy.
- **Routing:** `/org/{organization:slug}/...`
- **Middleware:** `ResolveTenant` (Sets `setPermissionsTeamId($org->id)`).
- **Golden Rule:** Every single database query must be scoped by `organization_id`. No exceptions.

### 1.2 Authentication & Security
- **Auth:** Laravel Breeze (Session-based).
- **RBAC:** Spatie Laravel-Permissions with **Teams** enabled.
- **Granularity:**
  - **Roles:** Admin, HR, Department Head, Project Manager, Sales, Tech.
  - **Permissions:** `view clients`, `create projects`, `manage billing`, etc.
  - **Policies:** Every Controller action (`store`, `update`, `destroy`) is protected by a Laravel Policy (`$this->authorize`).

---

## 2. The "Super-Powered" Modules

### 🏛️ Module A: Organizations & Departments
*Instead of hardcoded "CST" rules, we use flexible configuration.*
- **Organizations:** The tenant container. Contains settings (branding, timezone).
- **Departments:** Dynamic teams (Sales, Tech, CST).
  - **Power Feature:** "Department Responsibilities." Admins can tag a department as "Project Management Capable" or "Sales Capable," allowing logic to adapt without hardcoding "CST".

### 👥 Module B: User Management (Flexible)
- **Users:** Belong to one or more organizations (via pivot).
- **Roles:** Assigned *per organization*.
- **Power Feature:** **"Shadow Login"**. Admins can "impersonate" users to see exactly what they see (critical for debugging visibility issues).

### 🤝 Module C: Clients (The Hub)
*The most critical data entity.*
- **Fields:**
  - Standard: Name, Email, Phone, Website, Address.
  - **Agency Specifics:** `niche`, `industry`, `timezone`.
  - **Status:** `Lead` → `Onboarding` → `Active` → `Churned` (Enum).
- **Flexible Ownership (The Fix):**
  - Instead of rigid "Fronter/Closer" arrays, we support **"Relationship Arrays"**:
    - `fronters`: [User IDs]
    - `closers`: [User IDs]
    - `account_managers`: [User IDs]
  - **Visibility Logic:**
    - **Admins:** See ALL.
    - **Staff:** See clients where they are listed in *any* relationship array OR have the `view all clients` permission. (This fixes the "too rigid" issue).

### 🚀 Module D: Projects & Workflows
- **Structure:**
  - `Project` belongs to `Client`.
  - **Status:** `Planned` → `In Progress` → `Review` → `Completed`.
- **Financials:** `budget`, `price`, `billable` (boolean).
- **Power Feature:** **"Project Templates"**.
  - Users can create a project from a template that pre-loads default Tasks, Folders, and Files.

### ✅ Module E: Tasks (Kanban Power)
- **Views:** List, Kanban Board, Calendar.
- **Fields:**
  - `status`: `Todo`, `In Progress`, `Review`, `Done`.
  - `priority`: `Low`, `Medium`, `High`, `Urgent` (Color-coded).
  - `assignees`: Multiple users allowed.
- **Workflow:**
  - **Submission Flow:** Assignee clicks "Submit for Review" → Status changes to `Review` → Notification sent to PM.
  - **Approval:** PM clicks "Approve" (Done) or "Reject" (Back to In Progress).

### 📢 Module F: The "Pulse" (Announcements & Notifications)
- **Announcements:**
  - Targeted: "All Company", "Department: Sales", or "Specific Users".
  - **Power Feature:** **"Must Read"**. Force users to acknowledge an announcement before using the dashboard.
- **Notifications:**
  - **Real-Time:** In-app Bell icon (Database Notifications).
  - **Smart Batching:** Don't send 50 emails for 50 tasks. Send a "Daily Digest" or batch them every 15 mins.

### ⏱️ Module G: Attendance (Smart Tracking)
- **Logic:** Clock In / Clock Out.
- **Geolocation:** Optional lat/long capture on clock-in.
- **Power Feature:** **"Auto-Checkout"**. If a user forgets to clock out, system auto-clocks them out at 11:59 PM and flags the record for HR review.

---

## 3. Advanced SaaS Capabilities

### 🛡️ 3.1 The Audit Log (The "Black Box")
*Nothing happens without a trace.*
- **What to Log:**
  - Client Status Changes (`Lead` → `Active`).
  - Project Budget Updates.
  - Role Assignments.
  - **Not** minor typos in descriptions (too noisy).
- **Storage:** `audit_logs` table. `actor_id`, `event`, `old_values` (JSON), `new_values` (JSON).

### 📊 3.2 Dynamic Dashboards
*Stop showing empty charts.*
- **Role-Aware Widgets:**
  - **Sales:** "Leads Closed this Month", "Pipeline Value".
  - **PMs:** "Overdue Tasks", "Projects in Review".
  - **Admins:** "MRR (Monthly Recurring Revenue)", "Staff Utilization".

---

## 4. The Implementation Roadmap (Revised)

**Phase 1: Foundation (Done)**
- [x] Environment & Sail
- [x] Multi-tenant Routing
- [x] User Management & Roles

**Phase 2: Core Data (Current)**
- [x] Clients Module (Backend + Frontend wired)
- [ ] Projects Module (Wire Frontend to Backend)
- [ ] Tasks Module (Kanban Board wiring)

**Phase 3: Workflows**
- [ ] Task Submission/Approval Flow
- [ ] Attendance Clock In/Out logic
- [ ] Announcements targeting

**Phase 4: Polish & SaaS Features**
- [ ] Dashboard Widgets (Real Queries)
- [ ] Activity/Audit Logs
- [ ] Billing/Subscriptions (Cashier)

---

## 5. Technical Constraints for AI Agents
*Instructions for Cursor/Gemini when writing code:*
1.  **Strict Typing:** Always use PHP types (`public function index(Request $request): Response`).
2.  **Eager Loading:** Never run a loop without `with(['client', 'manager'])`.
3.  **Scoped Queries:** Every `User::all()` is a bug. Use `User::where('organization_id', $org->id)->get()`.
4.  **No Mock Data:** If the frontend needs data, build the Controller logic. Do not hardcode JS arrays.