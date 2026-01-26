<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Organization;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

final class AttendanceController extends Controller
{
    /**
     * Show attendance for the current organization with basic filters.
     */
    public function index(Request $request): Response
    {
        /** @var Organization $organization */
        $organization = $request->route('organization');
        $user = $request->user();

        $filters = [
            'user_id' => $request->query('user_id'),
            'date_from' => $request->query('date_from'),
            'date_to' => $request->query('date_to'),
            'status' => $request->query('status'),
        ];

        $query = Attendance::query()
            ->where('organization_id', (int) $organization->id);

        // If a specific user is selected (HR/Admin view), filter by that user.
        if (! empty($filters['user_id'])) {
            $query->where('user_id', (int) $filters['user_id']);
        } elseif ($user !== null) {
            // Default to current user when no filter is provided.
            $query->where('user_id', (int) $user->id);
            $filters['user_id'] = (string) $user->id;
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('clock_in_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('clock_in_at', '<=', $filters['date_to']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $attendance = $query
            ->orderByDesc('clock_in_at')
            ->paginate(30)
            ->withQueryString();

        // "Today" / current session always reflects the authenticated user,
        // not the filter, so clock in/out UX is always about "me".
        $current = null;

        if ($user !== null) {
            $current = Attendance::query()
                ->where('organization_id', (int) $organization->id)
                ->where('user_id', (int) $user->id)
                ->whereNull('clock_out_at')
                ->orderByDesc('clock_in_at')
                ->first();
        }

        $users = $organization->users()
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return Inertia::render('Attendance/Index', [
            'attendance' => $attendance,
            'current' => $current,
            'filters' => $filters,
            'users' => $users,
        ]);
    }

    /**
     * Clock the current user in.
     */
    public function clockIn(Request $request): RedirectResponse
    {
        /** @var Organization $organization */
        $organization = $request->route('organization');
        $user = $request->user();

        // Do not allow multiple open sessions.
        $alreadyClockedIn = Attendance::query()
            ->where('organization_id', (int) $organization->id)
            ->where('user_id', (int) $user->id)
            ->whereNull('clock_out_at')
            ->exists();

        if ($alreadyClockedIn) {
            return back()->with('error', 'You are already clocked in.');
        }

        // Enforce at most one record per user per day.
        $today = now()->toDateString();

        $hasTodayRecord = Attendance::query()
            ->where('organization_id', (int) $organization->id)
            ->where('user_id', (int) $user->id)
            ->whereDate('clock_in_at', $today)
            ->exists();

        if ($hasTodayRecord) {
            return back()->with('error', 'You already have an attendance record for today.');
        }

        $attendance = new Attendance;
        $attendance->setAttribute('organization_id', (int) $organization->id);
        $attendance->setAttribute('user_id', (int) $user->id);
        $attendance->setAttribute('clock_in_at', now());
        $attendance->setAttribute('status', 'open');
        $attendance->save();

        return back()->with('success', 'Clocked in');
    }

    /**
     * Clock the current user out.
     */
    public function clockOut(Request $request): RedirectResponse
    {
        /** @var Organization $organization */
        $organization = $request->route('organization');
        $user = $request->user();

        /** @var Attendance|null $attendance */
        $attendance = Attendance::query()
            ->where('organization_id', (int) $organization->id)
            ->where('user_id', (int) $user->id)
            ->whereNull('clock_out_at')
            ->orderByDesc('clock_in_at')
            ->first();

        if (! $attendance) {
            return back()->with('error', 'You are not currently clocked in.');
        }

        $clockOut = now();
        $minutes = null;

        /** @var Carbon|null $clockIn */
        $clockIn = $attendance->getAttribute('clock_in_at');

        if ($clockIn instanceof Carbon) {
            // diffInMinutes returns an int; cast explicitly for clarity.
            $minutes = (int) $clockIn->diffInMinutes($clockOut);
        }

        $attendance->setAttribute('clock_out_at', $clockOut);
        $attendance->setAttribute('minutes', $minutes);
        $attendance->setAttribute('status', 'closed');
        $attendance->save();

        return back()->with('success', 'Clocked out');
    }

    /**
     * Approve an attendance record (HR / Manager).
     */
    public function approve(Request $request, Attendance $attendance): RedirectResponse
    {
        /** @var Organization $organization */
        $organization = $request->route('organization');

        if ((int) $attendance->organization_id !== (int) $organization->id) {
            abort(404);
        }

        $before = $attendance->getAttributes();

        $attendance->setAttribute('approved_by', $request->user()->id);
        $attendance->setAttribute('approved_at', now());
        $attendance->setAttribute('status', 'approved');
        $attendance->save();

        AuditLogger::log(
            $organization,
            $request->user(),
            'approved',
            'attendance',
            (int) $attendance->id,
            [
                'before' => $before,
                'after' => $attendance->getAttributes(),
            ],
        );

        return back()->with('success', 'Approved');
    }

    /**
     * Manual corrections for HR/Managers: update status & notes for a record.
     */
    public function update(Request $request, Organization $organization, Attendance $attendance): RedirectResponse
    {
        if ((int) $attendance->organization_id !== (int) $organization->id) {
            abort(404);
        }

        $validated = $request->validate([
            'status' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
        ]);

        $before = $attendance->getAttributes();

        $attendance->fill($validated);
        $attendance->save();

        AuditLogger::log(
            $organization,
            $request->user(),
            'updated',
            'attendance',
            (int) $attendance->id,
            [
                'before' => $before,
                'after' => $attendance->getAttributes(),
            ],
        );

        return back()->with('success', 'Attendance updated.');
    }
}
