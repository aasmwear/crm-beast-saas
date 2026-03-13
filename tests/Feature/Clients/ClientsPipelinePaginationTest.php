<?php

namespace Tests\Feature\Clients;

use App\Models\Client;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ClientsPipelinePaginationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_clients_pipeline_is_paginated_with_50_per_page(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        $user = User::factory()->create([
            'active_organization_id' => $org->id,
            'client_id' => null,
        ]);
        $org->users()->attach($user->id);

        app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);
        $ownerRole = Role::where('name', 'Owner')->whereNull('team_id')->first();
        $user->assignRole($ownerRole);

        Client::factory()->count(55)->create([
            'organization_id' => $org->id,
            'status' => 'active',
        ]);

        $this->actingAs($user)
            ->get(route('clients.pipeline', ['organization' => $org->slug]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Clients/Pipeline')
                ->where('clients.total', 55)
                ->has('clients.data', 50)
                ->has('clients.links')
            );
    }

    public function test_clients_pipeline_pagination_preserves_query_string(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        $user = User::factory()->create([
            'active_organization_id' => $org->id,
            'client_id' => null,
        ]);
        $org->users()->attach($user->id);

        app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);
        $ownerRole = Role::where('name', 'Owner')->whereNull('team_id')->first();
        $user->assignRole($ownerRole);

        Client::factory()->count(55)->create([
            'organization_id' => $org->id,
            'status' => 'active',
        ]);

        $this->actingAs($user)
            ->get(route('clients.pipeline', [
                'organization' => $org->slug,
                'page' => 2,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Clients/Pipeline')
                ->where('clients.links', fn (mixed $links): bool => collect($links)->pluck('url')->filter()->contains(
                    fn (?string $url): bool => is_string($url) && str_contains($url, 'page=')
                ))
            );
    }

    public function test_clients_pipeline_respects_visibility(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        $user = User::factory()->create([
            'active_organization_id' => $org->id,
            'client_id' => null,
        ]);
        $org->users()->attach($user->id);

        $otherUser = User::factory()->create([
            'active_organization_id' => $org->id,
            'client_id' => null,
        ]);
        $org->users()->attach($otherUser->id);

        $role = Role::create([
            'name' => 'pipeline-visibility-viewer',
            'guard_name' => 'web',
            'team_id' => $org->id,
        ]);
        $role->givePermissionTo('clients.view');
        app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);
        $user->assignRole($role);
        $otherUser->assignRole($role);

        $visibleClient = Client::factory()->create([
            'organization_id' => $org->id,
            'company_name' => 'Visible Co',
            'status' => 'active',
            'assigned_account_manager_id' => $user->id,
        ]);
        Client::factory()->create([
            'organization_id' => $org->id,
            'company_name' => 'Hidden Co',
            'status' => 'active',
            'assigned_account_manager_id' => $otherUser->id,
            'fronter_id' => null,
            'closer_id' => null,
        ]);

        $this->actingAs($user)
            ->get(route('clients.pipeline', ['organization' => $org->slug]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Clients/Pipeline')
                ->where('clients.total', 1)
                ->has('clients.data', 1)
                ->where('clients.data.0.id', $visibleClient->id)
                ->where('clients.data.0.company_name', 'Visible Co')
            );
    }
}
