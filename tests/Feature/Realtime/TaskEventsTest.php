<?php

namespace Tests\Feature\Realtime;

use App\Events\TaskMoved;
use App\Events\TaskUpdated;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * TaskEventsTest.
 *
 * Tests that TaskController dispatches realtime events correctly.
 */
final class TaskEventsTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    private User $user;

    private Project $project;

    private Task $task;

    protected function setUp(): void
    {
        parent::setUp();

        // Create organization
        $this->org = Organization::factory()->create();

        // Create user and attach to organization
        $this->user = User::factory()->create();
        $this->org->users()->attach($this->user->id, ['is_owner' => true]);

        // Set active organization
        $this->user->update(['active_organization_id' => $this->org->id]);

        // Create project
        $this->project = Project::factory()->create([
            'organization_id' => $this->org->id,
            'title' => 'Test Project',
        ]);

        // Create task
        $this->task = Task::factory()->create([
            'organization_id' => $this->org->id,
            'project_id' => $this->project->id,
            'title' => 'Test Task',
            'status' => 'Todo',
        ]);
    }

    /**
     * Test: TaskMoved event is dispatched when task status changes.
     */
    public function test_task_moved_event_dispatched_on_status_change(): void
    {
        Event::fake([TaskMoved::class]);

        // Update task status
        $response = $this->actingAs($this->user)
            ->putJson(route('tasks.update', [
                'organization' => $this->org->slug,
                'task' => $this->task->id,
            ]), [
                'status' => 'In Progress',
            ]);

        $response->assertRedirect();

        // Assert TaskMoved event was dispatched
        Event::assertDispatched(TaskMoved::class, function ($event) {
            return $event->taskId === $this->task->id
                && $event->projectId === $this->project->id
                && $event->organizationId === $this->org->id
                && $event->newStatus === 'In Progress'
                && $event->movedBy === $this->user->id;
        });
    }

    /**
     * Test: TaskUpdated event is dispatched when task details change.
     */
    public function test_task_updated_event_dispatched_on_detail_change(): void
    {
        Event::fake([TaskUpdated::class]);

        // Update task details (title and priority)
        $response = $this->actingAs($this->user)
            ->putJson(route('tasks.update', [
                'organization' => $this->org->slug,
                'task' => $this->task->id,
            ]), [
                'title' => 'Updated Task Title',
                'priority' => 'high',
            ]);

        $response->assertRedirect();

        // Assert TaskUpdated event was dispatched
        Event::assertDispatched(TaskUpdated::class, function ($event) {
            return $event->taskId === $this->task->id
                && $event->projectId === $this->project->id
                && $event->organizationId === $this->org->id
                && isset($event->changes['title'])
                && $event->changes['title'] === 'Updated Task Title'
                && isset($event->changes['priority'])
                && $event->changes['priority'] === 'high'
                && $event->updatedBy === $this->user->id;
        });
    }

    /**
     * Test: Both TaskMoved and TaskUpdated dispatched when status + details change.
     */
    public function test_both_events_dispatched_on_combined_update(): void
    {
        Event::fake([TaskMoved::class, TaskUpdated::class]);

        // Update both status and details
        $response = $this->actingAs($this->user)
            ->putJson(route('tasks.update', [
                'organization' => $this->org->slug,
                'task' => $this->task->id,
            ]), [
                'status' => 'Done',
                'title' => 'Completed Task',
                'priority' => 'low',
            ]);

        $response->assertRedirect();

        // Assert TaskMoved event was dispatched
        Event::assertDispatched(TaskMoved::class, function ($event) {
            return $event->taskId === $this->task->id
                && $event->newStatus === 'Done';
        });

        // Assert TaskUpdated event was dispatched
        Event::assertDispatched(TaskUpdated::class, function ($event) {
            return $event->taskId === $this->task->id
                && isset($event->changes['title'])
                && $event->changes['title'] === 'Completed Task';
        });
    }

    /**
     * Test: Events are queued for broadcasting (not immediately broadcast).
     */
    public function test_events_are_queued_for_broadcasting(): void
    {
        Queue::fake();

        // Update task status
        $response = $this->actingAs($this->user)
            ->putJson(route('tasks.update', [
                'organization' => $this->org->slug,
                'task' => $this->task->id,
            ]), [
                'status' => 'In Progress',
            ]);

        $response->assertRedirect();

        // Assert event was pushed to queue
        Queue::assertPushed(function ($job) {
            $jobClass = get_class($job);

            // Laravel broadcasts events via BroadcastEvent job
            return str_contains($jobClass, 'BroadcastEvent')
                || str_contains($jobClass, 'CallQueuedListener');
        });
    }

    /**
     * Test: No events dispatched when no changes are made.
     */
    public function test_no_events_dispatched_when_no_changes(): void
    {
        Event::fake([TaskMoved::class, TaskUpdated::class]);

        // Update with no actual changes
        $response = $this->actingAs($this->user)
            ->putJson(route('tasks.update', [
                'organization' => $this->org->slug,
                'task' => $this->task->id,
            ]), [
                'status' => $this->task->status, // Same status
            ]);

        $response->assertRedirect();

        // Assert no events were dispatched
        Event::assertNotDispatched(TaskMoved::class);
        Event::assertNotDispatched(TaskUpdated::class);
    }

    /**
     * Test: Unauthorized user cannot trigger events.
     */
    public function test_unauthorized_user_cannot_trigger_events(): void
    {
        Event::fake([TaskMoved::class, TaskUpdated::class]);

        // Create unauthorized user (not in organization)
        $unauthorizedUser = User::factory()->create();

        // Attempt to update task
        $response = $this->actingAs($unauthorizedUser)
            ->putJson(route('tasks.update', [
                'organization' => $this->org->slug,
                'task' => $this->task->id,
            ]), [
                'status' => 'In Progress',
            ]);

        // Should be forbidden
        $response->assertForbidden();

        // Assert no events were dispatched
        Event::assertNotDispatched(TaskMoved::class);
        Event::assertNotDispatched(TaskUpdated::class);
    }
}
