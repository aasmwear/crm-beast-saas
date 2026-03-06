<?php

namespace Tests\Feature\TenantIsolation;

use App\Models\Client;
use App\Models\Department;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Tenant isolation hardening: DepartmentController, TaskController::store,
 * and scoped route bindings.
 */
final class TenantIsolationHardeningTest extends TestCase
{
    use RefreshDatabase;

    private Organization $orgA;

    private Organization $orgB;

    private User $userA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->orgA = Organization::factory()->create(['slug' => 'org-a']);
        $this->orgB = Organization::factory()->create(['slug' => 'org-b']);

        $ownerRole = Role::where('name', 'Owner')->whereNull('team_id')->first();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->orgA->id);

        $this->userA = User::factory()->create(['active_organization_id' => $this->orgA->id]);
        $this->userA->organizations()->attach([$this->orgA->id]);
        $this->userA->assignRole($ownerRole);
    }

    public function test_task_store_rejects_project_id_from_another_organization(): void
    {
        $clientA = Client::factory()->create(['organization_id' => $this->orgA->id]);
        $projectA = Project::factory()->create([
            'organization_id' => $this->orgA->id,
            'client_id' => $clientA->id,
        ]);
        $clientB = Client::factory()->create(['organization_id' => $this->orgB->id]);
        $projectB = Project::factory()->create([
            'organization_id' => $this->orgB->id,
            'client_id' => $clientB->id,
        ]);

        $response = $this->actingAs($this->userA)
            ->postJson("/org/{$this->orgA->slug}/tasks", [
                'project_id' => $projectB->id,
                'title' => 'Cross-tenant task',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['project_id']);
    }

    public function test_task_store_accepts_project_id_from_same_organization(): void
    {
        $clientA = Client::factory()->create(['organization_id' => $this->orgA->id]);
        $projectA = Project::factory()->create([
            'organization_id' => $this->orgA->id,
            'client_id' => $clientA->id,
        ]);

        $response = $this->actingAs($this->userA)
            ->post("/org/{$this->orgA->slug}/tasks", [
                'project_id' => $projectA->id,
                'title' => 'Valid task',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('tasks', [
            'organization_id' => $this->orgA->id,
            'project_id' => $projectA->id,
            'title' => 'Valid task',
        ]);
    }

    public function test_department_store_scopes_organization_id_from_route(): void
    {
        $response = $this->actingAs($this->userA)
            ->post("/org/{$this->orgA->slug}/departments", [
                'name' => 'Engineering',
                'code' => 'ENG',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('departments', [
            'organization_id' => $this->orgA->id,
            'name' => 'Engineering',
            'code' => 'ENG',
        ]);
    }

    public function test_scoped_bindings_returns_404_for_cross_tenant_task(): void
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->orgB->id);
        $userB = User::factory()->create(['active_organization_id' => $this->orgB->id]);
        $userB->organizations()->attach([$this->orgB->id]);
        $userB->assignRole(Role::where('name', 'Owner')->whereNull('team_id')->first());

        $clientB = Client::factory()->create(['organization_id' => $this->orgB->id]);
        $projectB = Project::factory()->create([
            'organization_id' => $this->orgB->id,
            'client_id' => $clientB->id,
        ]);
        $taskB = Task::factory()->create([
            'organization_id' => $this->orgB->id,
            'project_id' => $projectB->id,
        ]);

        $response = $this->actingAs($this->userA)
            ->get("/org/{$this->orgA->slug}/tasks/{$taskB->id}");

        $response->assertStatus(404);
    }

    public function test_scoped_bindings_returns_404_for_cross_tenant_client(): void
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->orgB->id);
        $userB = User::factory()->create(['active_organization_id' => $this->orgB->id]);
        $userB->organizations()->attach([$this->orgB->id]);
        $userB->assignRole(Role::where('name', 'Owner')->whereNull('team_id')->first());

        $clientB = Client::factory()->create(['organization_id' => $this->orgB->id]);

        $response = $this->actingAs($this->userA)
            ->get("/org/{$this->orgA->slug}/clients/{$clientB->id}");

        $response->assertStatus(404);
    }

    public function test_scoped_bindings_returns_404_for_cross_tenant_project(): void
    {
        $clientB = Client::factory()->create(['organization_id' => $this->orgB->id]);
        $projectB = Project::factory()->create([
            'organization_id' => $this->orgB->id,
            'client_id' => $clientB->id,
        ]);

        $response = $this->actingAs($this->userA)
            ->get("/org/{$this->orgA->slug}/projects/{$projectB->id}");

        $response->assertStatus(404);
    }
}
