<?php

namespace Tests\Feature\Projects;

use App\Models\Client;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ProjectPermissionTest extends TestCase
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

    public function test_user_with_projects_view_can_list_projects(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        $user = User::factory()->create([
            'active_organization_id' => $org->id,
        ]);
        $org->users()->attach($user->id);

        $role = Role::create([
            'name' => 'project-viewer',
            'guard_name' => 'web',
            'team_id' => $org->id,
        ]);
        $role->givePermissionTo('projects.view');
        app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);
        $user->assignRole($role);

        $response = $this->actingAs($user)
            ->get(route('projects.index', ['organization' => $org->slug]));

        $response->assertOk();
    }

    public function test_user_with_projects_view_can_open_project(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        $user = User::factory()->create([
            'active_organization_id' => $org->id,
        ]);
        $org->users()->attach($user->id);

        $role = Role::create([
            'name' => 'project-viewer',
            'guard_name' => 'web',
            'team_id' => $org->id,
        ]);
        $role->givePermissionTo('projects.view');
        app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);
        $user->assignRole($role);

        $client = $this->createClientForOrg($org);
        $project = Project::factory()->create([
            'organization_id' => $org->id,
            'client_id' => $client->id,
            'title' => 'Test Project',
        ]);

        $response = $this->actingAs($user)
            ->get(route('projects.show', [
                'organization' => $org->slug,
                'project' => $project->id,
            ]));

        $response->assertOk();
    }

    public function test_user_without_projects_view_gets_403_on_list(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        $user = User::factory()->create([
            'active_organization_id' => $org->id,
        ]);
        $org->users()->attach($user->id);

        $role = Role::create([
            'name' => 'no-projects',
            'guard_name' => 'web',
            'team_id' => $org->id,
        ]);
        $role->syncPermissions([]);
        app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);
        $user->assignRole($role);

        $response = $this->actingAs($user)
            ->get(route('projects.index', ['organization' => $org->slug]));

        $response->assertStatus(403);
    }

    public function test_user_with_projects_create_can_create(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        $user = User::factory()->create([
            'active_organization_id' => $org->id,
        ]);
        $org->users()->attach($user->id);

        $role = Role::create([
            'name' => 'project-creator',
            'guard_name' => 'web',
            'team_id' => $org->id,
        ]);
        $role->givePermissionTo(['projects.view', 'projects.create']);
        app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);
        $user->assignRole($role);

        $client = $this->createClientForOrg($org);

        $response = $this->actingAs($user)
            ->post(route('projects.store', ['organization' => $org->slug]), [
                'title' => 'New Project',
                'status' => 'Not Started',
                'client_id' => $client->id,
                'user_ids' => [],
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('projects', [
            'organization_id' => $org->id,
            'title' => 'New Project',
        ]);
    }

    public function test_user_without_projects_edit_cannot_update(): void
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
        $role->givePermissionTo(['projects.view']);
        app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);
        $user->assignRole($role);

        $client = $this->createClientForOrg($org);
        $project = Project::factory()->create([
            'organization_id' => $org->id,
            'client_id' => $client->id,
            'title' => 'Existing Project',
        ]);

        $response = $this->actingAs($user)
            ->put(route('projects.update', [
                'organization' => $org->slug,
                'project' => $project->id,
            ]), [
                'title' => 'Updated Title',
                'status' => 'In Progress',
                'client_id' => $client->id,
                'billable' => false,
            ]);

        $response->assertStatus(403);
        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'title' => 'Existing Project',
        ]);
    }

    public function test_user_without_projects_delete_cannot_delete(): void
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
        $role->givePermissionTo(['projects.view', 'projects.edit']);
        app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);
        $user->assignRole($role);

        $client = $this->createClientForOrg($org);
        $project = Project::factory()->create([
            'organization_id' => $org->id,
            'client_id' => $client->id,
        ]);

        $response = $this->actingAs($user)
            ->delete(route('projects.destroy', [
                'organization' => $org->slug,
                'project' => $project->id,
            ]));

        $response->assertStatus(403);
        $this->assertDatabaseHas('projects', ['id' => $project->id]);
    }

    public function test_user_without_projects_view_gets_403_on_show(): void
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
        $role->syncPermissions(['tasks.view']);
        app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);
        $user->assignRole($role);

        $client = $this->createClientForOrg($org);
        $project = Project::factory()->create([
            'organization_id' => $org->id,
            'client_id' => $client->id,
        ]);

        $response = $this->actingAs($user)
            ->get(route('projects.show', [
                'organization' => $org->slug,
                'project' => $project->id,
            ]));

        $response->assertStatus(403);
    }

    public function test_user_without_projects_create_gets_403_on_store(): void
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
        $role->givePermissionTo(['projects.view']);
        app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);
        $user->assignRole($role);

        $response = $this->actingAs($user)
            ->post(route('projects.store', ['organization' => $org->slug]), [
                'title' => 'New Project',
                'status' => 'Not Started',
                'user_ids' => [],
            ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('projects', [
            'organization_id' => $org->id,
            'title' => 'New Project',
        ]);
    }

    public function test_user_without_projects_edit_gets_403_on_pipeline_update(): void
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
        $role->givePermissionTo(['projects.view']);
        app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);
        $user->assignRole($role);

        $client = $this->createClientForOrg($org);
        $project = Project::factory()->create([
            'organization_id' => $org->id,
            'client_id' => $client->id,
            'status' => 'Planned',
        ]);

        $response = $this->actingAs($user)
            ->post(route('projects.pipeline.update', [
                'organization' => $org->slug,
                'project' => $project->id,
            ]), ['status' => 'In Progress']);

        $response->assertStatus(403);
        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'status' => 'Planned',
        ]);
    }
}
