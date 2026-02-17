# 🔴 Phase 3.5 - Realtime Task Events - Implementation Summary

**Status:** Complete ✅  
**Date:** 2026-02-07

---

## 📋 What Was Implemented

### 1. Backend: TaskController Realtime Events

**File:** `app/Http/Controllers/TaskController.php`

#### Changes Made:

1. **Imports Added:**
   ```php
   use App\Events\TaskMoved;
   use App\Events\TaskUpdated;
   ```

2. **Event Dispatching Logic in `update()` Method:**

   **TaskMoved Event** (Status Changes):
   - Dispatched when task `status` field changes
   - Payload includes: `taskId`, `projectId`, `organizationId`, `newStatus`, `newSortOrder`, `movedBy`
   - Used for real-time Kanban board updates

   **TaskUpdated Event** (Detail Changes):
   - Dispatched when fields like `title`, `description`, `priority`, `due_date`, `assignees`, `estimated_hours` change
   - Payload includes: `taskId`, `projectId`, `organizationId`, `changes` (array of changed fields), `updatedBy`
   - Used for real-time task detail panel updates

#### Implementation Details:

```php
// Detect status change
$statusChanged = array_key_exists('status', $update) 
    && ($before['status'] ?? null) !== ($after['status'] ?? null);

if ($statusChanged) {
    TaskMoved::dispatch(
        taskId: (int) $task->id,
        projectId: (int) $task->project_id,
        organizationId: (int) $task->organization_id,
        newStatus: (string) $after['status'],
        newSortOrder: (string) ($after['sort_order'] ?? ''),
        movedBy: (int) $request->user()->id,
    );
}

// Detect detail changes
$detailChanges = [];
foreach (['title', 'description', 'priority', 'due_date', 'assignees', 'estimated_hours'] as $field) {
    if (array_key_exists($field, $update) && ($before[$field] ?? null) !== ($after[$field] ?? null)) {
        $detailChanges[$field] = $after[$field];
    }
}

if (!empty($detailChanges)) {
    TaskUpdated::dispatch(
        taskId: (int) $task->id,
        projectId: (int) $task->project_id,
        organizationId: (int) $task->organization_id,
        changes: $detailChanges,
        updatedBy: (int) $request->user()->id,
    );
}
```

---

### 2. Frontend: Task Board Realtime Updates

**File:** `resources/js/Pages/Tasks/Board.vue`

#### Changes Made:

1. **Imports Added:**
   ```typescript
   import { useRealtime } from '@/Composables/useRealtime'
   import type { TaskMovedPayload, TaskUpdatedPayload } from '@/Composables/useRealtime'
   ```

2. **Props Extended:**
   ```typescript
   const props = defineProps<{
     organization: { id: number; name: string; slug: string }
     tasks: BoardTask[]
     projectId?: number // NEW: Optional project ID for single-project views
   }>()
   ```

3. **Local State for Realtime:**
   ```typescript
   const localTasks = ref<BoardTask[]>([...props.tasks])
   ```

4. **Realtime Subscription:**
   ```typescript
   onMounted(() => {
     if (props.projectId && isReady()) {
       subscribeToProject(props.organization.id, props.projectId, {
         onTaskMoved: handleTaskMoved,
         onTaskUpdated: handleTaskUpdated,
       })
     }
   })
   
   onUnmounted(() => {
     unsubscribeAll()
   })
   ```

5. **Event Handlers:**

   **TaskMoved Handler:**
   ```typescript
   function handleTaskMoved(data: TaskMovedPayload) {
     const task = localTasks.value.find(t => t.id === data.task_id)
     if (task) {
       task.status = data.new_status
       // Task automatically moves to correct column
     }
   }
   ```

   **TaskUpdated Handler:**
   ```typescript
   function handleTaskUpdated(data: TaskUpdatedPayload) {
     const task = localTasks.value.find(t => t.id === data.task_id)
     if (task) {
       if (data.changes.title) task.title = data.changes.title as string
       if (data.changes.priority) task.priority = data.changes.priority as string
       if (data.changes.due_date) task.due_date = data.changes.due_date as string
     }
   }
   ```

6. **Optimistic Updates:**
   ```typescript
   function updateStatus(task: BoardTask, columnKey: ColumnKey) {
     // Optimistic update
     const oldStatus = task.status
     task.status = col.label
     
     router.put(/*...*/, {
       onError: () => {
         task.status = oldStatus // Revert on error
       },
     })
   }
   ```

---

### 3. Testing: Realtime Event Verification

**File:** `tests/Feature/Realtime/TaskEventsTest.php`

#### Test Cases:

1. **`test_task_moved_event_dispatched_on_status_change()`**
   - Verifies TaskMoved event is dispatched when task status changes
   - Asserts correct payload (taskId, projectId, newStatus, movedBy)

2. **`test_task_updated_event_dispatched_on_detail_change()`**
   - Verifies TaskUpdated event is dispatched when task details change
   - Asserts changes array contains updated fields

3. **`test_both_events_dispatched_on_combined_update()`**
   - Tests simultaneous status + detail changes
   - Asserts both events are dispatched correctly

4. **`test_events_are_queued_for_broadcasting()`**
   - Verifies events are queued (not immediately broadcast)
   - Checks for BroadcastEvent job in queue

5. **`test_no_events_dispatched_when_no_changes()`**
   - Ensures no events fire when no actual changes occur

6. **`test_unauthorized_user_cannot_trigger_events()`**
   - Security: unauthorized users cannot trigger realtime events

#### Running Tests:

```bash
# Run all realtime tests
php artisan test --filter=Realtime

# Run specific test
php artisan test --filter=test_task_moved_event_dispatched_on_status_change

# Run with coverage
php artisan test --filter=Realtime --coverage
```

---

## 🚀 How It Works (End-to-End Flow)

### Scenario: User Drags Task from "Todo" to "In Progress"

1. **Frontend (Board.vue):**
   - User changes task status via dropdown
   - `updateStatus()` makes optimistic local update
   - Sends PUT request to `/org/{slug}/tasks/{id}`

2. **Backend (TaskController):**
   - Validates request and authorization
   - Updates task in database
   - Detects status change
   - Dispatches `TaskMoved` event to queue

3. **Queue Worker:**
   - Processes `TaskMoved` event
   - Broadcasts to channel: `private-org.{orgId}.projects.{projectId}`

4. **Reverb Server:**
   - Receives broadcast from queue worker
   - Pushes to all connected clients on that channel

5. **Other Connected Clients:**
   - Laravel Echo receives `task.moved` event
   - `handleTaskMoved()` updates local task state
   - Task automatically moves to correct column (no page refresh!)

6. **Audit Log:**
   - All changes logged via `AuditLogger`
   - Maintains full audit trail

---

## 🎯 Usage Examples

### Example 1: Single Project Task Board

```php
// In ProjectController@show or similar
public function show(Request $request, Project $project): Response
{
    $tasks = Task::where('project_id', $project->id)
        ->visibleTo($request->user())
        ->get();
    
    return Inertia::render('Tasks/Board', [
        'organization' => $org,
        'tasks' => $tasks,
        'projectId' => $project->id, // ← Enables realtime for this project
    ]);
}
```

### Example 2: Organization-Wide Task Board

```php
// In TaskController@index
public function index(Request $request): Response
{
    $tasks = Task::where('organization_id', $org->id)
        ->visibleTo($request->user())
        ->get();
    
    return Inertia::render('Tasks/Board', [
        'organization' => $org,
        'tasks' => $tasks,
        // No projectId → Realtime disabled (multi-project view)
    ]);
}
```

### Example 3: Manually Dispatching Events

```php
// In any controller or service
use App\Events\TaskMoved;

// Manual dispatch (useful for bulk operations, API, etc.)
TaskMoved::dispatch(
    taskId: $task->id,
    projectId: $task->project_id,
    organizationId: $task->organization_id,
    newStatus: 'Done',
    newSortOrder: 'zzz999',
    movedBy: auth()->id(),
);
```

---

## 🔍 Debugging Realtime Events

### Check if Events Are Dispatched

```bash
# Monitor queue in realtime
php artisan queue:work redis --verbose

# You should see:
# [TaskMoved] Dispatched to: private-org.1.projects.5
```

### Check if Events Reach Reverb

```bash
# In Reverb debug mode
php artisan reverb:start --debug

# You should see:
# [2026-02-07] Broadcasting to private-org.1.projects.5: task.moved
```

### Check Frontend Console

Open browser console on Task Board page:

```javascript
// Should see:
[TaskBoard] Subscribing to project realtime updates: 5
[Realtime] Subscribed to org.1.projects.5
[TaskBoard] Task moved: { task_id: 42, new_status: "In Progress", ... }
```

### Common Issues

1. **Events Not Dispatched:**
   - ✅ Check queue worker is running: `php artisan queue:work redis`
   - ✅ Check `QUEUE_CONNECTION=redis` in `.env`
   - ✅ Check `BROADCAST_CONNECTION=reverb` in `.env`

2. **Events Not Received in Frontend:**
   - ✅ Check Reverb is running: `php artisan reverb:start`
   - ✅ Check port 8080 is exposed in `compose.yaml`
   - ✅ Check browser console for Echo connection errors
   - ✅ Verify channel authorization in `routes/channels.php`

3. **Events Dispatched but Not Broadcasting:**
   - ✅ Events must implement `ShouldBroadcast` interface ✅ (Already done)
   - ✅ Check queue is processing: `php artisan queue:monitor`
   - ✅ Check Reverb logs for errors

---

## 📊 Performance Considerations

### Event Queuing
- ✅ All events are queued (not synchronous)
- ✅ No performance impact on HTTP requests
- ✅ Queue workers handle broadcasting

### Optimistic Updates
- ✅ UI updates immediately (optimistic)
- ✅ Reverts on error (error handling)
- ✅ No waiting for server response

### Channel Subscription
- ✅ Single subscription per project
- ✅ Automatic cleanup on component unmount
- ✅ No memory leaks

---

## 🔒 Security

### Authorization
- ✅ Channel authorization via `routes/channels.php`
- ✅ Users can only join channels for their organization
- ✅ Project visibility rules enforced

### Tenant Isolation
- ✅ Organization ID checked in events
- ✅ Channel names include organization ID
- ✅ No cross-tenant leakage

### Event Payload
- ✅ Only necessary data in payload
- ✅ No sensitive fields exposed
- ✅ User permissions respected

---

## 🎉 Next Steps

### Phase 4: Expand Realtime Features

1. **Project Board Realtime:**
   - Add realtime to `Projects/Board.vue`
   - Create `ProjectMoved` event

2. **Task Drawer Realtime:**
   - Update task details in realtime when viewing
   - Show "Someone else is editing" indicator

3. **Presence Channels:**
   - Show who's viewing a project
   - Online/offline status

4. **Typing Indicators:**
   - Show when someone is editing a task
   - Real-time cursor positions

5. **Notification Badges:**
   - Real-time notification count updates
   - Toast notifications for mentions

---

## ✅ Verification Checklist

- [x] TaskController dispatches TaskMoved on status change
- [x] TaskController dispatches TaskUpdated on detail change
- [x] Events are queued for broadcasting
- [x] Task Board subscribes to project channel
- [x] Task Board handles TaskMoved events
- [x] Task Board handles TaskUpdated events
- [x] Optimistic updates implemented
- [x] Tests verify event dispatching
- [x] Tests verify queue integration
- [x] Tests verify authorization
- [x] No linter errors
- [x] Documentation complete

---

**Phase 3.5 Complete!** 🎉

The Realtime Engine is now fully wired to the Task system. Users will see live updates as tasks move across the board, creating a truly collaborative experience.
