<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * AttendanceService.
 *
 * Handles clock in/out logic with geo-proofing and midnight split support.
 */
final class AttendanceService
{
    /**
     * Clock in a user.
     *
     * @param  User  $user  The user clocking in
     * @param  array<string, mixed>  $data  Clock in data
     * @return Attendance
     *
     * @throws ValidationException
     */
    public function clockIn(User $user, array $data): Attendance
    {
        // Validate user is not already clocked in
        $existingClockIn = Attendance::where('user_id', $user->id)
            ->where('organization_id', $user->active_organization_id)
            ->whereNotNull('clock_in_at')
            ->whereNull('clock_out_at')
            ->whereNull('deleted_at')
            ->first();

        if ($existingClockIn) {
            throw ValidationException::withMessages([
                'clock_in' => 'You are already clocked in. Please clock out first.',
            ]);
        }

        // Get current timestamp
        $now = Carbon::now();

        // Create attendance record
        return DB::transaction(function () use ($user, $data, $now) {
            return Attendance::create([
                'organization_id' => $user->active_organization_id,
                'user_id' => $user->id,
                'business_date' => $now->toDateString(),
                'clock_in_at' => $now,
                'clock_in_ip' => $data['ip'] ?? request()->ip(),
                'clock_in_lat' => $data['lat'] ?? null,
                'clock_in_lng' => $data['lng'] ?? null,
                'proof_image_path' => $data['proof_image_path'] ?? null,
                'status' => 'clocked_in',
            ]);
        });
    }

    /**
     * Clock out a user.
     *
     * Handles midnight split: If clock_out is on a different date than clock_in,
     * we still store it as one record but calculate total_minutes correctly.
     *
     * @param  User  $user  The user clocking out
     * @param  array<string, mixed>  $data  Clock out data
     * @return Attendance
     *
     * @throws ValidationException
     */
    public function clockOut(User $user, array $data): Attendance
    {
        // Find active clock-in record
        $attendance = Attendance::where('user_id', $user->id)
            ->where('organization_id', $user->active_organization_id)
            ->whereNotNull('clock_in_at')
            ->whereNull('clock_out_at')
            ->whereNull('deleted_at')
            ->first();

        if (! $attendance) {
            throw ValidationException::withMessages([
                'clock_out' => 'No active clock-in found. Please clock in first.',
            ]);
        }

        // Get current timestamp
        $now = Carbon::now();

        // Calculate total minutes
        $clockInTime = Carbon::parse($attendance->clock_in_at);
        $totalMinutes = $clockInTime->diffInMinutes($now);

        // Ghost Protocol: Auto-limit shifts to 16 hours (960 minutes)
        $notes = null;
        if ($totalMinutes > 960) {
            $totalMinutes = 960;
            
            // Add note about capped shift
            $notes = ($attendance->notes ?? '')."\n[SYSTEM] Shift auto-capped at 16 hours.";
        }

        // Determine status
        $status = $this->determineStatus($totalMinutes);

        // Update attendance record
        return DB::transaction(function () use ($attendance, $data, $now, $totalMinutes, $status, $notes) {
            $attendance->update([
                'clock_out_at' => $now,
                'clock_out_ip' => $data['ip'] ?? request()->ip(),
                'clock_out_lat' => $data['lat'] ?? null,
                'clock_out_lng' => $data['lng'] ?? null,
                'minutes' => $totalMinutes,
                'status' => $status,
                'notes' => $notes ?? $attendance->notes,
            ]);

            return $attendance->fresh();
        });
    }

    /**
     * Determine attendance status based on total minutes.
     *
     * @param  int  $totalMinutes  Total minutes worked
     * @return string Status code
     */
    private function determineStatus(int $totalMinutes): string
    {
        // Full day: 8 hours (480 minutes) or more
        if ($totalMinutes >= 480) {
            return 'present';
        }

        // Half day: 4-8 hours (240-479 minutes)
        if ($totalMinutes >= 240) {
            return 'half_day';
        }

        // Less than 4 hours
        return 'partial';
    }

    /**
     * Auto clock-out users who have been clocked in for more than 16 hours (Ghost Protocol).
     *
     * This should be run via a scheduled command (e.g., daily at midnight).
     *
     * @param  int|null  $organizationId  Optional: scope to specific organization
     * @return int Number of users auto-clocked out
     */
    public function autoClockOutStaleShifts(?int $organizationId = null): int
    {
        $query = Attendance::whereNotNull('clock_in_at')
            ->whereNull('clock_out_at')
            ->where('clock_in_at', '<', Carbon::now()->subHours(16));

        if ($organizationId) {
            $query->where('organization_id', $organizationId);
        }

        $staleAttendances = $query->get();

        foreach ($staleAttendances as $attendance) {
            $now = Carbon::now();
            $clockInTime = Carbon::parse($attendance->clock_in_at);
            
            // Cap at 16 hours (960 minutes)
            $totalMinutes = 960;
            
            $attendance->update([
                'clock_out_at' => $clockInTime->copy()->addHours(16),
                'clock_out_ip' => 'SYSTEM_AUTO_CLOCKOUT',
                'minutes' => $totalMinutes,
                'status' => 'present',
                'notes' => ($attendance->notes ?? '')."\n[GHOST PROTOCOL] Auto-clocked out after 16 hours.",
            ]);
        }

        return $staleAttendances->count();
    }

    /**
     * Get attendance records for a user on a specific date.
     *
     * Handles midnight splits by grouping by business_date.
     *
     * @param  User  $user  The user
     * @param  string  $date  Date in Y-m-d format
     * @return \Illuminate\Database\Eloquent\Collection<int, Attendance>
     */
    public function getAttendanceForDate(User $user, string $date)
    {
        return Attendance::where('user_id', $user->id)
            ->where('organization_id', $user->active_organization_id)
            ->where('business_date', $date)
            ->orderBy('clock_in_at')
            ->get();
    }

    /**
     * Calculate total hours worked for a user on a specific date.
     *
     * @param  User  $user  The user
     * @param  string  $date  Date in Y-m-d format
     * @return float Total hours worked
     */
    public function calculateHoursForDate(User $user, string $date): float
    {
        $attendances = $this->getAttendanceForDate($user, $date);

        $totalMinutes = $attendances->sum('minutes');

        return round($totalMinutes / 60, 2);
    }
}
