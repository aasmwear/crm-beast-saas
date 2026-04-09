<?php

declare(strict_types=1);

namespace Tests\Feature\Projects;

use App\Models\Client;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\ProjectTaskProgressService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

final class ProjectTaskProgressDenormTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function actingUserWithProjectsView(Organization $org): User
    {
        $user = User::factory()->create(['active_organization_id' => $org->id]);
        $org->users()->attach($user->id);
        $role = Role::create([
            'name' => 'proj-denorm-' . uniqid(),
            'guard_name' => 'web',
            'team_id' => $org->id,
        ]);
        $role->givePermissionTo('projects.view');
        app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);
        $user->assignRole($role);

        return $user;
    }

    public function test_task_create_updates_project_denormalized_counters(): void
    {
        $org = Organization::factory()->create(['slug' => 'denorm-acme']);
        $user = User::factory()->create(['active_organization_id' => $org->id]);
        $org->users()->attach($user->id);
        $role = Role::create([
            'name' => 'proj-task-create-' . uniqid(),
            'guard_name' => 'web',
            'team_id' => $org->id,
        ]);
        $role->givePermissionTo(['projects.view', 'tasks.create']);
        app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);
        $user->assignRole($role);
        $client = Client::factory()->create(['organization_id' => $org->id]);
        $project = Project::factory()->create([
            'organization_id' => $org->id,
            'client_id' => $client->id,
            'project_manager_id' => $user->id,
        ]);

        $this->assertSame(0, $project->fresh()->tasks_count);

        $this->actingAs($user)->post(route('tasks.store', ['organization' => $org->slug]), [
            'project_id' => $project->id,
            'title' => 'New task',
            'status' => 'open',
        ])->assertRedirect();

        $project->refresh();
        $this->assertSame(1, $project->tasks_count);
        $this->assertSame(1, $project->open_tasks_count);
        $this->assertSame(0, $project->completed_tasks_count);
        $this->assertSame(0, $project->progress_percent);
    }

    public function test_status_change_to_done_updates_completed_and_progress(): void
    {
        $org = Organization::factory()->create(['slug' => 'denorm-acme-2']);
        $user = User::factory()->create(['active_organization_id' => $org->id]);
        $org->users()->attach($user->id);
        $role = Role::create([
            'name' => 'task-editor-' . uniqid(),
            'guard_name' => 'web',
            'team_id' => $org->id,
        ]);
        $role->givePermissionTo(['projects.view', 'tasks.view', 'tasks.update']);
        app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);
        $user->assignRole($role);

        $client = Client::factory()->create(['organization_id' => $org->id]);
        $project = Project::factory()->create([
            'organization_id' => $org->id,
            'client_id' => $client->id,
            'project_manager_id' => $user->id,
        ]);

        $task = Task::factory()->create([
            'organization_id' => $org->id,
            'project_id' => $project->id,
            'status' => 'Open',
        ]);

        $project->refresh();
        $this->assertSame(1, $project->tasks_count);
        $this->assertSame(0, $project->progress_percent);

        $this->actingAs($user)->put(
            route('tasks.update', ['organization' => $org->slug, 'task' => $task->id]),
            ['status' => 'Done']
        )->assertRedirect();

        $project->refresh();
        $this->assertSame(1, $project->tasks_count);
        $this->assertSame(0, $project->open_tasks_count);
        $this->assertSame(1, $project->completed_tasks_count);
        $this->assertSame(100, $project->progress_percent);
    }

    public function test_soft_delete_updates_project_counters(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->create(['active_organization_id' => $org->id]);
        $org->users()->attach($user->id);
        $role = Role::create([
            'name' => 'task-del-' . uniqid(),
            'guard_name' => 'web',
            'team_id' => $org->id,
        ]);
        $role->givePermissionTo(['tasks.view', 'tasks.delete']);
        app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);
        $user->assignRole($role);

        $client = Client::factory()->create(['organization_id' => $org->id]);
        $project = Project::factory()->create([
            'organization_id' => $org->id,
            'client_id' => $client->id,
            'project_manager_id' => $user->id,
        ]);

        $task = Task::factory()->create([
            'organization_id' => $org->id,
            'project_id' => $project->id,
        ]);

        $project->refresh();
        $this->assertSame(1, $project->tasks_count);

        $this->actingAs($user)->delete(
            route('tasks.destroy', ['organization' => $org->slug, 'task' => $task->id])
        )->assertRedirect();

        $project->refresh();
        $this->assertSame(0, $project->tasks_count);
        $this->assertSame(0, $project->open_tasks_count);
        $this->assertSame(0, $project->completed_tasks_count);
        $this->assertSame(0, $project->progress_percent);
    }

    public function test_projects_index_uses_denormalized_progress_in_props(): void
    {
        $org = Organization::factory()->create(['slug' => 'denorm-index']);
        $user = $this->actingUserWithProjectsView($org);
        $client = Client::factory()->create(['organization_id' => $org->id]);
        $project = Project::factory()->create([
            'organization_id' => $org->id,
            'client_id' => $client->id,
            'project_manager_id' => $user->id,
        ]);

        Task::factory()->create([
            'organization_id' => $org->id,
            'project_id' => $project->id,
            'status' => 'Done',
        ]);
        Task::factory()->create([
            'organization_id' => $org->id,
            'project_id' => $project->id,
            'status' => 'Open',
        ]);

        $project->refresh();
        $this->assertSame(50, $project->progress_percent);

        $response = $this->actingAs($user)->get(
            route('projects.index', ['organization' => $org->slug])
        );

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Projects/Index')
                ->has('projects.data', 1)
                ->where('projects.data.0.progress', 50));
    }

    public function test_completed_status_rules_match_service(): void
    {
        $this->assertTrue(ProjectTaskProgressService::isTaskStatusCompleted('DONE'));
        $this->assertTrue(ProjectTaskProgressService::isTaskStatusCompleted('Completed'));
        $this->assertFalse(ProjectTaskProgressService::isTaskStatusCompleted('Approved'));
        $this->assertFalse(ProjectTaskProgressService::isTaskStatusCompleted('In Progress'));
    }

    public function test_org_b_project_denorm_independent_of_org_a(): void
    {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();

        $clientA = Client::factory()->create(['organization_id' => $orgA->id]);
        $clientB = Client::factory()->create(['organization_id' => $orgB->id]);

        $projectA = Project::factory()->create([
            'organization_id' => $orgA->id,
            'client_id' => $clientA->id,
        ]);
        $projectB = Project::factory()->create([
            'organization_id' => $orgB->id,
            'client_id' => $clientB->id,
        ]);

        Task::factory()->create([
            'organization_id' => $orgA->id,
            'project_id' => $projectA->id,
        ]);
        Task::factory()->create([
            'organization_id' => $orgB->id,
            'project_id' => $projectB->id,
        ]);
        Task::factory()->create([
            'organization_id' => $orgB->id,
            'project_id' => $projectB->id,
        ]);

        $projectA->refresh();
        $projectB->refresh();
        $this->assertSame(1, $projectA->tasks_count);
        $this->assertSame(2, $projectB->tasks_count);
    }
}
