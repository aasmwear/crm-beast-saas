<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ActivityController extends Controller
{
    /**
     * Activity / Audit Log index.
     *
     * Route: GET /org/{organization:slug}/activity
     * Name: activity.index
     *
     * Props:
     * - organization: { id, name, slug }
     * - items: Array<{ id, actor?: string|null, actor_id?: int|null, action: string, entity: string, created_at: string }>
     * - filters: { actor_id?: string|null, entity?: string|null, action?: string|null, date_from?: string|null, date_to?: string|null }
     * - users: Array<{ id: number, name: string }>
     */
    public function index(Request $request, Organization $organization): Response
    {
        abort_unless($request->user()?->can('activity.view'), 403);

        $filters = [
            'actor_id' => $request->query('actor_id'),
            'entity' => $request->query('entity'),
            'action' => $request->query('action'),
            'date_from' => $request->query('date_from'),
            'date_to' => $request->query('date_to'),
        ];

        $query = DB::table('audit_logs')
            ->leftJoin('users', 'audit_logs.actor_id', '=', 'users.id')
            ->where('audit_logs.organization_id', $organization->id);

        if (! empty($filters['actor_id'])) {
            $query->where('audit_logs.actor_id', (int) $filters['actor_id']);
        }

        if (! empty($filters['entity'])) {
            $query->where('audit_logs.entity', $filters['entity']);
        }

        if (! empty($filters['action'])) {
            $query->where('audit_logs.action', $filters['action']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('audit_logs.created_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('audit_logs.created_at', '<=', $filters['date_to']);
        }

        $rows = $query
            ->orderByDesc('audit_logs.id')
            ->limit(200)
            ->get([
                'audit_logs.id as id',
                'audit_logs.action as action',
                'audit_logs.entity as entity',
                'audit_logs.entity_id as entity_id',
                'audit_logs.created_at as created_at',
                'audit_logs.actor_id as actor_id',
                'users.name as actor',
            ]);

        // IMPORTANT: qualify columns because organization_user has its own "id" column in your schema.
        $users = $organization->users()
            ->select([
                'users.id as id',
                'users.name as name',
            ])
            ->orderBy('users.name')
            ->limit(200)
            ->get();

        return Inertia::render('Activity/Index', [
            'organization' => $organization->only(['id', 'name', 'slug']),
            'items' => $rows,
            'filters' => $filters,
            'users' => $users,
        ]);
    }
}
