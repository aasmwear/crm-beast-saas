# 🔴 Realtime Engine Quick Start (Laravel Reverb)

**Status:** Phase 3 Complete ✅  
**Stack:** Laravel Reverb + Laravel Echo + Vue 3 Composables

---

## 📋 Table of Contents

1. [What Was Implemented](#what-was-implemented)
2. [Environment Setup](#environment-setup)
3. [Running the Reverb Server](#running-the-reverb-server)
4. [Backend Usage (Broadcasting Events)](#backend-usage-broadcasting-events)
5. [Frontend Usage (Listening to Events)](#frontend-usage-listening-to-events)
6. [Channel Authorization](#channel-authorization)
7. [Debugging](#debugging)

---

## What Was Implemented

### Backend (Laravel)

1. **Events (`app/Events/`):**
   - ✅ `TaskMoved` - Broadcasts when a task status/position changes
   - ✅ `TaskUpdated` - Broadcasts when task details are modified
   - ✅ `PlatformAlert` - Broadcasts platform-wide alerts to Super Admins

2. **Channel Authorization (`routes/channels.php`):**
   - ✅ `org.{orgId}.projects.{projectId}` - Private project channels (tenant-scoped)
   - ✅ `platform-announcements` - Public channel for Super Admins
   - ✅ Tenant isolation enforcement (users can only join channels for their organization)

3. **Configuration:**
   - ✅ `config/broadcasting.php` - Broadcasting driver configuration
   - ✅ `config/reverb.php` - Reverb server configuration

### Frontend (Vue 3 + TypeScript)

1. **Composable (`resources/js/Composables/useRealtime.ts`):**
   - ✅ `subscribeToProject()` - Subscribe to project task updates
   - ✅ `subscribeToPlatform()` - Subscribe to platform announcements (Super Admin)
   - ✅ `unsubscribeAll()` - Clean up all subscriptions
   - ✅ Automatic cleanup on component unmount

2. **Echo Bootstrap (`resources/js/bootstrap.js`):**
   - ✅ Laravel Echo initialization with Reverb
   - ✅ CSRF token authentication

3. **Type Definitions:**
   - ✅ TypeScript interfaces for all event payloads
   - ✅ Global Window types for Echo and Pusher

---

## Environment Setup

### 1. Update Your `.env` File

Copy the following configuration to your `.env` file:

```env
# Broadcasting & Queue
BROADCAST_CONNECTION=reverb
QUEUE_CONNECTION=redis

# Reverb Configuration
REVERB_APP_ID=crm-beast
REVERB_APP_KEY=your-reverb-app-key-here
REVERB_APP_SECRET=your-reverb-app-secret-here
REVERB_HOST=localhost
REVERB_PORT=8080
REVERB_SCHEME=http

# Reverb Server
REVERB_SERVER_HOST=0.0.0.0
REVERB_SERVER_PORT=8080

# Reverb Scaling (for Redis-backed multi-server)
REVERB_SCALING_ENABLED=false

# Vite (Frontend) - These are auto-populated from above
VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```

### 2. Generate Reverb Keys

If you need to generate new secure keys:

```bash
php artisan reverb:install
```

Or manually generate secure strings for `REVERB_APP_KEY` and `REVERB_APP_SECRET`.

### 3. Ensure Redis is Running

Reverb requires Redis for queue management:

```bash
# If using Docker/Sail
./vendor/bin/sail up -d redis

# Or check if Redis is running locally
redis-cli ping
```

---

## Running the Reverb Server

### Development (Single Terminal)

Open a new terminal and run:

```bash
php artisan reverb:start
```

You should see:

```
Server started on 0.0.0.0:8080
Press Ctrl+C to stop the server
```

### Development (Background with Supervisor)

For production or development with multiple processes, use Supervisor or similar:

```ini
[program:reverb]
command=php /path/to/crm-beast-saas/artisan reverb:start
directory=/path/to/crm-beast-saas
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/log/reverb.log
```

### Queue Worker (Required!)

Events are queued by default. Run the queue worker:

```bash
php artisan queue:work redis
```

**IMPORTANT:** Without the queue worker, events won't be broadcast!

---

## Backend Usage (Broadcasting Events)

### Example 1: Broadcasting Task Moved Event

```php
use App\Events\TaskMoved;

// When a task is moved on the Kanban board
TaskMoved::dispatch(
    taskId: $task->id,
    projectId: $task->project_id,
    organizationId: $task->organization_id,
    newStatus: 'in_progress',
    newSortOrder: 'aaa123',
    movedBy: auth()->id(),
);
```

### Example 2: Broadcasting Task Updated Event

```php
use App\Events\TaskUpdated;

// When a task is updated (title, description, etc.)
TaskUpdated::dispatch(
    taskId: $task->id,
    projectId: $task->project_id,
    organizationId: $task->organization_id,
    changes: [
        'title' => 'New Task Title',
        'priority' => 'high',
    ],
    updatedBy: auth()->id(),
);
```

### Example 3: Broadcasting Platform Alert (Super Admin)

```php
use App\Events\PlatformAlert;

// Platform-wide announcement
PlatformAlert::dispatch(
    message: 'System maintenance scheduled for 2am UTC',
    level: 'warning',
    metadata: [
        'scheduled_at' => '2026-02-10 02:00:00',
        'duration' => '2 hours',
    ],
);
```

---

## Frontend Usage (Listening to Events)

### Example 1: Kanban Board Component

```vue
<script setup lang="ts">
import { onMounted, onUnmounted } from 'vue';
import { useRealtime } from '@/Composables/useRealtime';
import { usePage } from '@inertiajs/vue3';

const page = usePage();
const { subscribeToProject, unsubscribeAll } = useRealtime();

// Props
const props = defineProps<{
    projectId: number;
    organizationId: number;
}>();

// Reactive tasks state
const tasks = ref([...]);

onMounted(() => {
    // Subscribe to project realtime updates
    subscribeToProject(props.organizationId, props.projectId, {
        onTaskMoved: (data) => {
            console.log('Task moved in realtime:', data);
            
            // Update local task state
            const task = tasks.value.find(t => t.id === data.task_id);
            if (task) {
                task.status = data.new_status;
                task.sort_order = data.new_sort_order;
                // Re-sort tasks
                tasks.value.sort((a, b) => a.sort_order.localeCompare(b.sort_order));
            }
        },
        
        onTaskUpdated: (data) => {
            console.log('Task updated in realtime:', data);
            
            // Apply changes to local task
            const task = tasks.value.find(t => t.id === data.task_id);
            if (task) {
                Object.assign(task, data.changes);
            }
        },
    });
});

onUnmounted(() => {
    unsubscribeAll();
});
</script>
```

### Example 2: Platform Admin Dashboard

```vue
<script setup lang="ts">
import { onMounted } from 'vue';
import { useRealtime } from '@/Composables/useRealtime';

const { subscribeToPlatform } = useRealtime();

const alerts = ref([]);

onMounted(() => {
    // Super Admin only
    subscribeToPlatform({
        onAlert: (data) => {
            console.log('Platform alert:', data);
            
            // Show toast notification
            alerts.value.unshift({
                message: data.message,
                level: data.level,
                timestamp: data.timestamp,
            });
        },
    });
});
</script>
```

---

## Channel Authorization

### How It Works

1. **Client Requests Authorization:**
   - When Echo connects to a private channel, it sends a POST request to `/broadcasting/auth`

2. **Server Validates:**
   - Laravel checks the authorization callback in `routes/channels.php`
   - For `org.{orgId}.projects.{projectId}`, it verifies:
     - User belongs to the organization
     - Project exists and belongs to that organization
     - User has access to the project (using visibility scopes)

3. **Connection Established or Denied:**
   - If authorized → connection established
   - If denied → connection refused (user won't receive events)

### Security Notes

- **Tenant Isolation:** Users can ONLY join channels for organizations they belong to
- **Project Visibility:** Respects the existing `Project::visibleTo($user)` scope
- **Super Admin Bypass:** Platform announcements check `is_super_admin` flag

---

## Debugging

### Check if Echo is Initialized

In browser console:

```javascript
window.Echo
// Should return Echo instance

window.Echo.connector.pusher.connection.state
// Should return 'connected'
```

### Check Active Channels

```javascript
Object.keys(window.Echo.connector.channels)
// Returns array of channel names you're subscribed to
```

### Enable Reverb Debug Logging

In `.env`:

```env
LOG_LEVEL=debug
```

Then check logs:

```bash
php artisan reverb:start --debug
```

### Common Issues

1. **Events Not Broadcasting:**
   - ✅ Check queue worker is running (`php artisan queue:work`)
   - ✅ Check `BROADCAST_CONNECTION=reverb` in `.env`
   - ✅ Check `QUEUE_CONNECTION=redis` in `.env`

2. **Authorization Failed (403):**
   - ✅ Check user belongs to organization
   - ✅ Check user has access to project
   - ✅ Check CSRF token is present in page meta tags

3. **Connection Failed:**
   - ✅ Check Reverb server is running (`php artisan reverb:start`)
   - ✅ Check `REVERB_HOST` and `REVERB_PORT` match in `.env` and Vite config
   - ✅ Check Redis is running

4. **TypeScript Errors:**
   - ✅ Rebuild frontend: `npm run build`
   - ✅ Check `resources/js/types/global.d.ts` is imported

---

## Next Steps

### Phase 4: Implementation in Controllers

Now that the infrastructure is ready, you can:

1. **Add Event Dispatching to Controllers:**
   - `TaskController::update()` → dispatch `TaskUpdated`
   - `TaskController::move()` → dispatch `TaskMoved`

2. **Build Realtime UI Components:**
   - Kanban board with live updates
   - Task detail modal with live changes
   - Platform admin dashboard with alerts

3. **Add Optimistic Updates:**
   - Update UI immediately, then sync with server response
   - Revert if server returns error

### Phase 5: Advanced Features

- **Presence Channels:** Show who's viewing a project
- **Typing Indicators:** Show who's editing a task
- **Notification Debouncing:** Group multiple updates into one notification

---

## Resources

- [Laravel Reverb Documentation](https://laravel.com/docs/11.x/reverb)
- [Laravel Echo Documentation](https://laravel.com/docs/11.x/broadcasting#client-side-installation)
- [Pusher Protocol Documentation](https://pusher.com/docs/channels/library_auth_reference/pusher-websockets-protocol/)

---

**Phase 3 Complete!** 🎉  
The Realtime Engine is ready to power live updates across CRM Beast.
