<?php

namespace Tests\Feature\Attendance;

use App\Models\Attendance;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AttendanceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Create a tenant organization and attach an owner-level user.
     *
     * @return array{0: Organization, 1: User}
     */
    protected function makeTenant(): array
    {
        /** @var Organization $org */
        $org = Organization::factory()->create([
            'slug' => 'acme',
            'settings' => [],
        ]);

        /** @var User $user */
        $user = User::factory()->create();
        $user->organizations()->attach($org->id);

        if (method_exists($user, 'assignRole')) {
            app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);
            Role::findOrCreate('Owner', config('auth.defaults.guard', 'web'));
            $user->assignRole('Owner');
        }

        return [$org, $user];
    }

    public function test_single_record_per_user_per_day_is_enforced(): void
    {
        [$org, $user] = $this->makeTenant();
        config()->set('features.attendance', true);

        $this->actingAs($user);

        // First clock-in should create today's record.
        $this->post(route('attendance.clockIn', [
            'organization' => $org->slug,
        ]))->assertRedirect();

        $this->assertDatabaseCount('attendance', 1);

        /** @var Attendance $attendance */
        $attendance = Attendance::query()
            ->where('organization_id', $org->id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $this->assertNull($attendance->clock_out_at);

        // Clock out on the same day.
        $this->post(route('attendance.clockOut', [
            'organization' => $org->slug,
        ]))->assertRedirect();

        $attendance->refresh();
        $this->assertNotNull($attendance->clock_out_at);

        // Second clock-in on the same day should not create a new record
        // and should surface the "per-day" error message.
        $response = $this->post(route('attendance.clockIn', [
            'organization' => $org->slug,
        ]));

        $response->assertRedirect();
        $response->assertSessionHas('error', 'You already have an attendance record for today.');

        $this->assertEquals(
            1,
            Attendance::query()
                ->where('organization_id', $org->id)
                ->where('user_id', $user->id)
                ->count()
        );
    }

    public function test_minutes_are_calculated_on_clock_out(): void
    {
        [$org, $user] = $this->makeTenant();
        config()->set('features.attendance', true);

        $this->actingAs($user);

        // Clock in to create a record.
        $this->post(route('attendance.clockIn', [
            'organization' => $org->slug,
        ]))->assertRedirect();

        /** @var Attendance $attendance */
        $attendance = Attendance::query()
            ->where('organization_id', $org->id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        // Pretend the user clocked in 135 minutes ago.
        $attendance->clock_in_at = now()->subMinutes(135);
        $attendance->save();

        // When clocking out, controller should calculate minutes based on diff.
        $this->post(route('attendance.clockOut', [
            'organization' => $org->slug,
        ]))->assertRedirect();

        $attendance->refresh();

        $this->assertNotNull($attendance->clock_out_at);
        $this->assertSame(135, $attendance->minutes);
        $this->assertSame('closed', $attendance->status);
    }

    public function test_hr_can_update_status_and_notes(): void
    {
        [$org, $user] = $this->makeTenant();
        config()->set('features.attendance', true);

        $this->actingAs($user);

        // Seed an existing attendance record.
        /** @var Attendance $attendance */
        $attendance = Attendance::query()->create([
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'clock_in_at' => now()->subHours(2),
            'clock_out_at' => now()->subHour(),
            'minutes' => 60,
            'status' => 'closed',
            'notes' => null,
        ]);

        $payload = [
            'status' => 'approved',
            'notes' => 'Manual correction after system issue.',
        ];

        $this->patch(route('attendance.update', [
            'organization' => $org->slug,
            'attendance' => $attendance->id,
        ]), $payload)->assertRedirect();

        $this->assertDatabaseHas('attendance', [
            'id' => $attendance->id,
            'organization_id' => $org->id,
            'status' => 'approved',
            'notes' => 'Manual correction after system issue.',
        ]);
    }
}
