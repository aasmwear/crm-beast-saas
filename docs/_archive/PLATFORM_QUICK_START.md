# Platform Layer - Quick Start Guide

## 🚀 Quick Reference

### Default Super Admin Credentials
```
Email: admin@crmbeast.com
Password: password
```

### Platform Admin URLs
```
Login:      http://localhost:8080/admin/login
Dashboard:  http://localhost:8080/admin/dashboard
```

---

## 📋 Common Tasks

### 1. Authenticate as Platform Admin

```php
use App\Models\Platform\PlatformAdmin;

// Find and login
$admin = PlatformAdmin::where('email', 'admin@crmbeast.com')->first();
auth('platform')->login($admin);

// Check authentication
if (auth('platform')->check()) {
    $currentAdmin = auth('platform')->user();
    echo "Logged in as: {$currentAdmin->name}";
}
```

### 2. Create New Platform Admin

```php
use App\Models\Platform\PlatformAdmin;

// Create Super Admin
$admin = PlatformAdmin::create([
    'name' => 'John Doe',
    'email' => 'john@crmbeast.com',
    'password' => bcrypt('password'),
    'role' => 'super_admin',
    'is_active' => true,
]);

// Or using factory
$admin = PlatformAdmin::factory()->superAdmin()->create();
```

### 3. Manage Organization Features

```php
use App\Models\Platform\OrganizationFeature;
use App\Models\Organization;

$org = Organization::where('slug', 'acme')->first();

// Create feature configuration
$features = OrganizationFeature::create([
    'organization_id' => $org->id,
    'features' => [
        'attendance' => true,
        'sms' => true,
        'api_access' => true,
        'storage_gb' => 50,
    ],
    'subscription_status' => 'active',
    'trial_ends_at' => now()->addDays(30),
]);

// Or via organization relationship
$features = $org->features()->create([...]);

// Check and modify features
if ($features->hasFeature('sms')) {
    // SMS is enabled
}

$features->enableFeature('api_access');
$features->disableFeature('sms');
```

### 4. Check Subscription Status

```php
$org = Organization::with('features')->first();

if ($org->features->isActive()) {
    echo "Subscription active";
}

if ($org->features->trialEnded()) {
    echo "Trial has ended";
}

// Update subscription status
$org->features->update([
    'subscription_status' => 'past_due'
]);
```

---

## 🔒 Middleware Usage

### Protecting Routes

```php
// In routes/platform.php or any controller
Route::middleware(['auth:platform'])->group(function () {
    Route::get('/admin/dashboard', [PlatformDashboardController::class, 'index']);
});

// Check if user is super admin (in controller)
public function someMethod()
{
    $admin = auth('platform')->user();
    
    if (!$admin->isSuperAdmin()) {
        abort(403, 'Super Admin access required');
    }
    
    // Your logic here
}
```

### Guest Routes (Login/Register)

```php
Route::middleware(['guest:platform'])->group(function () {
    Route::get('/admin/login', [PlatformAuthController::class, 'showLogin']);
    Route::post('/admin/login', [PlatformAuthController::class, 'login']);
});
```

---

## 🧪 Testing Examples

### Feature Test Example

```php
<?php

namespace Tests\Feature\Platform;

use App\Models\Platform\PlatformAdmin;
use Tests\TestCase;

class PlatformAuthTest extends TestCase
{
    public function test_platform_admin_can_login(): void
    {
        $admin = PlatformAdmin::factory()->superAdmin()->create([
            'email' => 'test@crmbeast.com',
            'password' => bcrypt('password'),
        ]);

        $response = $this->post('/admin/login', [
            'email' => 'test@crmbeast.com',
            'password' => 'password',
        ]);

        $this->assertAuthenticated('platform');
        $response->assertRedirect('/admin/dashboard');
    }

    public function test_super_admin_can_access_organizations(): void
    {
        $admin = PlatformAdmin::factory()->superAdmin()->create();

        $response = $this->actingAs($admin, 'platform')
            ->get('/admin/organizations');

        $response->assertOk();
    }

    public function test_support_cannot_delete_organizations(): void
    {
        $support = PlatformAdmin::factory()->support()->create();

        $response = $this->actingAs($support, 'platform')
            ->delete('/admin/organizations/1');

        $response->assertForbidden();
    }
}
```

---

## 📦 Factory Usage

### PlatformAdmin Factory

```php
use App\Models\Platform\PlatformAdmin;

// Create Super Admin
$superAdmin = PlatformAdmin::factory()->superAdmin()->create();

// Create Support Staff
$support = PlatformAdmin::factory()->support()->create();

// Create Inactive Admin
$inactive = PlatformAdmin::factory()->inactive()->create();

// Create multiple
$admins = PlatformAdmin::factory()->count(5)->create();

// Create with custom attributes
$admin = PlatformAdmin::factory()->create([
    'name' => 'Custom Admin',
    'email' => 'custom@crmbeast.com',
]);
```

### OrganizationFeature Factory

```php
use App\Models\Platform\OrganizationFeature;
use App\Models\Organization;

$org = Organization::factory()->create();

// Create with default features
$features = OrganizationFeature::factory()->create([
    'organization_id' => $org->id,
]);

// Create with all features enabled
$features = OrganizationFeature::factory()->allFeatures()->create([
    'organization_id' => $org->id,
]);

// Create trial account
$features = OrganizationFeature::factory()->trial()->create([
    'organization_id' => $org->id,
]);

// Create expired trial
$features = OrganizationFeature::factory()->trialEnded()->create([
    'organization_id' => $org->id,
]);

// Create past due subscription
$features = OrganizationFeature::factory()->pastDue()->create([
    'organization_id' => $org->id,
]);
```

---

## 🎯 Route Names Reference

All platform routes use the `platform.` prefix:

| Action | Method | Route | Name |
|--------|--------|-------|------|
| Login Page | GET | `/admin/login` | `platform.login` |
| Login Submit | POST | `/admin/login` | `platform.login.store` |
| Logout | POST | `/admin/logout` | `platform.logout` |
| Dashboard | GET | `/admin/dashboard` | `platform.dashboard` |
| Organizations List | GET | `/admin/organizations` | `platform.organizations.index` |
| Organization Details | GET | `/admin/organizations/{org}` | `platform.organizations.show` |
| Update Features | PATCH | `/admin/organizations/{org}/features` | `platform.organizations.features.update` |
| Update Subscription | PATCH | `/admin/organizations/{org}/subscription` | `platform.organizations.subscription.update` |
| Admins List | GET | `/admin/admins` | `platform.admins.index` |
| Create Admin | POST | `/admin/admins` | `platform.admins.store` |
| Update Admin | PATCH | `/admin/admins/{admin}` | `platform.admins.update` |
| Delete Admin | DELETE | `/admin/admins/{admin}` | `platform.admins.destroy` |

---

## 🔧 Artisan Commands

```bash
# Run platform migration
./vendor/bin/sail artisan migrate --path=database/migrations/2026_02_07_000000_create_platform_infrastructure.php

# Seed platform admin
./vendor/bin/sail artisan db:seed --class=PlatformSeeder

# List platform routes
./vendor/bin/sail artisan route:list --name=platform

# Check platform admin count
./vendor/bin/sail artisan tinker --execute="echo App\Models\Platform\PlatformAdmin::count()"
```

---

## 🐛 Troubleshooting

### Issue: "Class PlatformAdmin not found"
**Solution:** Run `composer dump-autoload`

### Issue: "Table platform_admins doesn't exist"
**Solution:** Run the migration:
```bash
./vendor/bin/sail artisan migrate --path=database/migrations/2026_02_07_000000_create_platform_infrastructure.php
```

### Issue: "No platform admin exists"
**Solution:** Run the seeder:
```bash
./vendor/bin/sail artisan db:seed --class=PlatformSeeder
```

### Issue: "Auth guard [platform] not defined"
**Solution:** Clear config cache:
```bash
./vendor/bin/sail artisan config:clear
./vendor/bin/sail artisan config:cache
```

---

## 📚 Related Files

- **Models:** `app/Models/Platform/PlatformAdmin.php`, `OrganizationFeature.php`
- **Factories:** `database/factories/Platform/PlatformAdminFactory.php`, `OrganizationFeatureFactory.php`
- **Migration:** `database/migrations/2026_02_07_000000_create_platform_infrastructure.php`
- **Seeder:** `database/seeders/PlatformSeeder.php`
- **Routes:** `routes/platform.php`
- **Config:** `config/auth.php`
- **Bootstrap:** `bootstrap/app.php`

---

## ✅ Verification Checklist

Run this in tinker to verify everything is working:

```php
// In artisan tinker
use App\Models\Platform\PlatformAdmin;
use App\Models\Platform\OrganizationFeature;

// 1. Check admin exists
PlatformAdmin::count(); // Should be > 0

// 2. Check auth config
config('auth.guards.platform'); // Should return array

// 3. Check routes
Route::getRoutes()->match(Request::create('/admin/login')); // Should not throw

// 4. Test factory
PlatformAdmin::factory()->create(); // Should create successfully

// 5. Test authentication
$admin = PlatformAdmin::first();
auth('platform')->login($admin);
auth('platform')->check(); // Should return true
```

---

**Need help?** Check the full implementation docs: `PLATFORM_LAYER_IMPLEMENTATION.md`
