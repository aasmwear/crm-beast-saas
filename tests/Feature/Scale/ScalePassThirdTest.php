<?php

declare(strict_types=1);

namespace Tests\Feature\Scale;

use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

final class ScalePassThirdTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_project_show_works_with_bounded_eager_loads(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        $user = User::factory()->create(['active_organization_id' => $org->id]);
        $org->users()->attach($user->id);

        $role = Role::create([
            'name' => 'project-viewer-scale',
            'guard_name' => 'web',
            'team_id' => $org->id,
        ]);
        $role->givePermissionTo('projects.view');
        app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);
        $user->assignRole($role);

        $client = Client::factory()->create(['organization_id' => $org->id]);
        $project = Project::factory()->create([
            'organization_id' => $org->id,
            'client_id' => $client->id,
            'title' => 'Scale Test Project',
        ]);

        $response = $this->actingAs($user)
            ->get(route('projects.show', [
                'organization' => $org->slug,
                'project' => $project->id,
            ]));

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Projects/Show')
                ->has('project')
                ->has('project.tasks')
                ->has('files')
                ->has('comments')
                ->has('activities')
                ->has('users')
                ->where('project.title', 'Scale Test Project')
            );
    }

    public function test_clients_show_returns_correct_has_portal_access_for_contacts(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        $user = User::factory()->create(['active_organization_id' => $org->id]);
        $org->users()->attach($user->id);

        $role = Role::create([
            'name' => 'client-viewer-scale',
            'guard_name' => 'web',
            'team_id' => $org->id,
        ]);
        $role->givePermissionTo('clients.view');
        app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);
        $user->assignRole($role);

        $client = Client::factory()->create([
            'organization_id' => $org->id,
            'company_name' => 'Portal Test Client',
        ]);

        ClientContact::create([
            'client_id' => $client->id,
            'name' => 'Contact With Portal',
            'email' => 'portal-user@example.com',
            'is_primary' => true,
        ]);
        ClientContact::create([
            'client_id' => $client->id,
            'name' => 'Contact Without Portal',
            'email' => 'no-portal@example.com',
            'is_primary' => false,
        ]);

        User::factory()->create([
            'email' => 'portal-user@example.com',
            'client_id' => $client->id,
        ]);

        $response = $this->actingAs($user)
            ->get(route('clients.show', [
                'organization' => $org->slug,
                'client' => $client->id,
            ]));

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Clients/Show')
                ->where('client.company_name', 'Portal Test Client')
                ->has('client.contacts', 2)
                ->where('client.contacts.0.email', 'portal-user@example.com')
                ->where('client.contacts.0.has_portal_access', true)
                ->where('client.contacts.1.email', 'no-portal@example.com')
                ->where('client.contacts.1.has_portal_access', false)
            );
    }

    public function test_monthly_revenue_series_returns_correct_format(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        $user = User::factory()->create(['active_organization_id' => $org->id]);
        $org->users()->attach($user->id);

        $role = Role::create([
            'name' => 'owner-scale',
            'guard_name' => 'web',
            'team_id' => $org->id,
        ]);
        $role->givePermissionTo([
            'projects.view', 'clients.view', 'billing.view', 'financials.view',
            'activity.view', 'settings.view',
        ]);
        app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);
        $user->assignRole($role);

        $client = Client::factory()->create(['organization_id' => $org->id]);
        Invoice::create([
            'organization_id' => $org->id,
            'client_id' => $client->id,
            'number' => 'INV-2026-001',
            'issue_date' => now()->subMonth(),
            'due_date' => now()->subMonth()->addDays(14),
            'status' => 'Paid',
            'total_cents' => 50000,
            'currency' => 'USD',
            'paid_at' => now()->subMonth()->addDays(5),
        ]);

        $response = $this->actingAs($user)
            ->get(route('dashboard', ['organization' => $org->slug]));

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard/Index')
                ->has('charts')
                ->has('charts.monthly_revenue')
                ->where('charts.monthly_revenue', fn ($mr) => isset($mr['labels'], $mr['values'])
                    && is_array($mr['labels'])
                    && is_array($mr['values'])
                    && count($mr['labels']) === 6
                    && count($mr['values']) === 6)
            );
    }

    public function test_projects_index_dropdowns_are_bounded(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        $user = User::factory()->create(['active_organization_id' => $org->id]);
        $org->users()->attach($user->id);

        $role = Role::create([
            'name' => 'project-viewer-dropdown',
            'guard_name' => 'web',
            'team_id' => $org->id,
        ]);
        $role->givePermissionTo('projects.view');
        app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);
        $user->assignRole($role);

        $client = Client::factory()->create(['organization_id' => $org->id]);
        Project::factory()->create([
            'organization_id' => $org->id,
            'client_id' => $client->id,
        ]);

        $response = $this->actingAs($user)
            ->get(route('projects.index', ['organization' => $org->slug]));

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Projects/Index')
                ->has('clients')
                ->has('users')
                ->has('projects')
            );
    }
}
