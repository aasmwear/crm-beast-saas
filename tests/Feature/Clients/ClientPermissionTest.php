<?php

namespace Tests\Feature\Clients;

use App\Models\Client;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ClientPermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_user_with_clients_view_can_list_clients(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        $user = User::factory()->create([
            'active_organization_id' => $org->id,
            'client_id' => null,
        ]);
        $org->users()->attach($user->id);

        $role = Role::create([
            'name' => 'viewer',
            'guard_name' => 'web',
            'team_id' => $org->id,
        ]);
        $role->givePermissionTo('clients.view');
        app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);
        $user->assignRole($role);

        $response = $this->actingAs($user)
            ->get(route('clients.index', ['organization' => $org->slug]));

        $response->assertOk();
    }

    public function test_user_without_clients_permission_gets_403_on_list(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        $user = User::factory()->create([
            'active_organization_id' => $org->id,
            'client_id' => null,
        ]);
        $org->users()->attach($user->id);

        $role = Role::create([
            'name' => 'no-clients',
            'guard_name' => 'web',
            'team_id' => $org->id,
        ]);
        $role->syncPermissions([]);
        app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);
        $user->assignRole($role);

        $response = $this->actingAs($user)
            ->get(route('clients.index', ['organization' => $org->slug]));

        $response->assertStatus(403);
    }

    public function test_user_with_clients_create_can_create_client(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        $user = User::factory()->create([
            'active_organization_id' => $org->id,
            'client_id' => null,
        ]);
        $org->users()->attach($user->id);

        $role = Role::create([
            'name' => 'creator',
            'guard_name' => 'web',
            'team_id' => $org->id,
        ]);
        $role->givePermissionTo(['clients.view', 'clients.create']);
        app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);
        $user->assignRole($role);

        $response = $this->actingAs($user)
            ->post(route('clients.store', ['organization' => $org->slug]), [
                'company_name' => 'Test Corp',
                'primary_contact_name' => 'Jane Doe',
                'primary_contact_email' => 'jane@testcorp.com',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('clients', [
            'organization_id' => $org->id,
            'company_name' => 'Test Corp',
        ]);
    }

    public function test_user_without_clients_edit_cannot_update_client(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        $user = User::factory()->create([
            'active_organization_id' => $org->id,
            'client_id' => null,
        ]);
        $org->users()->attach($user->id);

        $role = Role::create([
            'name' => 'view-only',
            'guard_name' => 'web',
            'team_id' => $org->id,
        ]);
        $role->givePermissionTo(['clients.view']);
        app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);
        $user->assignRole($role);

        $client = Client::factory()->create([
            'organization_id' => $org->id,
            'company_name' => 'Existing Client',
        ]);

        $response = $this->actingAs($user)
            ->put(route('clients.update', [
                'organization' => $org->slug,
                'client' => $client->id,
            ]), [
                'company_name' => 'Updated Name',
                'primary_contact_name' => 'John',
                'primary_contact_email' => 'john@test.com',
            ]);

        $response->assertStatus(403);
        $this->assertDatabaseHas('clients', [
            'id' => $client->id,
            'company_name' => 'Existing Client',
        ]);
    }

    public function test_user_without_clients_delete_cannot_delete_client(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        $user = User::factory()->create([
            'active_organization_id' => $org->id,
            'client_id' => null,
        ]);
        $org->users()->attach($user->id);

        $role = Role::create([
            'name' => 'no-delete',
            'guard_name' => 'web',
            'team_id' => $org->id,
        ]);
        $role->givePermissionTo(['clients.view', 'clients.edit']);
        app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);
        $user->assignRole($role);

        $client = Client::factory()->create([
            'organization_id' => $org->id,
        ]);

        $response = $this->actingAs($user)
            ->delete(route('clients.destroy', [
                'organization' => $org->slug,
                'client' => $client->id,
            ]));

        $response->assertStatus(403);
        $this->assertDatabaseHas('clients', ['id' => $client->id]);
    }
}
