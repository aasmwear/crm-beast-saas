<?php

namespace Tests\Feature\Attendance;

use App\Models\Attendance;
use App\Models\Organization;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Attendance data integrity: double clock-in, timezone, approve validation.
 */
final class AttendanceIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function tenantWithManage(Organization $org, User $user): void
    {
        $org->users()->attach($user->id);
        $user->forceFill(['active_organization_id' => $org->id])->save();
        app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);
        $user->assignRole(Role::where('name', 'Owner')->whereNull('team_id')->first());
    }

    public function test_double_clock_in_prevented_by_application(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme', 'timezone' => 'UTC']);
        $user = User::factory()->create();
        $this->tenantWithManage($org, $user);

        $this->actingAs($user)
            ->post(route('attendance.clockIn', ['organization' => $org->slug]))
            ->assertRedirect();

        $this->assertDatabaseCount('attendance', 1);

        $response = $this->actingAs($user)
            ->post(route('attendance.clockIn', ['organization' => $org->slug]));

        $response->assertRedirect();
        $response->assertSessionHas('error', 'You are already clocked in.');
        $this->assertDatabaseCount('attendance', 1);
    }

    public function test_double_clock_in_prevented_by_unique_constraint_when_pgsql(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Partial unique index test requires PostgreSQL.');
        }

        $org = Organization::factory()->create(['slug' => 'acme', 'timezone' => 'UTC']);
        $user = User::factory()->create();
        $this->tenantWithManage($org, $user);

        Attendance::create([
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'clock_in_at' => now(),
            'clock_in_ip' => '127.0.0.1',
            'status' => 'open',
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        Attendance::create([
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'clock_in_at' => now(),
            'clock_in_ip' => '127.0.0.1',
            'status' => 'open',
        ]);
    }

    public function test_timezone_used_for_today_check(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-14 06:00:00', 'UTC'));

        $org = Organization::factory()->create([
            'slug' => 'acme',
            'timezone' => 'America/New_York',
        ]);
        $user = User::factory()->create();
        $this->tenantWithManage($org, $user);

        // In UTC it's 2026-03-14 06:00 (March 14).
        // In America/New_York it's 2026-03-14 01:00 (March 14).
        // Create a record for "yesterday" in NY: 2026-03-13 20:00 NY = 2026-03-14 00:00 UTC.
        $existing = Attendance::create([
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'clock_in_at' => Carbon::parse('2026-03-13 20:00', 'America/New_York'),
            'clock_out_at' => Carbon::parse('2026-03-13 21:00', 'America/New_York'),
            'clock_in_ip' => '127.0.0.1',
            'minutes' => 60,
            'status' => 'closed',
        ]);

        $response = $this->actingAs($user)
            ->post(route('attendance.clockIn', ['organization' => $org->slug]));

        Carbon::setTestNow();

        // In NY, "today" is 2026-03-14. The existing record is 2026-03-13 in NY. So no collision.
        // User should be able to clock in for 2026-03-14.
        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseCount('attendance', 2);
    }

    public function test_open_attendance_cannot_be_approved(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        $user = User::factory()->create();
        $this->tenantWithManage($org, $user);

        $openRecord = Attendance::create([
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'clock_in_at' => now(),
            'clock_in_ip' => '127.0.0.1',
            'status' => 'open',
            'clock_out_at' => null,
        ]);

        $response = $this->actingAs($user)
            ->post(route('attendance.approve', [
                'organization' => $org->slug,
                'attendance' => $openRecord->id,
            ]));

        $response->assertSessionHasErrors('attendance');
        $openRecord->refresh();
        $this->assertSame('open', $openRecord->status);
    }

    public function test_closed_attendance_can_be_approved(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        $user = User::factory()->create();
        $this->tenantWithManage($org, $user);

        $closedRecord = Attendance::create([
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'clock_in_at' => now()->subHours(2),
            'clock_out_at' => now(),
            'clock_in_ip' => '127.0.0.1',
            'minutes' => 120,
            'status' => 'closed',
        ]);

        $response = $this->actingAs($user)
            ->post(route('attendance.approve', [
                'organization' => $org->slug,
                'attendance' => $closedRecord->id,
            ]));

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $closedRecord->refresh();
        $this->assertSame('approved', $closedRecord->status);
        $this->assertNotNull($closedRecord->approved_at);
    }
}
