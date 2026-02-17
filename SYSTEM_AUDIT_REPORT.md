# CRM Beast - System Audit Report
**Generated:** 2026-02-06
**Purpose:** State of the Union - Prevent Duplication & Identify Issues

---

## 1. DATABASE SCHEMA REALITY

### ✅ **Clients Table** (`clients`)
**Source:** `2025_10_09_110000_create_core_entities.php` + `2025_10_18_000100_align_clients_projects_per_spec.php`

**Columns:**
- `id` (PK)
- `organization_id` (FK to organizations) ✅
- `company_name` ✅
- `industry` (nullable)
- `niche` (nullable)
- `primary_contact_name` (nullable)
- `primary_contact_email` (nullable)
- `primary_contact_phone` (nullable)
- `website` (nullable)
- `address` (nullable, text)
- `tags` (JSON, nullable) ✅
- **`fronter` (JSON, nullable)** ✅ EXISTS - stores array of user IDs
- **`closer` (JSON, nullable)** ✅ EXISTS - stores array of user IDs
- **`assigned_account_manager_id` (FK to users, nullable)** ✅ EXISTS
- `google_business_profile_status` (enum/string)
- `google_business_profile_access_status` (enum/string)
- `client_activation_status` (enum/string, default 'Inactive')
- `notes_by_cst` (text, nullable)
- `notes_by_sales` (text, nullable)
- `notes_by_tech` (text, nullable)
- **`status` (string, default 'active')** ✅ EXISTS
- `created_at`, `updated_at`
- `deleted_at` (soft deletes) ✅

**Indexes:**
- `organization_id + assigned_account_manager_id`

---

### ✅ **Projects Table** (`projects`)
**Source:** `2025_10_09_110000_create_core_entities.php` + `2025_10_18_000100_align_clients_projects_per_spec.php`

**Columns:**
- `id` (PK)
- `organization_id` (FK to organizations) ✅
- `client_id` (FK to clients) ✅
- `title` ✅
- `project_code` (nullable, unique per org)
- `description` (text, nullable)
- **`project_manager_id` (FK to users)** ✅ EXISTS (NOT nullable in original schema)
- `department_id` (FK to departments, nullable)
- `start_date` (date, nullable)
- `end_date` (date, nullable)
- `status` (string, default 'active')
- **`budget` (decimal 12,2, nullable)** ✅ EXISTS
- **`price` (decimal 12,2, nullable)** ✅ EXISTS
- `billable` (boolean, default true)
- `google_business_profile_status` (nullable)
- `google_business_profile_access_status` (nullable)
- `client_activation_status` (nullable)
- `notes_by_cst` (text, nullable)
- `notes_by_sales` (text, nullable)
- `notes_by_tech` (text, nullable)
- `attachments` (JSON, nullable)
- `custom_fields` (JSON, nullable)
- `created_at`, `updated_at`
- `deleted_at` (soft deletes)

**Indexes:**
- `organization_id + client_id`
- UNIQUE: `organization_id + project_code`

---

### ✅ **Tasks Table** (`tasks`)
**Source:** `2025_10_09_110000_create_core_entities.php` + `2025_10_19_000460_update_tasks_submission_review.php`

**Columns:**
- `id` (PK)
- `organization_id` (FK to organizations) ✅
- `project_id` (FK to projects) ✅
- `title` ✅
- `description` (text, nullable)
- `assignees` (JSON, nullable) ✅ - stores array of user IDs
- `due_date` (date, nullable)
- `priority` (string, default 'normal')
- `status` (string, default 'open')
- `estimated_hours` (decimal 8,2, nullable)
- `logged_hours` (decimal 8,2, default 0)
- `subtasks` (JSON, nullable)
- `attachments` (JSON, nullable)
- `comments` (JSON, nullable)
- **`submission` (JSON, nullable)** ✅ EXISTS (original field)
- **`submission_note` (text, nullable)** ✅ EXISTS (added later)
- **`submission_files` (JSON, nullable)** ✅ EXISTS (added later)
- **`review_status` (string, nullable, indexed)** ✅ EXISTS
- **`reviewed_by_id` (bigint, nullable, indexed)** ✅ EXISTS
- `created_at`, `updated_at`
- `deleted_at` (soft deletes)

**Indexes:**
- `organization_id + project_id`
- `review_status`
- `reviewed_by_id`

⚠️ **WARNING:** Task has BOTH `submission` (JSON) AND `submission_note` + `submission_files` fields. This is redundant.

---

### ⚠️ **DUPLICATE TABLE WARNING: Announcements**

**Two migrations create the same table:**
1. `2025_10_17_000000_create_announcements_table.php` (older, simpler)
2. `2025_10_19_000410_create_announcements_table.php` (newer, more fields)

**Current Schema (from core_entities migration):**
- `id`, `organization_id`, `author_id`, `scope`, `targets` (JSON), `title`, `body`, `pinned`, `timestamps`

**Later Migration Adds:**
- `user_id` (author), `department_id`, `project_id`, `published_at`, `soft_deletes`

⚠️ **RESOLUTION NEEDED:** Both migrations have `if (! Schema::hasTable())` guards, so whichever runs first wins. Schema is inconsistent across environments.

**Actual Table Used:** Single `announcements` table (no duplicates in database)

---

### ✅ **Other Tables**
- `organizations` ✅
- `users` ✅
- `departments` ✅
- `attendance` ✅
- `project_messages` ✅
- `settings` ✅
- `audit_logs` ✅
- `notifications_center` ✅ (renamed from `notifications`)
- `notification_events` ✅ (Laravel's database notifications table)

**No Duplicate Tables** - Only duplicate migrations (announcements)

---

## 2. MODEL CAPABILITIES

### **Client Model** (`app/Models/Client.php`)

**$fillable:**
```php
[
    'organization_id', 'company_name', 'industry', 'niche',
    'primary_contact_name', 'primary_contact_email', 'primary_contact_phone',
    'website', 'address', 'tags', 
    'fronter', 'closer', 'assigned_account_manager_id',
    'google_business_profile_status', 'google_business_profile_access_status',
    'client_activation_status', 'notes_by_cst', 'notes_by_sales', 'notes_by_tech',
    'status'
]
```

**$casts:**
```php
[
    'tags' => 'array',
    'fronter' => 'array',  ✅
    'closer' => 'array'    ✅
]
```

**Relationships:**
- ✅ `organization()` → BelongsTo Organization
- ✅ `projects()` → HasMany Project
- ✅ `accountManager()` → BelongsTo User (via `assigned_account_manager_id`)
- ❌ **NO** `fronter()` or `closer()` relationships (they're JSON arrays, not FKs)

**Scopes:**
- ✅ `scopeForOrg($orgId)` - Multi-tenant filter
- ✅ `scopeVisibleTo($user)` - Permission-based visibility (checks fronter/closer JSON)

---

### **Project Model** (`app/Models/Project.php`)

**$fillable:**
```php
[
    'organization_id', 'client_id', 'title', 'project_code', 'description',
    'project_manager_id', 'department_id', 'start_date', 'end_date',
    'status', 'budget', 'price', 'billable',
    'google_business_profile_status', 'google_business_profile_access_status',
    'client_activation_status', 'notes_by_cst', 'notes_by_sales', 'notes_by_tech',
    'attachments', 'custom_fields'
]
```

**$casts:**
```php
[
    'billable' => 'boolean',
    'attachments' => 'array',
    'custom_fields' => 'array',
    'start_date' => 'date',
    'end_date' => 'date'
]
```

**Relationships:**
- ✅ `organization()` → BelongsTo Organization
- ✅ `client()` → BelongsTo Client
- ✅ `manager()` → BelongsTo User (via `project_manager_id`)
- ✅ `department()` → BelongsTo Department
- ✅ `tasks()` → HasMany Task

**Scopes:**
- ✅ `scopeVisibleTo($user)` - Permission-based visibility

---

### **Task Model** (`app/Models/Task.php`)

**$fillable:**
```php
[
    'organization_id', 'project_id', 'title', 'description', 'assignees',
    'due_date', 'priority', 'status', 'estimated_hours', 'logged_hours',
    'subtasks', 'attachments', 'comments',
    'submission', 'submission_note', 'submission_files',
    'review_status', 'reviewed_by_id'
]
```

**$casts:**
```php
[
    'assignees' => 'array',
    'due_date' => 'datetime',
    'estimated_hours' => 'float',
    'logged_hours' => 'float',
    'subtasks' => 'array',
    'attachments' => 'array',
    'comments' => 'array',
    'submission' => 'array',      ← Original JSON field
    'submission_files' => 'array' ← Newer dedicated field
]
```

⚠️ **REDUNDANCY:** Both `submission` (JSON) and `submission_note` + `submission_files` exist

**Relationships:**
- ✅ `organization()` → BelongsTo Organization
- ✅ `project()` → BelongsTo Project
- ✅ `reviewer()` → BelongsTo User (via `reviewed_by_id`)

**Scopes:**
- ✅ `scopeForOrg($orgId)` - Multi-tenant filter
- ✅ `scopeVisibleTo($user)` - Permission-based visibility

---

## 3. CONTROLLER INVENTORY

### ⚠️ **DUPLICATE CONTROLLERS - Announcements**

**Two controllers exist:**
1. **`AnnouncementController.php`** (singular) ← **ACTIVELY USED** ✅
   - Proper authorization (`$this->authorize()`)
   - Uses Organization model binding
   - Clean Inertia responses
   - **Used in routes/web.php** ✅

2. **`AnnouncementsController.php`** (plural) ← **ORPHAN** ⚠️
   - Manual organization lookup via slug
   - No authorization checks
   - Different method signatures
   - **NOT used in routes/web.php** ❌

**Resolution:** `AnnouncementsController.php` is dead code and should be deleted.

---

### ⚠️ **ORPHAN CONTROLLERS** (Not Used in Routes)

1. **`TaskSubmissionController.php`** ❌
   - NOT referenced in `routes/web.php`
   - Dead code

2. **`BillingController.php`** ❌
   - NOT referenced in `routes/web.php`
   - Dead code
   - Note: `BillingPageController` IS used ✅

3. **`OrganizationSettingsController.php`** ❌
   - NOT referenced in `routes/web.php`
   - Dead code

4. **`NotificationsController.php`** ❌
   - NOT referenced in `routes/web.php`
   - Note: `NotificationCenterController` IS used ✅

---

### ✅ **ACTIVE CONTROLLERS** (All Used)

**Client Management:**
- `ClientsInertiaController` ✅ - Main client pages (index, show, edit, import)
- `ClientController` ✅ - Client CRUD operations (store, update, destroy, export)
- `ClientsImportController` ✅ - CSV import
- `ClientsPipelineController` ✅ - Pipeline board

**Project Management:**
- `ProjectController` ✅ - Full CRUD + status updates
- `ProjectBoardController` ✅ - Kanban board
- `ProjectCalendarController` ✅ - Calendar view
- `ProjectMessagesController` ✅ - Project chat

**Task Management:**
- `TaskController` ✅ - Full CRUD + submit/review
- `TaskBoardController` ✅ - Kanban board

**User/Org Management:**
- `UserManagementController` ✅ - User list + role assignment
- `DepartmentController` ✅ - Department CRUD
- `RolePermissionController` ✅ - Role/permission editor

**Other:**
- `AnnouncementController` ✅
- `AttendanceController` ✅
- `NotificationCenterController` ✅
- `ActivityController` ✅ (audit logs)
- `DashboardController` ✅
- `ProfileController` ✅
- `SettingsController` ✅
- `BillingPageController` ✅
- `ReportController` ✅

---

### ✅ **ClientController::store() - User Assignment Check**

**File:** `app/Http/Controllers/ClientController.php` (Lines 133-188)

**Current Implementation:**
```php
public function store(Request $request): RedirectResponse
{
    $data = $request->validate([
        // ... basic fields ...
        'fronter' => ['nullable', 'array'],     ✅ HANDLES
        'closer' => ['nullable', 'array'],      ✅ HANDLES
        'assigned_account_manager_id' => ['nullable', 'integer', 'exists:users,id'], ✅ HANDLES
        // ... other fields ...
    ]);
    
    $client = Client::query()->create($data); // ✅ Mass assignment works
    
    // ... audit logging ...
}
```

**Verdict:** ✅ **COMPLETE** - ClientController::store() DOES handle user assignments:
- `fronter` (array)
- `closer` (array)
- `assigned_account_manager_id` (single user ID)

All three fields are validated and mass-assigned via `$fillable`.

---

## 4. ROUTE VERIFICATION

### ✅ **Resource Routes Defined**

```php
Route::resource('projects', ProjectController::class);  // ✅ All 7 routes
Route::resource('clients', ...) // Split across ClientsInertiaController + ClientController
// Tasks routes manually defined (not resource)
```

### ⚠️ **Broken/Missing Route Mappings**

**None Found** - All imported controllers in `routes/web.php` are mapped.

### ⚠️ **Controllers NOT in Routes**

1. `AnnouncementsController` (plural) - Import exists but NOT used
2. `TaskSubmissionController` - No import, no routes
3. `BillingController` - No import, no routes
4. `OrganizationSettingsController` - No import, no routes
5. `NotificationsController` - No import, no routes

---

## SUMMARY OF ISSUES

### 🔴 **CRITICAL**
1. **Duplicate Migration:** Two `create_announcements_table` migrations can cause schema inconsistency
   - `2025_10_17_000000_create_announcements_table.php`
   - `2025_10_19_000410_create_announcements_table.php`

### 🟡 **WARNINGS**
2. **Duplicate Controller:** `AnnouncementsController.php` exists but is unused
3. **Orphan Controllers:** 5 controllers exist but are not routed
4. **Field Redundancy:** Task has both `submission` (JSON) AND `submission_note` + `submission_files`

### 🟢 **GOOD NEWS**
- ✅ No duplicate tables in actual database (only duplicate migrations)
- ✅ All core relationships exist and work
- ✅ Client model has `fronter`, `closer`, `assigned_account_manager_id` ✅
- ✅ Project model has `budget`, `price`, `project_manager_id` ✅
- ✅ Task model has `submission`, `review_status`, `reviewed_by_id` ✅
- ✅ ClientController::store() handles all user assignments ✅

---

## RECOMMENDATIONS FOR LEAD ARCHITECT

### **Immediate Actions:**
1. **Delete** unused controllers:
   - `app/Http/Controllers/AnnouncementsController.php`
   - `app/Http/Controllers/TaskSubmissionController.php`
   - `app/Http/Controllers/BillingController.php`
   - `app/Http/Controllers/OrganizationSettingsController.php`
   - `app/Http/Controllers/NotificationsController.php`

2. **Delete** duplicate migration:
   - `database/migrations/2025_10_17_000000_create_announcements_table.php` (keep the newer one)

### **Future Refactoring:**
3. **Consolidate** Task submission fields:
   - Either use `submission` (JSON) OR `submission_note` + `submission_files`
   - Remove the unused approach

4. **Standardize** naming:
   - Consider renaming `AnnouncementController` to `AnnouncementsController` for consistency
   - Or keep singular (current Laravel convention)

---

**End of Report**
