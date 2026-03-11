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

class RoleManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_tenant_admin_can_create_role(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        $owner = User::factory()->create([
            'active_organization_id' => $org->id,
            'client_id' => null,
        ]);
        $org->users()->attach($owner->id, ['is_owner' => true]);

        $ownerRole = Role::where('name', 'Owner')->whereNull('team_id')->first();
        app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);
        $owner->assignRole($ownerRole);

        $response = $this->actingAs($owner)
            ->post(route('roles.store', ['organization' => $org->slug]), [
                'name' => 'custom-developer',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('roles', [
            'name' => 'custom-developer',
            'team_id' => $org->id,
            'guard_name' => 'web',
        ]);
    }

    public function test_tenant_admin_can_edit_permissions(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        $owner = User::factory()->create([
            'active_organization_id' => $org->id,
            'client_id' => null,
        ]);
        $org->users()->attach($owner->id, ['is_owner' => true]);

        $ownerRole = Role::where('name', 'Owner')->whereNull('team_id')->first();
        app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);
        $owner->assignRole($ownerRole);

        $customRole = Role::create([
            'name' => 'developer',
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

    public function test_tenant_admin_can_edit_role_name(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        $owner = User::factory()->create([
            'active_organization_id' => $org->id,
            'client_id' => null,
        ]);
        $org->users()->attach($owner->id, ['is_owner' => true]);

        $ownerRole = Role::where('name', 'Owner')->whereNull('team_id')->first();
        app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);
        $owner->assignRole($ownerRole);

        $customRole = Role::create([
            'name' => 'dev-role',
            'guard_name' => 'web',
            'team_id' => $org->id,
        ]);

        $response = $this->actingAs($owner)
            ->patch(route('roles.updateRole', [
                'organization' => $org->slug,
                'role' => $customRole->id,
            ]), [
                'name' => 'senior-developer',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('roles', [
            'id' => $customRole->id,
            'name' => 'senior-developer',
            'team_id' => $org->id,
        ]);
    }

    public function test_tenant_admin_can_delete_role_when_not_assigned(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        $owner = User::factory()->create([
            'active_organization_id' => $org->id,
            'client_id' => null,
        ]);
        $org->users()->attach($owner->id, ['is_owner' => true]);

        $ownerRole = Role::where('name', 'Owner')->whereNull('team_id')->first();
        app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);
        $owner->assignRole($ownerRole);

        $customRole = Role::create([
            'name' => 'deletable-role',
            'guard_name' => 'web',
            'team_id' => $org->id,
        ]);

        $response = $this->actingAs($owner)
            ->delete(route('roles.destroy', [
                'organization' => $org->slug,
                'role' => $customRole->id,
            ]));

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('roles', ['id' => $customRole->id]);
    }

    public function test_role_cannot_be_deleted_if_assigned_to_users(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        $owner = User::factory()->create([
            'active_organization_id' => $org->id,
            'client_id' => null,
        ]);
        $org->users()->attach($owner->id, ['is_owner' => true]);

        $ownerRole = Role::where('name', 'Owner')->whereNull('team_id')->first();
        app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);
        $owner->assignRole($ownerRole);

        $customRole = Role::create([
            'name' => 'assigned-role',
            'guard_name' => 'web',
            'team_id' => $org->id,
        ]);

        $employee = User::factory()->create([
            'active_organization_id' => $org->id,
            'client_id' => null,
        ]);
        $org->users()->attach($employee->id);
        app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);
        $employee->assignRole($customRole);

        $response = $this->actingAs($owner)
            ->delete(route('roles.destroy', [
                'organization' => $org->slug,
                'role' => $customRole->id,
            ]));

        $response->assertSessionHasErrors('role');
        $this->assertDatabaseHas('roles', ['id' => $customRole->id]);
    }

    public function test_permissions_scoped_to_org_team(): void
    {
        $orgA = Organization::factory()->create(['slug' => 'org-a']);
        $orgB = Organization::factory()->create(['slug' => 'org-b']);

        $ownerA = User::factory()->create([
            'active_organization_id' => $orgA->id,
            'client_id' => null,
        ]);
        $orgA->users()->attach($ownerA->id, ['is_owner' => true]);

        $ownerRole = Role::where('name', 'Owner')->whereNull('team_id')->first();
        app(PermissionRegistrar::class)->setPermissionsTeamId($orgA->id);
        $ownerA->assignRole($ownerRole);

        $roleInOrgB = Role::create([
            'name' => 'org-b-role',
            'guard_name' => 'web',
            'team_id' => $orgB->id,
        ]);
        $clientsView = Permission::where('name', 'clients.view')->first();

        $response = $this->actingAs($ownerA)
            ->post(route('roles.save', ['organization' => $orgA->slug]), [
                'role_id' => $roleInOrgB->id,
                'permission_ids' => [$clientsView->id],
            ]);

        $response->assertSessionHasErrors('role_id');
    }

    public function test_user_without_roles_manage_gets_403_on_create(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        $employee = User::factory()->create([
            'active_organization_id' => $org->id,
            'client_id' => null,
        ]);
        $org->users()->attach($employee->id);

        $employeeRole = Role::where('name', 'Employee')->whereNull('team_id')->first();
        app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);
        $employee->assignRole($employeeRole);

        $response = $this->actingAs($employee)
            ->post(route('roles.store', ['organization' => $org->slug]), [
                'name' => 'custom-role',
            ]);

        $response->assertStatus(403);
    }
}
