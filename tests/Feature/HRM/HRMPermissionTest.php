<?php

namespace Tests\Feature\HRM;

use App\Models\Department;
use App\Models\Organization;
use App\Models\User;
use App\Support\PlanCatalog;
use App\Models\OrganizationSubscription;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class HRMPermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function createUserWithRole(Organization $org, string $roleName, array $permissions): User
    {
        $user = User::factory()->create([
            'active_organization_id' => $org->id,
        ]);
        $org->users()->attach($user->id);

        $role = Role::create([
            'name' => $roleName,
            'guard_name' => 'web',
            'team_id' => $org->id,
        ]);
        $role->syncPermissions($permissions);
        app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);
        $user->assignRole($role);

        return $user;
    }

    private function ensureSubscription(Organization $org): void
    {
        OrganizationSubscription::firstOrCreate(
            ['organization_id' => $org->id],
            [
                'plan_key' => PlanCatalog::PLAN_STARTER,
                'status' => 'active',
                'seats_included' => 10,
            ]
        );
    }

    public function test_user_with_hrm_view_can_view_hrm_page(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        $user = $this->createUserWithRole($org, 'hrm-viewer', ['hrm.view']);

        $response = $this->actingAs($user)
            ->get(route('hrm.index', ['organization' => $org->slug]));

        $response->assertOk();
    }

    public function test_user_with_users_view_can_view_hrm_page(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        $user = $this->createUserWithRole($org, 'users-viewer', ['users.view']);

        $response = $this->actingAs($user)
            ->get(route('hrm.index', ['organization' => $org->slug]));

        $response->assertOk();
    }

    public function test_hrm_index_is_paginated_and_preserves_search_filter(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        $user = $this->createUserWithRole($org, 'hrm-viewer-paginated', ['hrm.view']);

        User::factory()->count(30)->create([
            'name' => 'Needle Person',
            'active_organization_id' => $org->id,
        ])->each(function (User $member) use ($org): void {
            $org->users()->attach($member->id);
        });

        User::factory()->count(4)->create([
            'name' => 'Other Person',
            'active_organization_id' => $org->id,
        ])->each(function (User $member) use ($org): void {
            $org->users()->attach($member->id);
        });

        $this->actingAs($user)
            ->get(route('hrm.index', [
                'organization' => $org->slug,
                'q' => 'Needle',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('HRM/Index')
                ->where('filters.q', 'Needle')
                ->where('employees.total', 30)
                ->has('employees.data', 25)
                ->where('employees.next_page_url', fn (?string $url) => is_string($url) && str_contains($url, 'q=Needle'))
                ->has('employees.links')
            );
    }

    public function test_user_without_hrm_or_users_view_gets_403(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        $user = $this->createUserWithRole($org, 'no-hrm', []);

        $response = $this->actingAs($user)
            ->get(route('hrm.index', ['organization' => $org->slug]));

        $response->assertStatus(403);
    }

    public function test_user_with_hrm_create_can_create_employee(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        $this->ensureSubscription($org);
        $user = $this->createUserWithRole($org, 'hrm-creator', ['hrm.view', 'hrm.create']);

        $response = $this->actingAs($user)
            ->post(route('hrm.store', ['organization' => $org->slug]), [
                'name' => 'New Employee',
                'email' => 'newemp@acme.test',
                'role' => 'Employee',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('users', ['email' => 'newemp@acme.test']);
        $this->assertDatabaseHas('organization_user', [
            'organization_id' => $org->id,
            'user_id' => User::where('email', 'newemp@acme.test')->value('id'),
        ]);
    }

    public function test_user_without_hrm_create_cannot_create_employee(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        $this->ensureSubscription($org);
        $user = $this->createUserWithRole($org, 'view-only', ['hrm.view']);

        $response = $this->actingAs($user)
            ->post(route('hrm.store', ['organization' => $org->slug]), [
                'name' => 'New Employee',
                'email' => 'newemp@acme.test',
                'role' => 'Employee',
            ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('users', ['email' => 'newemp@acme.test']);
    }

    public function test_user_without_hrm_edit_cannot_update_employee(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        $viewer = $this->createUserWithRole($org, 'view-only', ['hrm.view']);
        $employee = User::factory()->create(['active_organization_id' => $org->id]);
        $org->users()->attach($employee->id);

        $response = $this->actingAs($viewer)
            ->put(route('hrm.update', [
                'organization' => $org->slug,
                'user' => $employee->id,
            ]), [
                'name' => 'Updated Name',
                'email' => $employee->email,
                'designation' => 'Senior Dev',
                'department_id' => null,
                'role' => 'Employee',
            ]);

        $response->assertStatus(403);
        $employee->refresh();
        $this->assertNotSame('Updated Name', $employee->name);
    }

    public function test_user_with_hrm_edit_can_update_employee(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        $hrUser = $this->createUserWithRole($org, 'hr', ['hrm.view', 'hrm.edit']);
        $employee = User::factory()->create([
            'active_organization_id' => $org->id,
            'name' => 'Original Name',
        ]);
        $org->users()->attach($employee->id);

        $response = $this->actingAs($hrUser)
            ->put(route('hrm.update', [
                'organization' => $org->slug,
                'user' => $employee->id,
            ]), [
                'name' => 'Updated Name',
                'email' => $employee->email,
                'designation' => 'Senior Dev',
                'department_id' => null,
                'role' => 'Employee',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $employee->refresh();
        $this->assertSame('Updated Name', $employee->name);
    }

    public function test_user_without_hrm_delete_cannot_remove_employee(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        $hrEditOnly = $this->createUserWithRole($org, 'hr-edit', ['hrm.view', 'hrm.edit']);
        $employee = User::factory()->create(['active_organization_id' => $org->id]);
        $org->users()->attach($employee->id);

        $response = $this->actingAs($hrEditOnly)
            ->delete(route('hrm.destroy', [
                'organization' => $org->slug,
                'user' => $employee->id,
            ]));

        $response->assertStatus(403);
        $this->assertTrue($org->users()->where('user_id', $employee->id)->exists());
    }

    public function test_user_with_hrm_delete_can_remove_employee(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        $hrUser = $this->createUserWithRole($org, 'hr-full', ['hrm.view', 'hrm.edit', 'hrm.delete']);
        $employee = User::factory()->create(['active_organization_id' => $org->id]);
        $org->users()->attach($employee->id);

        $response = $this->actingAs($hrUser)
            ->delete(route('hrm.destroy', [
                'organization' => $org->slug,
                'user' => $employee->id,
            ]));

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertFalse($org->users()->where('user_id', $employee->id)->exists());
    }

    public function test_user_cannot_remove_themselves(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        $hrUser = $this->createUserWithRole($org, 'hr-full', ['hrm.view', 'hrm.edit', 'hrm.delete']);

        $response = $this->actingAs($hrUser)
            ->delete(route('hrm.destroy', [
                'organization' => $org->slug,
                'user' => $hrUser->id,
            ]));

        $response->assertStatus(403);
        $this->assertTrue($org->users()->where('user_id', $hrUser->id)->exists());
    }

    public function test_cross_tenant_employee_returns_404(): void
    {
        $orgA = Organization::factory()->create(['slug' => 'acme']);
        $orgB = Organization::factory()->create(['slug' => 'beta']);
        $hrUser = $this->createUserWithRole($orgA, 'hr', ['hrm.view', 'hrm.edit']);
        $employeeInOrgB = User::factory()->create(['active_organization_id' => $orgB->id]);
        $orgB->users()->attach($employeeInOrgB->id);

        $response = $this->actingAs($hrUser)
            ->put(route('hrm.update', [
                'organization' => $orgA->slug,
                'user' => $employeeInOrgB->id,
            ]), [
                'name' => 'Hacked',
                'email' => $employeeInOrgB->email,
                'designation' => null,
                'department_id' => null,
                'role' => 'Employee',
            ]);

        $response->assertStatus(404);
    }
}
