<?php

namespace Tests\Feature\Roles;

use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

final class RoleCloneTest extends TestCase
{
    use \Illuminate\Foundation\Testing\RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function createOwnerForOrg(Organization $org): User
    {
        $owner = User::factory()->create([
            'active_organization_id' => $org->id,
            'client_id' => null,
        ]);
        $org->users()->attach($owner->id, ['is_owner' => true]);
        $ownerRole = Role::where('name', 'Owner')->whereNull('team_id')->first();
        app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);
        $owner->assignRole($ownerRole);

        return $owner;
    }

    public function test_tenant_admin_can_clone_team_scoped_role(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        $owner = $this->createOwnerForOrg($org);

        $sourceRole = Role::create([
            'name' => 'developer',
            'guard_name' => 'web',
            'team_id' => $org->id,
        ]);
        $perm1 = Permission::where('name', 'clients.view')->first();
        $perm2 = Permission::where('name', 'projects.view')->first();
        $sourceRole->syncPermissions([$perm1, $perm2]);

        $response = $this->actingAs($owner)
            ->post(route('roles.clone', [
                'organization' => $org->slug,
                'role' => $sourceRole->id,
            ]));

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $response->assertSessionHas('created_role_id');

        $this->assertDatabaseHas('roles', [
            'name' => 'developer-copy',
            'team_id' => $org->id,
            'guard_name' => 'web',
        ]);

        $clonedRole = Role::where('name', 'developer-copy')->where('team_id', $org->id)->first();
        $this->assertNotNull($clonedRole);
        $clonedPermIds = $clonedRole->permissions()->pluck('permissions.id')->toArray();
        $this->assertContains($perm1->id, $clonedPermIds);
        $this->assertContains($perm2->id, $clonedPermIds);
    }

    public function test_cloned_role_gets_same_permissions(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        $owner = $this->createOwnerForOrg($org);

        $sourceRole = Role::create([
            'name' => 'custom-role',
            'guard_name' => 'web',
            'team_id' => $org->id,
        ]);
        $perms = Permission::whereIn('name', ['clients.view', 'clients.create', 'projects.view'])->get();
        $sourceRole->syncPermissions($perms);

        $this->actingAs($owner)
            ->post(route('roles.clone', [
                'organization' => $org->slug,
                'role' => $sourceRole->id,
            ]));

        $clonedRole = Role::where('name', 'custom-role-copy')->where('team_id', $org->id)->first();
        $this->assertNotNull($clonedRole);
        $sourcePermIds = $sourceRole->permissions()->pluck('permissions.id')->sort()->values()->toArray();
        $clonedPermIds = $clonedRole->permissions()->pluck('permissions.id')->sort()->values()->toArray();
        $this->assertSame($sourcePermIds, $clonedPermIds);
    }

    public function test_cloned_role_gets_unique_name_when_copy_exists(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        $owner = $this->createOwnerForOrg($org);

        Role::create([
            'name' => 'developer-copy',
            'guard_name' => 'web',
            'team_id' => $org->id,
        ]);

        $sourceRole = Role::create([
            'name' => 'developer',
            'guard_name' => 'web',
            'team_id' => $org->id,
        ]);

        $this->actingAs($owner)
            ->post(route('roles.clone', [
                'organization' => $org->slug,
                'role' => $sourceRole->id,
            ]));

        $this->assertDatabaseHas('roles', [
            'name' => 'developer-copy-2',
            'team_id' => $org->id,
        ]);
    }

    public function test_tenant_cannot_clone_global_role(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        $owner = $this->createOwnerForOrg($org);

        $globalRole = Role::where('name', 'Employee')->whereNull('team_id')->first();
        $this->assertNotNull($globalRole);

        $response = $this->actingAs($owner)
            ->post(route('roles.clone', [
                'organization' => $org->slug,
                'role' => $globalRole->id,
            ]));

        $response->assertSessionHasErrors('role');
        $this->assertDatabaseMissing('roles', [
            'name' => 'employee-copy',
            'team_id' => $org->id,
        ]);
    }

    public function test_cross_tenant_clone_blocked(): void
    {
        $orgA = Organization::factory()->create(['slug' => 'org-a']);
        $orgB = Organization::factory()->create(['slug' => 'org-b']);
        $ownerA = $this->createOwnerForOrg($orgA);

        $roleInOrgB = Role::create([
            'name' => 'org-b-role',
            'guard_name' => 'web',
            'team_id' => $orgB->id,
        ]);

        $response = $this->actingAs($ownerA)
            ->post(route('roles.clone', [
                'organization' => $orgA->slug,
                'role' => $roleInOrgB->id,
            ]));

        $response->assertSessionHasErrors('role');
        $this->assertDatabaseMissing('roles', [
            'name' => 'org-b-role-copy',
            'team_id' => $orgA->id,
        ]);
    }

    public function test_user_without_roles_manage_cannot_clone(): void
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

        $sourceRole = Role::create([
            'name' => 'developer',
            'guard_name' => 'web',
            'team_id' => $org->id,
        ]);

        $response = $this->actingAs($employee)
            ->post(route('roles.clone', [
                'organization' => $org->slug,
                'role' => $sourceRole->id,
            ]));

        $response->assertStatus(403);
        $this->assertDatabaseMissing('roles', [
            'name' => 'developer-copy',
            'team_id' => $org->id,
        ]);
    }
}
