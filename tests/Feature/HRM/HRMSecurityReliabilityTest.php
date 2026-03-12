<?php

namespace Tests\Feature\HRM;

use App\Models\Organization;
use App\Models\OrganizationSubscription;
use App\Models\User;
use App\Support\PlanCatalog;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

final class HRMSecurityReliabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
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

    private function hrmCreator(Organization $org): User
    {
        $user = User::factory()->create(['active_organization_id' => $org->id]);
        $org->users()->attach($user->id);

        $role = Role::create([
            'name' => 'hrm-creator',
            'guard_name' => 'web',
            'team_id' => $org->id,
        ]);
        $role->syncPermissions(['hrm.view', 'hrm.create', 'hrm.edit']);

        app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);
        $user->assignRole($role);

        return $user;
    }

    public function test_employee_creation_no_longer_uses_literal_password(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        $this->ensureSubscription($org);
        $actor = $this->hrmCreator($org);

        $response = $this->actingAs($actor)
            ->post(route('hrm.store', ['organization' => $org->slug]), [
                'name' => 'Secure Employee',
                'email' => 'secure-employee@acme.test',
                'role' => 'Employee',
            ]);

        $response->assertRedirect();

        $created = User::query()->where('email', 'secure-employee@acme.test')->first();
        $this->assertNotNull($created);
        $this->assertFalse(Hash::check('password', (string) $created->password));
    }

    public function test_role_assignment_failure_does_not_silently_pass_and_user_is_rolled_back(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        $this->ensureSubscription($org);
        $actor = $this->hrmCreator($org);

        $response = $this->actingAs($actor)
            ->post(route('hrm.store', ['organization' => $org->slug]), [
                'name' => 'Should Rollback',
                'email' => 'rollback@acme.test',
                'role' => 'NotAssignableRole',
            ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('role');
        $this->assertDatabaseMissing('users', ['email' => 'rollback@acme.test']);
    }

    public function test_update_role_assignment_failure_rolls_back_profile_changes(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        $actor = $this->hrmCreator($org);

        $employee = User::factory()->create([
            'active_organization_id' => $org->id,
            'name' => 'Before Name',
            'designation' => 'Before Title',
        ]);
        $org->users()->attach($employee->id);

        $response = $this->actingAs($actor)
            ->put(route('hrm.update', ['organization' => $org->slug, 'user' => $employee->id]), [
                'name' => 'After Name',
                'email' => $employee->email,
                'designation' => 'After Title',
                'department_id' => null,
                'role' => 'NotAssignableRole',
            ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('role');

        $employee->refresh();
        $this->assertSame('Before Name', $employee->name);
        $this->assertSame('Before Title', $employee->designation);
    }

    public function test_store_success_flash_does_not_expose_raw_password(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        $this->ensureSubscription($org);
        $actor = $this->hrmCreator($org);

        $response = $this->actingAs($actor)
            ->post(route('hrm.store', ['organization' => $org->slug]), [
                'name' => 'No Leak Employee',
                'email' => 'no-leak@acme.test',
                'role' => 'Employee',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', function (string $message): bool {
            $value = strtolower($message);

            return ! str_contains($value, 'default password')
                && ! str_contains($value, 'password is "password"')
                && ! str_contains($value, "password is 'password'");
        });
    }
}

