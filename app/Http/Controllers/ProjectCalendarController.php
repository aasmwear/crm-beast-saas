<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\Project;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class ProjectCalendarController extends Controller
{
    /**
     * Display the calendar (timeline) view for projects.
     *
     * Route: GET /org/{organization:slug}/projects/calendar (name: projects.calendar)
     */
    public function index(Request $request, Organization $organization): Response
    {
        $this->authorize('viewAny', Project::class);

        /** @var \App\Models\User $user */
        $user = $request->user();

        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $fromInput = $validated['from'] ?? null;
        $toInput = $validated['to'] ?? null;

        $from = $fromInput ? Carbon::parse($fromInput)->startOfDay() : null;
        $to = $toInput ? Carbon::parse($toInput)->endOfDay() : null;

        // Default window: ±1 month around today if no filters provided
        if (! $from && ! $to) {
            $from = now()->subMonth()->startOfDay();
            $to = now()->addMonth()->endOfDay();
        }

        $query = Project::with(['manager:id,name'])
            ->where('organization_id', $organization->id)
            ->visibleTo($user);

        if ($from && $to) {
            $fromDate = $from->toDateString();
            $toDate = $to->toDateString();

            $query->where(static function ($q) use ($fromDate, $toDate): void {
                $q->whereBetween('start_date', [$fromDate, $toDate])
                    ->orWhereBetween('end_date', [$fromDate, $toDate]);
            });
        } elseif ($from) {
            $fromDate = $from->toDateString();

            $query->where(static function ($q) use ($fromDate): void {
                $q->whereDate('start_date', '>=', $fromDate)
                    ->orWhereDate('end_date', '>=', $fromDate);
            });
        } elseif ($to) {
            $toDate = $to->toDateString();

            $query->where(static function ($q) use ($toDate): void {
                $q->whereDate('start_date', '<=', $toDate)
                    ->orWhereDate('end_date', '<=', $toDate);
            });
        }

        $events = $query
            ->orderBy('start_date')
            ->orderBy('end_date')
            ->paginate(25)
            ->withQueryString()
            ->through(static function (Project $project): array {
                return [
                    'id' => $project->id,
                    'title' => $project->title,
                    'status' => $project->status,
                    'start_date' => optional($project->start_date)->toDateString(),
                    'end_date' => optional($project->end_date)->toDateString(),
                    'project_manager_name' => optional($project->manager)->name,
                ];
            });

        return Inertia::render('Projects/Calendar', [
            'organization' => $organization->only(['id', 'name', 'slug']),
            'filters' => [
                'from' => $from?->toDateString(),
                'to' => $to?->toDateString(),
            ],
            'events' => $events,
        ]);
    }
}
