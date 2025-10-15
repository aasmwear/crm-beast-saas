<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreClientRequest;
use App\Http\Requests\UpdateClientRequest;
use App\Models\Client;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ClientsController extends Controller
{
    public function index(Request $request, Organization $organization): Response
    {
        $this->authorize('viewAny', [Client::class, $organization]);

        // Read filters/search from querystring
        $status = trim((string) $request->query('status', ''));   // 'active' | 'inactive' | ''
        $industry = trim((string) $request->query('industry', '')); // free text
        $q = trim((string) $request->query('q', ''));        // free text

        // Escape % and _ for LIKE/ILIKE patterns
        $escapeLike = static fn (string $s): string => '%'.str_replace(['%', '_'], ['\\%', '\\_'], $s).'%';

        $clients = Client::query()
            ->where('organization_id', $organization->id)
            ->when($status !== '', function ($query) use ($status) {
                $query->where('status', $status);
            })
            ->when($industry !== '', function ($query) use ($industry, $escapeLike) {
                $query->where('industry', 'ILIKE', $escapeLike($industry));
            })
            ->when($q !== '', function ($query) use ($q, $escapeLike) {
                $like = $escapeLike($q);
                $query->where(function ($w) use ($like) {
                    $w->where('company_name', 'ILIKE', $like)
                        ->orWhere('industry', 'ILIKE', $like)
                        ->orWhere('niche', 'ILIKE', $like)
                        ->orWhere('primary_contact_name', 'ILIKE', $like);
                });
            })
            ->orderBy('company_name')
            ->select([
                'id',
                'company_name',
                'industry',
                'niche',
                'status',
            ])
            ->paginate(10)           // 10 per page (acceptance)
            ->withQueryString();     // keep filters/search in links

        return Inertia::render('Clients/Index', [
            'clients' => $clients,
            'filters' => [
                'status' => $status !== '' ? $status : null,
                'industry' => $industry !== '' ? $industry : null,
                'q' => $q !== '' ? $q : null,
            ],
        ]);
    }

    public function create(Organization $organization): Response
    {
        $this->authorize('create', [Client::class, $organization]);

        return Inertia::render('Clients/Create', []);
    }

    public function store(StoreClientRequest $request, Organization $organization): RedirectResponse
    {
        $this->authorize('create', [Client::class, $organization]);

        $data = $request->validated();
        $data['organization_id'] = $organization->id;

        /** @var \App\Models\Client $client */
        $client = Client::query()->create($data);

        return redirect()
            ->route('clients.show', [
                'organization' => $organization->slug,
                'client' => $client->id,
            ])
            ->with('success', 'Client created.');
    }

    public function show(Organization $organization, Client $client): Response
    {
        $this->authorize('view', [$client, $organization]);
        abort_unless($client->organization_id === $organization->id, 404);

        return Inertia::render('Clients/Show', [
            'client' => $client->only([
                'id',
                'company_name',
                'industry',
                'niche',
                'primary_contact_name',
                'primary_contact_email',
                'primary_contact_phone',
                'website',
                'address',
                'tags',
                'fronter',
                'closer',
                'assigned_account_manager_id',
                'google_business_profile_status',
                'google_business_profile_access_status',
                'client_activation_status',
                'status',
                'notes_by_cst',
                'notes_by_sales',
                'notes_by_tech',
                'created_at',
                'updated_at',
            ]),
        ]);
    }

    public function edit(Organization $organization, Client $client): Response
    {
        $this->authorize('update', [$client, $organization]);
        abort_unless($client->organization_id === $organization->id, 404);

        return Inertia::render('Clients/Edit', [
            'client' => $client->only([
                'id',
                'company_name',
                'industry',
                'niche',
                'primary_contact_name',
                'primary_contact_email',
                'primary_contact_phone',
                'website',
                'address',
                'tags',
                'fronter',
                'closer',
                'assigned_account_manager_id',
                'google_business_profile_status',
                'google_business_profile_access_status',
                'client_activation_status',
                'status',
                'notes_by_cst',
                'notes_by_sales',
                'notes_by_tech',
            ]),
        ]);
    }

    public function update(UpdateClientRequest $request, Organization $organization, Client $client): RedirectResponse
    {
        $this->authorize('update', [$client, $organization]);
        abort_unless($client->organization_id === $organization->id, 404);

        $data = $request->validated();
        unset($data['organization_id']); // never allow org reassignment

        $client->update($data);

        return redirect()
            ->route('clients.show', [
                'organization' => $organization->slug,
                'client' => $client->id,
            ])
            ->with('success', 'Client updated.');
    }

    public function destroy(Organization $organization, Client $client): RedirectResponse
    {
        $this->authorize('delete', [$client, $organization]);
        abort_unless($client->organization_id === $organization->id, 404);

        $client->delete();

        return redirect()
            ->route('clients.index', ['organization' => $organization->slug])
            ->with('success', 'Client deleted.');
    }
}
