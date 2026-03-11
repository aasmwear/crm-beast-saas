<?php

namespace Tests\Feature\Attendance;

use App\Models\Attendance;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AttendancePermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        config()->set('features.attendance', true);
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

    public function test_user_with_attendance_view_can_view_index(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        $user = $this->createUserWithRole($org, 'attendance-viewer', ['attendance.view']);

        $response = $this->actingAs($user)
            ->get(route('attendance.index', ['organization' => $org->slug]));

        $response->assertOk();
    }

    public function test_user_with_attendance_view_own_can_view_index(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        $user = $this->createUserWithRole($org, 'attendance-view-own', ['attendance.view-own']);

        $response = $this->actingAs($user)
            ->get(route('attendance.index', ['organization' => $org->slug]));

        $response->assertOk();
    }

    public function test_user_without_attendance_view_gets_403(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        $user = $this->createUserWithRole($org, 'no-attendance', []);

        $response = $this->actingAs($user)
            ->get(route('attendance.index', ['organization' => $org->slug]));

        $response->assertStatus(403);
    }

    public function test_user_with_attendance_create_can_clock_in(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        $user = $this->createUserWithRole($org, 'attendance-creator', ['attendance.view', 'attendance.create']);

        $response = $this->actingAs($user)
            ->post(route('attendance.clockIn', ['organization' => $org->slug]));

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('attendance', [
            'organization_id' => $org->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_user_with_attendance_clock_in_can_clock_in(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        $user = $this->createUserWithRole($org, 'clock-in-only', ['attendance.view-own', 'attendance.clock-in', 'attendance.clock-out']);

        $response = $this->actingAs($user)
            ->post(route('attendance.clockIn', ['organization' => $org->slug]));

        $response->assertRedirect();
        $this->assertDatabaseHas('attendance', [
            'organization_id' => $org->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_user_without_attendance_create_cannot_clock_in(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        $user = $this->createUserWithRole($org, 'view-only', ['attendance.view']);

        $response = $this->actingAs($user)
            ->post(route('attendance.clockIn', ['organization' => $org->slug]));

        $response->assertStatus(403);
        $this->assertDatabaseMissing('attendance', [
            'organization_id' => $org->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_user_without_attendance_create_cannot_clock_out(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        $user = $this->createUserWithRole($org, 'view-only', ['attendance.view']);

        $otherUser = User::factory()->create(['active_organization_id' => $org->id]);
        $org->users()->attach($otherUser->id);

        $attendance = Attendance::query()->create([
            'organization_id' => $org->id,
            'user_id' => $otherUser->id,
            'clock_in_at' => now()->subHour(),
            'clock_out_at' => null,
            'status' => 'open',
        ]);

        $response = $this->actingAs($user)
            ->post(route('attendance.clockOut', ['organization' => $org->slug]));

        $response->assertStatus(403);
        $attendance->refresh();
        $this->assertNull($attendance->clock_out_at);
    }

    public function test_normal_user_cannot_edit_another_users_attendance(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        $user = $this->createUserWithRole($org, 'employee', [
            'attendance.view-own',
            'attendance.create',
            'attendance.clock-in',
            'attendance.clock-out',
        ]);

        $otherUser = User::factory()->create(['active_organization_id' => $org->id]);
        $org->users()->attach($otherUser->id);

        $attendance = Attendance::query()->create([
            'organization_id' => $org->id,
            'user_id' => $otherUser->id,
            'clock_in_at' => now()->subHours(2),
            'clock_out_at' => now()->subHour(),
            'minutes' => 60,
            'status' => 'closed',
        ]);

        $response = $this->actingAs($user)
            ->patch(route('attendance.update', [
                'organization' => $org->slug,
                'attendance' => $attendance->id,
            ]), [
                'status' => 'approved',
                'notes' => 'Illegal edit',
            ]);

        $response->assertStatus(403);
        $attendance->refresh();
        $this->assertSame('closed', $attendance->status);
    }

    public function test_hr_with_attendance_edit_can_edit_others(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        $hrUser = $this->createUserWithRole($org, 'hr', [
            'attendance.view',
            'attendance.edit',
        ]);

        $employee = User::factory()->create(['active_organization_id' => $org->id]);
        $org->users()->attach($employee->id);

        $attendance = Attendance::query()->create([
            'organization_id' => $org->id,
            'user_id' => $employee->id,
            'clock_in_at' => now()->subHours(2),
            'clock_out_at' => now()->subHour(),
            'minutes' => 60,
            'status' => 'closed',
        ]);

        $response = $this->actingAs($hrUser)
            ->patch(route('attendance.update', [
                'organization' => $org->slug,
                'attendance' => $attendance->id,
            ]), [
                'status' => 'approved',
                'notes' => 'HR correction',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $attendance->refresh();
        $this->assertSame('approved', $attendance->status);
        $this->assertSame('HR correction', $attendance->notes);
    }

    public function test_user_without_attendance_delete_cannot_delete(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        $user = $this->createUserWithRole($org, 'view-edit-only', [
            'attendance.view',
            'attendance.edit',
        ]);

        $attendance = Attendance::query()->create([
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'clock_in_at' => now()->subHour(),
            'clock_out_at' => now(),
            'minutes' => 60,
            'status' => 'closed',
        ]);

        $response = $this->actingAs($user)
            ->delete(route('attendance.destroy', [
                'organization' => $org->slug,
                'attendance' => $attendance->id,
            ]));

        $response->assertStatus(403);
        $this->assertDatabaseHas('attendance', ['id' => $attendance->id]);
    }

    public function test_hr_with_attendance_manage_can_delete(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        $hrUser = $this->createUserWithRole($org, 'hr-manager', [
            'attendance.view',
            'attendance.manage',
        ]);

        $employee = User::factory()->create(['active_organization_id' => $org->id]);
        $org->users()->attach($employee->id);

        $attendance = Attendance::query()->create([
            'organization_id' => $org->id,
            'user_id' => $employee->id,
            'clock_in_at' => now()->subHour(),
            'clock_out_at' => now(),
            'minutes' => 60,
            'status' => 'closed',
        ]);

        $response = $this->actingAs($hrUser)
            ->delete(route('attendance.destroy', [
                'organization' => $org->slug,
                'attendance' => $attendance->id,
            ]));

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertSoftDeleted('attendance', ['id' => $attendance->id]);
    }

    public function test_cross_tenant_attendance_returns_404(): void
    {
        $orgA = Organization::factory()->create(['slug' => 'acme']);
        $orgB = Organization::factory()->create(['slug' => 'beta']);
        $user = $this->createUserWithRole($orgA, 'hr', [
            'attendance.view',
            'attendance.edit',
            'attendance.delete',
        ]);

        $attendanceInOrgB = Attendance::query()->create([
            'organization_id' => $orgB->id,
            'user_id' => $user->id,
            'clock_in_at' => now()->subHour(),
            'clock_out_at' => now(),
            'minutes' => 60,
            'status' => 'closed',
        ]);

        $response = $this->actingAs($user)
            ->patch(route('attendance.update', [
                'organization' => $orgA->slug,
                'attendance' => $attendanceInOrgB->id,
            ]), ['status' => 'approved']);

        $response->assertStatus(404);
    }
}
