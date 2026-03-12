<?php

namespace Tests\Feature\Roles;

use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Global roles (team_id = null) cannot have their permissions modified by tenants.
 */
final class GlobalRoleProtectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_tenant_cannot_modify_global_role_permissions_via_save(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        $owner = User::factory()->create([
            'active_organization_id' => $org->id,
            'client_id' => null,
        ]);
        $org->users()->attach($owner->id);

        $ownerRole = Role::where('name', 'Owner')->whereNull('team_id')->first();
        app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);
        $owner->assignRole($ownerRole);

        $clientsView = Permission::where('name', 'clients.view')->first();

        $response = $this->actingAs($owner)
            ->post(route('roles.save', ['organization' => $org->slug]), [
                'role_id' => $ownerRole->id,
                'permission_ids' => [$clientsView->id],
            ]);

        $response->assertStatus(403);
        $response->assertSee('Global roles cannot be modified');
    }

    public function test_tenant_cannot_modify_super_admin_role_permissions(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        $owner = User::factory()->create([
            'active_organization_id' => $org->id,
            'client_id' => null,
        ]);
        $org->users()->attach($owner->id);

        $ownerRole = Role::where('name', 'Owner')->whereNull('team_id')->first();
        app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);
        $owner->assignRole($ownerRole);

        $superAdminRole = Role::where('name', 'Super Admin')->whereNull('team_id')->first();
        $this->assertNotNull($superAdminRole);
        $clientsView = Permission::where('name', 'clients.view')->first();

        $response = $this->actingAs($owner)
            ->post(route('roles.save', ['organization' => $org->slug]), [
                'role_id' => $superAdminRole->id,
                'permission_ids' => [$clientsView->id],
            ]);

        $response->assertStatus(403);
        $response->assertSee('Global roles cannot be modified');
    }

    public function test_tenant_cannot_modify_global_role_via_matrix_update(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        $owner = User::factory()->create([
            'active_organization_id' => $org->id,
            'client_id' => null,
        ]);
        $org->users()->attach($owner->id);

        $ownerRole = Role::where('name', 'Owner')->whereNull('team_id')->first();
        app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);
        $owner->assignRole($ownerRole);

        $employeeRole = Role::where('name', 'Employee')->whereNull('team_id')->first();
        $clientsView = Permission::where('name', 'clients.view')->first();

        $response = $this->actingAs($owner)
            ->put(route('roles.update', ['organization' => $org->slug]), [
                'matrix' => [
                    (string) $employeeRole->id => [$clientsView->id],
                ],
            ]);

        $response->assertStatus(403);
        $response->assertSee('Global roles cannot be modified');
    }

    public function test_tenant_can_modify_tenant_scoped_role_permissions(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        $owner = User::factory()->create([
            'active_organization_id' => $org->id,
            'client_id' => null,
        ]);
        $org->users()->attach($owner->id);

        $ownerRole = Role::where('name', 'Owner')->whereNull('team_id')->first();
        app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);
        $owner->assignRole($ownerRole);

        $customRole = Role::create([
            'name' => 'custom-role',
            'guard_name' => 'web',
            'team_id' => $org->id,
        ]);

        $clientsView = Permission::where('name', 'clients.view')->first();
        $clientsCreate = Permission::where('name', 'clients.create')->first();

        $response = $this->actingAs($owner)
            ->post(route('roles.save', ['organization' => $org->slug]), [
                'role_id' => $customRole->id,
                'permission_ids' => [$clientsView->id, $clientsCreate->id],
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $customRole->refresh();
        $permIds = $customRole->permissions()->pluck('permissions.id')->toArray();
        $this->assertContains($clientsView->id, $permIds);
        $this->assertContains($clientsCreate->id, $permIds);
    }

    public function test_tenant_can_modify_tenant_scoped_role_via_matrix_update(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        $owner = User::factory()->create([
            'active_organization_id' => $org->id,
            'client_id' => null,
        ]);
        $org->users()->attach($owner->id);

        $ownerRole = Role::where('name', 'Owner')->whereNull('team_id')->first();
        app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);
        $owner->assignRole($ownerRole);

        $customRole = Role::create([
            'name' => 'developer',
            'guard_name' => 'web',
            'team_id' => $org->id,
        ]);

        $clientsView = Permission::where('name', 'clients.view')->first();

        $response = $this->actingAs($owner)
            ->put(route('roles.update', ['organization' => $org->slug]), [
                'matrix' => [
                    (string) $customRole->id => [$clientsView->id],
                ],
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $customRole->refresh();
        $permIds = $customRole->permissions()->pluck('permissions.id')->toArray();
        $this->assertContains($clientsView->id, $permIds);
    }

    public function test_roles_index_includes_is_editable_flag(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        $owner = User::factory()->create([
            'active_organization_id' => $org->id,
            'client_id' => null,
        ]);
        $org->users()->attach($owner->id);

        $ownerRole = Role::where('name', 'Owner')->whereNull('team_id')->first();
        app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);
        $owner->assignRole($ownerRole);

        $customRole = Role::create([
            'name' => 'team-role',
            'guard_name' => 'web',
            'team_id' => $org->id,
        ]);

        $response = $this->actingAs($owner)
            ->get(route('roles.index', ['organization' => $org->slug]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Settings/Roles')
            ->has('roles')
            ->where('roles', function ($roles) use ($ownerRole, $customRole) {
                $ownerData = collect($roles)->firstWhere('id', $ownerRole->id);
                $customData = collect($roles)->firstWhere('id', $customRole->id);
                return $ownerData
                    && $ownerData['is_editable'] === false
                    && $customData
                    && $customData['is_editable'] === true;
            })
        );
    }
}
