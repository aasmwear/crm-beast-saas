# Platform UI - Quick Start Guide

## 🚀 Quick Access

### Login to Platform
```
URL: http://localhost:8080/admin/login
Email: admin@crmbeast.com
Password: password
```

## 📁 File Structure

```
resources/js/
├── Layouts/
│   └── PlatformLayout.vue          # Main platform layout with sidebar
│
├── Pages/
│   └── Platform/
│       ├── Auth/
│       │   └── Login.vue           # Platform login page
│       └── Dashboard/
│           └── Index.vue           # Dashboard with KPIs

app/Http/Controllers/Platform/
├── Auth/
│   └── AuthenticatedSessionController.php
└── DashboardController.php         # Dashboard data provider
```

## 🎨 Theme Customization

### Colors

```css
/* Platform-specific colors */
Background: bg-slate-950
Sidebar: bg-slate-950/95 backdrop-blur-xl
Cards: glass-card (from theme.css)
Accents: from-purple-600 to-blue-600
Borders: border-white/10
Text: text-white/60 (muted), text-white (emphasis)
```

### Gradient Logo

```vue
<div class="w-10 h-10 rounded-lg bg-gradient-to-br from-purple-600 to-blue-600">
  <!-- Logo icon -->
</div>
```

## 🧩 Component Usage

### PlatformLayout

```vue
<template>
  <PlatformLayout>
    <!-- Your page content here -->
    <h1>Page Title</h1>
    <p>Content...</p>
  </PlatformLayout>
</template>

<script setup lang="ts">
import PlatformLayout from '@/Layouts/PlatformLayout.vue';
</script>
```

### KPI Card (Dashboard)

```vue
<Card class="card-neo">
  <div class="flex items-start justify-between">
    <div>
      <p class="text-xs uppercase tracking-wider text-white/50 mb-2">
        Metric Label
      </p>
      <p class="text-3xl font-bold text-white mb-1">
        {{ value }}
      </p>
      <div class="flex items-center gap-2">
        <span class="text-xs text-green-400">+12.5%</span>
        <span class="text-xs text-white/40">vs last month</span>
      </div>
    </div>
    <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-green-600/20 to-emerald-600/20 border border-green-500/30">
      <!-- Icon SVG -->
    </div>
  </div>
</Card>
```

### Table (Dashboard)

```vue
<Card class="card-neo">
  <div class="mb-6">
    <h2 class="text-lg font-bold text-white mb-1">Table Title</h2>
    <p class="text-sm text-white/50">Description</p>
  </div>

  <div class="overflow-hidden rounded-lg border border-white/10">
    <table class="w-full">
      <thead>
        <tr class="bg-white/5 border-b border-white/10">
          <th class="px-6 py-3 text-left text-xs font-medium text-white/70 uppercase">
            Column
          </th>
        </tr>
      </thead>
      <tbody class="divide-y divide-white/10">
        <tr class="hover:bg-white/5 transition">
          <td class="px-6 py-4">Data</td>
        </tr>
      </tbody>
    </table>
  </div>
</Card>
```

## 🔧 Adding New Pages

### 1. Create Vue Component

```bash
# Create new page
touch resources/js/Pages/Platform/Organizations/Index.vue
```

```vue
<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PlatformLayout from '@/Layouts/PlatformLayout.vue';

defineProps<{
  organizations: Array<any>;
}>();
</script>

<template>
  <Head title="Organizations" />
  
  <PlatformLayout>
    <h1 class="text-3xl font-bold text-white mb-8">Organizations</h1>
    <!-- Your content -->
  </PlatformLayout>
</template>
```

### 2. Create Controller

```php
<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use Inertia\Inertia;

class OrganizationController extends Controller
{
    public function index()
    {
        $organizations = Organization::all();
        
        return Inertia::render('Platform/Organizations/Index', [
            'organizations' => $organizations,
        ]);
    }
}
```

### 3. Add Route

```php
// routes/platform.php
use App\Http\Controllers\Platform\OrganizationController;

Route::middleware('auth:platform')->group(function () {
    Route::get('/organizations', [OrganizationController::class, 'index'])
        ->name('organizations.index');
});
```

### 4. Update Sidebar Navigation

```vue
<!-- resources/js/Layouts/PlatformLayout.vue -->
<Link
  :href="route('platform.organizations.index')"
  class="platform-nav-item"
  :class="{ 'active': isCurrent('platform.organizations.index') }"
>
  <svg class="w-5 h-5" viewBox="0 0 24 24">...</svg>
  <span>Organizations</span>
</Link>
```

## 🎯 Common Patterns

### Active Navigation State

```vue
<Link
  :href="route('platform.dashboard')"
  class="platform-nav-item"
  :class="{ 'active': isCurrent('platform.dashboard') }"
>
  Navigation Item
</Link>
```

### Status Badge

```vue
<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-600/20 text-blue-400 border border-blue-500/30">
  Active
</span>
```

### Loading State

```vue
<PrimaryButton :disabled="form.processing">
  <svg v-if="form.processing" class="animate-spin -ml-1 mr-2 h-4 w-4" viewBox="0 0 24 24">
    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"/>
    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
  </svg>
  <span v-if="!form.processing">Submit</span>
  <span v-else>Processing...</span>
</PrimaryButton>
```

### Empty State

```vue
<div class="flex flex-col items-center justify-center py-12 text-white/40">
  <svg class="w-12 h-12 mb-3" viewBox="0 0 24 24">...</svg>
  <p class="text-sm font-medium">No data available</p>
  <p class="text-xs mt-1">Description of empty state</p>
</div>
```

## 🎨 Icon Library

### Dashboard
```vue
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
  <rect x="3" y="3" width="7" height="7" rx="1"/>
  <rect x="14" y="3" width="7" height="7" rx="1"/>
  <rect x="14" y="14" width="7" height="7" rx="1"/>
  <rect x="3" y="14" width="7" height="7" rx="1"/>
</svg>
```

### Users/Organizations
```vue
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
  <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/>
  <circle cx="9" cy="7" r="4"/>
  <path d="M23 21v-2a4 4 0 00-3-3.87"/>
  <path d="M16 3.13a4 4 0 010 7.75"/>
</svg>
```

### System Health
```vue
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
  <path d="M22 12h-4l-3 9L9 3l-3 9H2"/>
</svg>
```

### Settings
```vue
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
  <circle cx="12" cy="12" r="3"/>
  <path d="M12 1v6m0 6v6m8-9h-6m-6 0H2"/>
</svg>
```

## 📊 Data Formatting

### Currency
```typescript
const formatCurrency = (value: number) => {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
    minimumFractionDigits: 0,
  }).format(value);
};
```

### Date
```typescript
const formatDate = (date: string) => {
  return new Date(date).toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  });
};
```

### Numbers
```typescript
const total = 1234567;
total.toLocaleString(); // "1,234,567"
```

## 🔍 Debugging

### Check Routes
```bash
./vendor/bin/sail artisan route:list --name=platform
```

### Build Assets
```bash
npm run build
```

### Clear Cache
```bash
./vendor/bin/sail artisan config:clear
./vendor/bin/sail artisan view:clear
```

## 🚀 Development Workflow

### 1. Make Changes
```bash
# Edit Vue files
nano resources/js/Pages/Platform/Dashboard/Index.vue
```

### 2. Build
```bash
# Production build
npm run build

# Development with watch (if needed)
npm run dev
```

### 3. Test
```
Visit: http://localhost:8080/admin/login
Login and verify changes
```

## 📝 Tips & Best Practices

### Component Organization
```
✅ DO: Reuse existing components (Card, InputLabel, etc.)
✅ DO: Use PlatformLayout for consistency
✅ DO: Follow dark glass theme patterns
❌ DON'T: Create duplicate components
❌ DON'T: Mix tenant and platform styles
```

### Styling
```
✅ DO: Use Tailwind utility classes
✅ DO: Follow slate-950 color scheme
✅ DO: Use backdrop-blur for glass effect
❌ DON'T: Add custom CSS unless necessary
❌ DON'T: Use inline styles
```

### Performance
```
✅ DO: Lazy load heavy components
✅ DO: Optimize images
✅ DO: Use production builds
❌ DON'T: Load unnecessary data
❌ DON'T: Use dev server in production
```

## 🆘 Troubleshooting

### Issue: Page not rendering
**Solution:** Clear Inertia cache and rebuild
```bash
rm -rf public/build
npm run build
```

### Issue: Styles not applying
**Solution:** Check Tailwind config and rebuild
```bash
npm run build
```

### Issue: Route not found
**Solution:** Check route registration
```bash
./vendor/bin/sail artisan route:list --name=platform
```

### Issue: Props not passed
**Solution:** Check controller return value
```php
return Inertia::render('Platform/Dashboard/Index', [
    'total_mrr' => $totalMrr, // ✅ Correct
]);
```

## 📚 Related Documentation

- **Implementation Guide**: `PLATFORM_UI_IMPLEMENTATION.md`
- **Auth Controllers**: `PLATFORM_AUTH_IMPLEMENTATION.md`
- **Infrastructure**: `PLATFORM_LAYER_IMPLEMENTATION.md`
- **Quick Reference**: `PLATFORM_QUICK_START.md`

---

**Need Help?** Check the full documentation or review existing pages for patterns and examples.
