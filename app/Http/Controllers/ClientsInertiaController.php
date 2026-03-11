<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Organization;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Contracts\Database\Query\Builder as BaseBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ClientsInertiaController extends Controller
{
    /**
     * Clients index (Inertia) with filters + pagination.
     *
     * Route: GET /org/{organization}/clients
     *
     * Props returned:
     * - organizationSlug: string
     * - filters: { status?: string|null, industry?: string|null, search?: string|null, q?: string|null }
     * - clients: LengthAwarePaginator
     */
    public function index(Request $request, Organization $organization): Response
    {
        $this->authorize('viewAny', Client::class);

        // Read filters (keep nulls so Inertia can keep keys stable)
        $status = $request->query('status');
        $industry = $request->query('industry');

        // Canonical name = "search", but support legacy "q" for tests / old links
        $search = $request->query('search');
        if (! is_string($search) || $search === '') {
            $search = $request->query('q');
        }

        /** @var User $user */
        $user = $request->user();

        /** @var Builder<Client>|BaseBuilder $query */
        $query = Client::query()
            ->forOrg($organization->id)
            // IMPORTANT: use the Client visibility scope
            ->visibleTo($user)
            ->when(
                is_string($status) && $status !== '',
                static function (Builder $q) use ($status): void {
                    $q->where('status', $status);
                }
            )
            ->when(
                is_string($industry) && $industry !== '',
                static function (Builder $q) use ($industry): void {
                    $q->where('industry', $industry);
                }
            )
            ->when(
                is_string($search) && $search !== '',
                static function (Builder $q) use ($search): void {
                    $needle = mb_strtolower($search);
                    $like = '%'.$needle.'%';

                    $q->where(static function (Builder $qq) use ($like): void {
                        // Company name is guaranteed
                        $qq->whereRaw('LOWER(company_name) LIKE ?', [$like]);

                        // These are safe "bonus" fields if present in the DB:
                        $qq->orWhereRaw('LOWER(COALESCE(primary_contact_name, \'\')) LIKE ?', [$like])
                            ->orWhereRaw('LOWER(COALESCE(primary_contact_email, \'\')) LIKE ?', [$like])
                            ->orWhereRaw('LOWER(COALESCE(primary_contact_phone, \'\')) LIKE ?', [$like])
                            ->orWhereRaw('LOWER(COALESCE(website, \'\')) LIKE ?', [$like])
                            ->orWhereRaw('LOWER(COALESCE(address, \'\')) LIKE ?', [$like]);
                    });
                }
            )
            ->orderByDesc('id');

        $clients = $query
            ->withCount('projects')
            ->paginate(10)
            ->withQueryString();

        /** @var User $user */
        $user = $request->user();

        return Inertia::render('Clients/Index', [
            'organizationSlug' => $organization->slug,
            'filters' => [
                // Keep keys stable for Vue; normalize to string|null
                'status' => is_string($status) ? $status : null,
                'industry' => is_string($industry) ? $industry : null,
                'search' => is_string($search) ? $search : null, // canonical
                'q' => is_string($search) ? $search : null,      // alias for tests / old links
            ],
            'clients' => $clients,
            'canCreate' => $user->can('create', Client::class),
            'canImport' => $user->can('clients.import'),
            'canExport' => $user->can('clients.export') || $user->can('clients.manage'),
        ]);
    }

    public function create(Organization $organization): Response
    {
        $this->authorize('create', Client::class);

        // Get all users in this organization for assignment dropdowns
        $users = DB::table('users')
            ->where('active_organization_id', $organization->id)
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return Inertia::render('Clients/Create', [
            'organizationSlug' => $organization->slug,
            'users' => $users,
        ]);
    }

    public function show(Request $request, Organization $organization, Client $client): Response
    {
        $this->authorize('view', $client);

        /** @var User $user */
        $user = $request->user();

        // Eager-load related data for the Show page
        // Note: load() closures receive Relation, not Builder.
        $client->load([
            'contacts',
            // Only load projects (active = not Completed/Cancelled) the current user can see.
            'projects' => static function (Relation $relation) use ($organization, $user): void {
                $relation->select(['id', 'organization_id', 'client_id', 'title', 'status'])
                    ->where('organization_id', $organization->id)
                    ->visibleTo($user)
                    ->orderByDesc('id')
                    ->with([
                        'tasks' => static function (Relation $tasksRelation) use ($organization, $user): void {
                            $tasksRelation->select(['id', 'project_id', 'title', 'status', 'due_date', 'organization_id'])
                                ->where('organization_id', $organization->id)
                                ->visibleTo($user)
                                ->orderByDesc('id');
                        },
                    ]);
            },
        ]);

        $clientData = $client->toArray();
        $clientData['contacts'] = collect($clientData['contacts'] ?? [])->map(function (array $c) use ($client): array {
            $email = $c['email'] ?? null;
            $hasPortalAccess = $email
                ? User::where('email', $email)->where('client_id', $client->id)->exists()
                : false;

            return array_merge($c, ['has_portal_access' => $hasPortalAccess]);
        })->values()->all();

        $payload = [
            'organizationSlug' => $organization->slug,
            'client' => $clientData,
            'canEdit' => $user->can('update', $client),
            'canDelete' => $user->can('delete', $client),
        ];

        if ($user->can('financials.view')) {
            $payload['financial_summary'] = $this->financialSummaryForClient($client);
        } else {
            $payload['financial_summary'] = null;
        }

        return Inertia::render('Clients/Show', $payload);
    }

    /**
     * Total budget and total invoiced (price) for all projects of this client.
     *
     * @return array{total_budget_cents: int, total_invoiced_cents: int, currency: string}
     */
    private function financialSummaryForClient(Client $client): array
    {
        $sums = $client->projects()
            ->selectRaw('COALESCE(SUM(budget_cents), 0) as total_budget_cents, COALESCE(SUM(price_cents), 0) as total_invoiced_cents')
            ->first();

        return [
            'total_budget_cents' => (int) ($sums->total_budget_cents ?? 0),
            'total_invoiced_cents' => (int) ($sums->total_invoiced_cents ?? 0),
            'currency' => $client->currency ?? 'USD',
        ];
    }

    public function edit(Organization $organization, Client $client): Response
    {
        $this->authorize('update', $client);

        // Get all users in this organization for assignment dropdowns
        $users = DB::table('users')
            ->where('active_organization_id', $organization->id)
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return Inertia::render('Clients/Edit', [
            'organizationSlug' => $organization->slug,
            'client' => $client,
            'users' => $users,
            'canEdit' => request()->user()?->can('update', $client) ?? false,
            'canDelete' => request()->user()?->can('delete', $client) ?? false,
        ]);
    }

    public function import(Organization $organization): Response
    {
        abort_unless(request()->user()?->can('clients.import'), 403);

        return Inertia::render('Clients/Import', [
            'organizationSlug' => $organization->slug,
        ]);
    }

    /**
     * DELETE /org/{organization:slug}/clients/{client}
     */
    public function destroy(Request $request, Organization $organization, Client $client): RedirectResponse
    {
        $this->authorize('delete', $client);

        if ((int) $client->organization_id !== (int) $organization->id) {
            abort(404);
        }

        return DB::transaction(function () use ($organization, $request, $client): RedirectResponse {
            $clientId = (int) $client->id;
            $snapshot = $client->toArray();

            $client->delete(); // SoftDeletes enabled on Client

            AuditLogger::log(
                organization: $organization,
                actor: $request->user(),
                action: 'deleted',
                entity: 'client',
                entityId: $clientId,
                changes: [
                    'before' => $snapshot,
                    'after' => null,
                ],
            );

            return redirect()
                ->route('clients.index', ['organization' => $organization->slug])
                ->with('success', 'Client deleted.');
        });
    }
}
