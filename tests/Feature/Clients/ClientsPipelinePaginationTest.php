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

    private function makeOwner(Organization $org): User
    {
        $user = User::factory()->create([
            'active_organization_id' => $org->id,
            'client_id' => null,
        ]);
        $org->users()->attach($user->id);
        app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);
        $ownerRole = Role::where('name', 'Owner')->whereNull('team_id')->first();
        $user->assignRole($ownerRole);

        return $user;
    }

    public function test_clients_pipeline_is_paginated_with_50_per_page(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        $user = $this->makeOwner($org);

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
        $user = $this->makeOwner($org);

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

    public function test_pipeline_search_filters_by_company_name(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        $user = $this->makeOwner($org);

        Client::factory()->create([
            'organization_id' => $org->id,
            'company_name' => 'Alpha Corp',
            'status' => 'active',
        ]);
        Client::factory()->create([
            'organization_id' => $org->id,
            'company_name' => 'Beta Inc',
            'status' => 'lead',
        ]);
        Client::factory()->create([
            'organization_id' => $org->id,
            'company_name' => 'Gamma LLC',
            'status' => 'paused',
        ]);

        $this->actingAs($user)
            ->get(route('clients.pipeline', [
                'organization' => $org->slug,
                'q' => 'Alpha',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Clients/Pipeline')
                ->where('clients.total', 1)
                ->has('clients.data', 1)
                ->where('clients.data.0.company_name', 'Alpha Corp')
                ->where('filters.q', 'Alpha')
            );
    }

    public function test_pipeline_search_preserves_query_in_pagination_links(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        $user = $this->makeOwner($org);

        Client::factory()->count(55)->create([
            'organization_id' => $org->id,
            'company_name' => 'Needle Corp',
            'status' => 'active',
        ]);
        Client::factory()->create([
            'organization_id' => $org->id,
            'company_name' => 'Other Inc',
            'status' => 'lead',
        ]);

        $this->actingAs($user)
            ->get(route('clients.pipeline', [
                'organization' => $org->slug,
                'q' => 'Needle',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Clients/Pipeline')
                ->where('clients.total', 55)
                ->where('filters.q', 'Needle')
                ->where('clients.links', fn (mixed $links): bool => collect($links)
                    ->pluck('url')
                    ->filter()
                    ->contains(fn (?string $url): bool => is_string($url) && str_contains($url, 'q=Needle')))
            );
    }

    public function test_pipeline_status_update_changes_client_status(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        $user = $this->makeOwner($org);

        $client = Client::factory()->create([
            'organization_id' => $org->id,
            'company_name' => 'Moveable Co',
            'status' => 'lead',
        ]);

        $response = $this->actingAs($user)
            ->post(route('clients.pipeline.update', [
                'organization' => $org->slug,
                'client' => $client->id,
            ]), ['status' => 'active']);

        $response->assertRedirect();
        $client->refresh();
        $this->assertSame('active', $client->status);
    }

    public function test_pipeline_status_update_rejects_invalid_status(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        $user = $this->makeOwner($org);

        $client = Client::factory()->create([
            'organization_id' => $org->id,
            'status' => 'lead',
        ]);

        $response = $this->actingAs($user)
            ->post(route('clients.pipeline.update', [
                'organization' => $org->slug,
                'client' => $client->id,
            ]), ['status' => 'garbage']);

        $response->assertSessionHasErrors('status');
        $client->refresh();
        $this->assertSame('lead', $client->status);
    }

    public function test_pipeline_empty_search_returns_all(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        $user = $this->makeOwner($org);

        Client::factory()->count(3)->create([
            'organization_id' => $org->id,
            'status' => 'active',
        ]);

        $this->actingAs($user)
            ->get(route('clients.pipeline', [
                'organization' => $org->slug,
                'q' => '',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Clients/Pipeline')
                ->where('clients.total', 3)
                ->has('clients.data', 3)
            );
    }

    public function test_pipeline_returns_filters_prop(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        $user = $this->makeOwner($org);

        $this->actingAs($user)
            ->get(route('clients.pipeline', ['organization' => $org->slug]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Clients/Pipeline')
                ->has('filters')
                ->where('filters.q', '')
            );
    }
}
