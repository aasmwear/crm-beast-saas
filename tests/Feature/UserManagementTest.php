<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;
use Inertia\Testing\AssertableInertia as Assert;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected $organizationA;
    protected $organizationB;
    protected $companyAdmin;
    protected $employee;
    protected $targetUser;
    protected $companyRole;
    protected $employeeRole;

    protected function setUp(): void
    {
        parent::setUp();
        
        // 1. Seed Permissions
        $this->seed(RolesAndPermissionsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // 2. Create Organizations
        $this->organizationA = Organization::factory()->create(['name' => 'Org A', 'slug' => 'org-a']);
        $this->organizationB = Organization::factory()->create(['name' => 'Org B', 'slug' => 'org-b']);

        // 3. Create Roles (Team Scoped)
        setPermissionsTeamId($this->organizationA->id);
        
        // Ensure roles exist or create them
        $this->companyRole = Role::firstOrCreate(['name' => 'Company Admin', 'team_id' => $this->organizationA->id]);
        $this->companyRole->givePermissionTo(['users.view', 'users.manage']);
        
        $this->employeeRole = Role::firstOrCreate(['name' => 'Employee', 'team_id' => $this->organizationA->id]);

        // 4. Create Users (FIXED: Removed 'organization_id' column)
        
        // Company Admin
        $this->companyAdmin = User::factory()->create([
            'active_organization_id' => $this->organizationA->id
        ]);
        $this->companyAdmin->organizations()->attach($this->organizationA);
        $this->companyAdmin->assignRole($this->companyRole);

        // Employee
        $this->employee = User::factory()->create([
            'active_organization_id' => $this->organizationA->id
        ]);
        $this->employee->organizations()->attach($this->organizationA);
        $this->employee->assignRole($this->employeeRole);

        // Target User (to be edited)
        $this->targetUser = User::factory()->create([
            'active_organization_id' => $this->organizationA->id
        ]);
        $this->targetUser->organizations()->attach($this->organizationA);
    }

    public function test_admin_can_view_users_list()
    {
        $response = $this->actingAs($this->companyAdmin)
            ->get(route('users.index', ['organization' => $this->organizationA->slug]));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Users/Index')
            ->has('users')
            ->has('availableRoles')
        );
    }

    public function test_admin_can_assign_role()
    {
        setPermissionsTeamId($this->organizationA->id);

        $response = $this->actingAs($this->companyAdmin)
            ->put(route('users.update', [
                'organization' => $this->organizationA->slug,
                'user' => $this->targetUser->id
            ]), [
                'role_ids' => [$this->employeeRole->id]
            ]);

        $response->assertRedirect();
        
        $this->assertDatabaseHas('model_has_roles', [
            'role_id' => $this->employeeRole->id,
            'model_id' => $this->targetUser->id,
            'team_id' => $this->organizationA->id
        ]);
    }
    
    public function test_cross_org_protection()
    {
        // Role from Org B
        $roleOrgB = Role::create(['name' => 'Spy', 'team_id' => $this->organizationB->id]);

        setPermissionsTeamId($this->organizationA->id);
        $response = $this->actingAs($this->companyAdmin)
            ->put(route('users.update', [
                'organization' => $this->organizationA->slug,
                'user' => $this->targetUser->id
            ]), [
                'role_ids' => [$roleOrgB->id]
            ]);

        // Should be forbidden or validation error
        // We accept either 403 (Policy) or 422 (Validation) or 500 (Abort) depending on implementation
        // But specifically we check DB missing
        $this->assertDatabaseMissing('model_has_roles', [
            'role_id' => $roleOrgB->id,
            'model_id' => $this->targetUser->id
        ]);
    }
}