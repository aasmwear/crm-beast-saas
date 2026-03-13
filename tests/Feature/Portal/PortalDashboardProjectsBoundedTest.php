<?php

namespace Tests\Feature\Portal;

use App\Models\Client;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PortalDashboardProjectsBoundedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_portal_dashboard_projects_are_bounded_to_50(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        $client = Client::factory()->create([
            'organization_id' => $org->id,
            'company_name' => 'Test Client',
        ]);

        $portalUser = User::factory()->create([
            'active_organization_id' => $org->id,
            'client_id' => $client->id,
            'email' => 'portal@client.test',
        ]);
        $org->users()->attach($portalUser->id);

        $clientRole = Role::firstOrCreate(
            ['name' => 'Client', 'guard_name' => 'web', 'team_id' => $org->id]
        );
        app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);
        $portalUser->assignRole($clientRole);

        Project::factory()->count(60)->create([
            'organization_id' => $org->id,
            'client_id' => $client->id,
        ]);

        $this->actingAs($portalUser)
            ->get(route('portal.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Portal/Dashboard')
                ->has('projects', 50)
                ->where('projects', fn ($projects): bool => count($projects) <= 50)
            );
    }

    public function test_portal_dashboard_shows_projects_for_client_user(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        $client = Client::factory()->create([
            'organization_id' => $org->id,
            'company_name' => 'My Client Co',
        ]);

        $portalUser = User::factory()->create([
            'active_organization_id' => $org->id,
            'client_id' => $client->id,
            'email' => 'contact@myclient.test',
        ]);
        $org->users()->attach($portalUser->id);

        $clientRole = Role::firstOrCreate(
            ['name' => 'Client', 'guard_name' => 'web', 'team_id' => $org->id]
        );
        app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);
        $portalUser->assignRole($clientRole);

        $project = Project::factory()->create([
            'organization_id' => $org->id,
            'client_id' => $client->id,
            'title' => 'My Project',
        ]);

        $this->actingAs($portalUser)
            ->get(route('portal.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Portal/Dashboard')
                ->has('projects', 1)
                ->where('projects.0.title', 'My Project')
            );
    }
}
