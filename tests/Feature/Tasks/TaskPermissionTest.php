<?php

namespace Tests\Feature\Tasks;

use App\Models\Client;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class TaskPermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function createClientForOrg(Organization $org): Client
    {
        return Client::factory()->create([
            'organization_id' => $org->id,
            'company_name' => 'Test Client',
        ]);
    }

    private function createProjectForOrg(Organization $org, ?Client $client = null): Project
    {
        $client = $client ?? $this->createClientForOrg($org);

        return Project::factory()->create([
            'organization_id' => $org->id,
            'client_id' => $client->id,
            'title' => 'Test Project',
        ]);
    }

    public function test_user_with_tasks_view_can_list_tasks(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        $user = User::factory()->create([
            'active_organization_id' => $org->id,
        ]);
        $org->users()->attach($user->id);

        $role = Role::create([
            'name' => 'task-viewer',
            'guard_name' => 'web',
            'team_id' => $org->id,
        ]);
        $role->givePermissionTo('tasks.view');
        app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);
        $user->assignRole($role);

        $response = $this->actingAs($user)
            ->get(route('tasks.index', ['organization' => $org->slug]));

        $response->assertOk();
    }

    public function test_user_with_tasks_view_can_open_board(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        $user = User::factory()->create([
            'active_organization_id' => $org->id,
        ]);
        $org->users()->attach($user->id);

        $role = Role::create([
            'name' => 'task-viewer',
            'guard_name' => 'web',
            'team_id' => $org->id,
        ]);
        $role->givePermissionTo('tasks.view');
        app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);
        $user->assignRole($role);

        $response = $this->actingAs($user)
            ->get(route('tasks.board', ['organization' => $org->slug]));

        $response->assertOk();
    }

    public function test_user_without_tasks_view_gets_403_on_list(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        $user = User::factory()->create([
            'active_organization_id' => $org->id,
        ]);
        $org->users()->attach($user->id);

        $role = Role::create([
            'name' => 'no-tasks',
            'guard_name' => 'web',
            'team_id' => $org->id,
        ]);
        $role->syncPermissions([]);
        app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);
        $user->assignRole($role);

        $response = $this->actingAs($user)
            ->get(route('tasks.index', ['organization' => $org->slug]));

        $response->assertStatus(403);
    }

    public function test_user_with_tasks_create_can_create(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        $user = User::factory()->create([
            'active_organization_id' => $org->id,
        ]);
        $org->users()->attach($user->id);

        $role = Role::create([
            'name' => 'task-creator',
            'guard_name' => 'web',
            'team_id' => $org->id,
        ]);
        $role->givePermissionTo(['tasks.view', 'tasks.create']);
        app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);
        $user->assignRole($role);

        $project = $this->createProjectForOrg($org);

        $response = $this->actingAs($user)
            ->post(route('tasks.store', ['organization' => $org->slug]), [
                'project_id' => $project->id,
                'title' => 'New Task',
                'status' => 'Todo',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('tasks', [
            'organization_id' => $org->id,
            'project_id' => $project->id,
            'title' => 'New Task',
        ]);
    }

    public function test_user_without_tasks_edit_cannot_update(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        $user = User::factory()->create([
            'active_organization_id' => $org->id,
        ]);
        $org->users()->attach($user->id);

        $role = Role::create([
            'name' => 'view-only',
            'guard_name' => 'web',
            'team_id' => $org->id,
        ]);
        $role->givePermissionTo(['tasks.view']);
        app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);
        $user->assignRole($role);

        $project = $this->createProjectForOrg($org);
        $task = Task::factory()->create([
            'organization_id' => $org->id,
            'project_id' => $project->id,
            'title' => 'Existing Task',
            'status' => 'Todo',
        ]);

        $response = $this->actingAs($user)
            ->put(route('tasks.update', [
                'organization' => $org->slug,
                'task' => $task->id,
            ]), [
                'title' => 'Updated Title',
                'status' => 'In Progress',
            ]);

        $response->assertStatus(403);
        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'title' => 'Existing Task',
        ]);
    }

    public function test_user_without_tasks_edit_cannot_submit(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        $user = User::factory()->create([
            'active_organization_id' => $org->id,
        ]);
        $org->users()->attach($user->id);

        $role = Role::create([
            'name' => 'view-only',
            'guard_name' => 'web',
            'team_id' => $org->id,
        ]);
        $role->givePermissionTo(['tasks.view']);
        app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);
        $user->assignRole($role);

        $project = $this->createProjectForOrg($org);
        $task = Task::factory()->create([
            'organization_id' => $org->id,
            'project_id' => $project->id,
            'title' => 'Task to Submit',
            'status' => 'Todo',
        ]);

        $response = $this->actingAs($user)
            ->post(route('tasks.submit', [
                'organization' => $org->slug,
                'task' => $task->id,
            ]), [
                'submission' => ['https://example.com/work'],
                'submission_note' => 'Done',
            ]);

        $response->assertStatus(403);
    }

    public function test_user_without_tasks_edit_cannot_review(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        $user = User::factory()->create([
            'active_organization_id' => $org->id,
        ]);
        $org->users()->attach($user->id);

        $role = Role::create([
            'name' => 'view-only',
            'guard_name' => 'web',
            'team_id' => $org->id,
        ]);
        $role->givePermissionTo(['tasks.view']);
        app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);
        $user->assignRole($role);

        $project = $this->createProjectForOrg($org);
        $task = Task::factory()->create([
            'organization_id' => $org->id,
            'project_id' => $project->id,
            'title' => 'Task to Review',
            'status' => 'Submitted',
            'review_status' => 'Pending',
        ]);

        $response = $this->actingAs($user)
            ->post(route('tasks.review', [
                'organization' => $org->slug,
                'task' => $task->id,
            ]), [
                'review_status' => 'Approved',
                'comments' => [],
            ]);

        $response->assertStatus(403);
    }

    public function test_user_without_tasks_delete_cannot_delete(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        $user = User::factory()->create([
            'active_organization_id' => $org->id,
        ]);
        $org->users()->attach($user->id);

        $role = Role::create([
            'name' => 'no-delete',
            'guard_name' => 'web',
            'team_id' => $org->id,
        ]);
        $role->givePermissionTo(['tasks.view', 'tasks.edit']);
        app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);
        $user->assignRole($role);

        $project = $this->createProjectForOrg($org);
        $task = Task::factory()->create([
            'organization_id' => $org->id,
            'project_id' => $project->id,
        ]);

        $response = $this->actingAs($user)
            ->delete(route('tasks.destroy', [
                'organization' => $org->slug,
                'task' => $task->id,
            ]));

        $response->assertStatus(403);
        $this->assertDatabaseHas('tasks', ['id' => $task->id]);
    }

    public function test_user_without_tasks_view_gets_403_on_show(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        $user = User::factory()->create([
            'active_organization_id' => $org->id,
        ]);
        $org->users()->attach($user->id);

        $role = Role::create([
            'name' => 'other',
            'guard_name' => 'web',
            'team_id' => $org->id,
        ]);
        $role->syncPermissions(['projects.view']);
        app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);
        $user->assignRole($role);

        $project = $this->createProjectForOrg($org);
        $task = Task::factory()->create([
            'organization_id' => $org->id,
            'project_id' => $project->id,
        ]);

        $response = $this->actingAs($user)
            ->getJson(route('tasks.show', [
                'organization' => $org->slug,
                'task' => $task->id,
            ]));

        $response->assertStatus(403);
    }

    public function test_user_without_tasks_create_gets_403_on_store(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        $user = User::factory()->create([
            'active_organization_id' => $org->id,
        ]);
        $org->users()->attach($user->id);

        $role = Role::create([
            'name' => 'view-only',
            'guard_name' => 'web',
            'team_id' => $org->id,
        ]);
        $role->givePermissionTo(['tasks.view']);
        app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);
        $user->assignRole($role);

        $project = $this->createProjectForOrg($org);

        $response = $this->actingAs($user)
            ->post(route('tasks.store', ['organization' => $org->slug]), [
                'project_id' => $project->id,
                'title' => 'New Task',
                'status' => 'Todo',
            ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('tasks', [
            'organization_id' => $org->id,
            'title' => 'New Task',
        ]);
    }

    public function test_task_board_is_paginated_with_50_per_page_and_total_count(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        $user = User::factory()->create([
            'active_organization_id' => $org->id,
        ]);
        $org->users()->attach($user->id);

        $role = Role::create([
            'name' => 'task-board-paginated-viewer',
            'guard_name' => 'web',
            'team_id' => $org->id,
        ]);
        $role->givePermissionTo('tasks.view');
        app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);
        $user->assignRole($role);

        $client = $this->createClientForOrg($org);
        $project = Project::factory()->create([
            'organization_id' => $org->id,
            'client_id' => $client->id,
            'project_manager_id' => $user->id,
        ]);

        Task::factory()->count(55)->create([
            'organization_id' => $org->id,
            'project_id' => $project->id,
        ]);

        $this->actingAs($user)
            ->get(route('tasks.board', ['organization' => $org->slug]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Tasks/Board')
                ->where('tasks.total', 55)
                ->has('tasks.data', 50)
                ->has('tasks.links')
            );
    }

    public function test_task_board_respects_visibility_scope_for_non_admin_user(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        $user = User::factory()->create([
            'active_organization_id' => $org->id,
        ]);
        $org->users()->attach($user->id);

        $otherUser = User::factory()->create([
            'active_organization_id' => $org->id,
        ]);
        $org->users()->attach($otherUser->id);

        $role = Role::create([
            'name' => 'task-board-visibility-viewer',
            'guard_name' => 'web',
            'team_id' => $org->id,
        ]);
        $role->givePermissionTo('tasks.view');
        app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);
        $user->assignRole($role);
        $otherUser->assignRole($role);

        $client = $this->createClientForOrg($org);
        $visibleProject = Project::factory()->create([
            'organization_id' => $org->id,
            'client_id' => $client->id,
            'project_manager_id' => $user->id,
        ]);
        $hiddenProject = Project::factory()->create([
            'organization_id' => $org->id,
            'client_id' => $client->id,
            'project_manager_id' => $otherUser->id,
        ]);

        $visibleTask = Task::factory()->create([
            'organization_id' => $org->id,
            'project_id' => $visibleProject->id,
            'title' => 'Visible Task',
        ]);
        Task::factory()->create([
            'organization_id' => $org->id,
            'project_id' => $hiddenProject->id,
            'title' => 'Hidden Task',
            'assignees' => [],
        ]);

        $this->actingAs($user)
            ->get(route('tasks.board', ['organization' => $org->slug]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Tasks/Board')
                ->where('tasks.total', 1)
                ->has('tasks.data', 1)
                ->where('tasks.data.0.id', $visibleTask->id)
                ->where('tasks.data.0.title', 'Visible Task')
            );
    }

    public function test_task_board_pagination_links_preserve_query_string(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        $user = User::factory()->create([
            'active_organization_id' => $org->id,
        ]);
        $org->users()->attach($user->id);

        $role = Role::create([
            'name' => 'task-board-query-links-viewer',
            'guard_name' => 'web',
            'team_id' => $org->id,
        ]);
        $role->givePermissionTo('tasks.view');
        app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);
        $user->assignRole($role);

        $client = $this->createClientForOrg($org);
        $project = Project::factory()->create([
            'organization_id' => $org->id,
            'client_id' => $client->id,
            'project_manager_id' => $user->id,
        ]);
        Task::factory()->count(55)->create([
            'organization_id' => $org->id,
            'project_id' => $project->id,
        ]);

        $this->actingAs($user)
            ->get(route('tasks.board', [
                'organization' => $org->slug,
                'status' => 'todo',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Tasks/Board')
                ->where('tasks.links', static function ($links): bool {
                    return collect($links)
                        ->pluck('url')
                        ->filter()
                        ->contains(static fn (mixed $url): bool => is_string($url) && str_contains($url, 'status=todo'));
                })
            );
    }
}
