<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Task;
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
            ->paginate(10)
            ->withQueryString();

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
        ]);
    }

    public function create(Organization $organization): Response
    {
        $this->authorize('create', Client::class);

        return Inertia::render('Clients/Create', [
            'organizationSlug' => $organization->slug,
        ]);
    }

    public function show(Request $request, Organization $organization, Client $client): Response
    {
        $this->authorize('view', $client);

        /** @var User $user */
        $user = $request->user();

        // Eager-load related data for the Show page
        $client->load([
            // Only load projects/tasks the current user can actually see.
            'projects' => static function (Builder $q) use ($organization, $user): void {
                /** @var Builder<Project> $q */
                $q->select(['id', 'organization_id', 'client_id', 'title', 'status'])
                    ->where('organization_id', $organization->id)
                    ->visibleTo($user)
                    ->orderByDesc('id')
                    ->with([
                        'tasks' => static function (Relation $relation) use ($organization, $user): void {
                            /** @var Builder<Task> $qt */
                            $qt = $relation->getQuery();

                            $qt->select(['id', 'project_id', 'title', 'status', 'due_date', 'organization_id'])
                                ->where('organization_id', $organization->id)
                                ->visibleTo($user)
                                ->orderByDesc('id');
                        },
                    ]);
            },
        ]);

        return Inertia::render('Clients/Show', [
            'organizationSlug' => $organization->slug,
            'client' => $client,
        ]);
    }

    public function edit(Organization $organization, Client $client): Response
    {
        $this->authorize('update', $client);

        return Inertia::render('Clients/Edit', [
            'organizationSlug' => $organization->slug,
            'client' => $client,
        ]);
    }

    public function import(Organization $organization): Response
    {
        // Treat import as a "create clients" operation
        $this->authorize('create', Client::class);

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
