<?php

namespace Tests\Feature\Clients;

use App\Models\Client;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ClientVisibilityRuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_user_sees_client_when_they_are_fronter_or_closer(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->create(['active_organization_id' => $org->id]);
        $user->organizations()->attach($org->id);

        $role = Role::create(['name' => 'viewer', 'guard_name' => 'web', 'team_id' => $org->id]);
        $role->givePermissionTo('clients.view');
        app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);
        $user->assignRole($role);

        $client = Client::factory()->create([
            'organization_id' => $org->id,
            'company_name' => 'Acme Co',
            'fronter_id' => $user->id,
            'tags' => ['lead'],
        ]);

        $this->actingAs($user);
        $res = $this->get("/org/{$org->slug}/clients?q=Acme");
        $res->assertStatus(200);
    }

    public function test_user_sees_client_when_assigned_account_manager(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->create(['active_organization_id' => $org->id]);
        $user->organizations()->attach($org->id);

        $role = Role::create(['name' => 'viewer-am', 'guard_name' => 'web', 'team_id' => $org->id]);
        $role->givePermissionTo('clients.view');
        app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);
        $user->assignRole($role);

        $client = Client::factory()->create([
            'organization_id' => $org->id,
            'company_name' => 'Beta Co',
            'assigned_account_manager_id' => $user->id,
        ]);

        $this->actingAs($user);
        $res = $this->get("/org/{$org->slug}/clients?q=Beta");
        $res->assertStatus(200);
    }

    public function test_user_sees_client_when_pm_or_assignee_on_project_tasks(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->create(['active_organization_id' => $org->id]);
        $user->organizations()->attach($org->id);

        $role = Role::create(['name' => 'viewer-pm', 'guard_name' => 'web', 'team_id' => $org->id]);
        $role->givePermissionTo('clients.view');
        app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);
        $user->assignRole($role);

        $client = Client::factory()->create([
            'organization_id' => $org->id,
            'company_name' => 'Gamma Co',
        ]);

        $project = Project::create([
            'organization_id' => $org->id,
            'client_id' => $client->id,
            'title' => 'Work',
            'project_manager_id' => $user->id,
        ]);

        Task::create([
            'organization_id' => $org->id,
            'project_id' => $project->id,
            'title' => 'Subtask',
            'assignees' => [$user->id],
        ]);

        $this->actingAs($user);
        $res = $this->get("/org/{$org->slug}/clients?q=Gamma");
        $res->assertStatus(200);
    }
}
