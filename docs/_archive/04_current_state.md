🛑 The "Hard Engineering" Analysis
Current Reality vs. Target Reality

The "Super Admin" Risk:


Current Code: You have is_super_admin logic mixed into the User model and UserManagementController.


Target (v6.1): A distinct PlatformAdmin model and platform guard.

The Conflict: If we blindly create a new table, we fracture your current login flow. We must implement a Dual-Guard Auth System in config/auth.php without breaking the existing Breeze setup for tenants.

The "Billing" Database Collision:


Current Code: You have laravel/cashier installed, likely expecting Stripe columns on users or organizations.


Target (v6.1): A separate subscriptions table and a features JSONB column for modular toggles (attendance: true).

The Conflict: We cannot just "add a column." We need to migrate existing data (if any) or define default JSON values so the middleware doesn't crash when it checks $org->features['attendance'].

The "Route Pollution" Problem:


Current Code: All routes live in routes/web.php under the org/{slug} prefix.


Target (v6.1): A completely separate /admin namespace.

The Execution: We shouldn't just hack web.php. We need to register a dedicated routes/platform.php in bootstrap/app.php to keep the kernel clean.