<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Organization;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ClientsController extends Controller
{
    public function index(Organization $organization, Request $request)
    {
        $this->authorize('viewAny', [Client::class, $organization]);

        $search = (string) $request->query('search', '');

        $q = Client::query()
            ->where('organization_id', $organization->id);

        if ($search !== '') {
            // PostgreSQL friendly ILIKE if you want; LIKE works too.
            $q->where(function ($w) use ($search) {
                $w->where('company_name', 'ILIKE', "%{$search}%")
                  ->orWhere('niche', 'ILIKE', "%{$search}%")
                  ->orWhere('status', 'ILIKE', "%{$search}%");
            });
        }

        $clients = $q->orderBy('company_name')
            ->paginate(15)
            ->withQueryString()
            ->through(fn ($c) => [
                'id'            => $c->id,
                'company_name'  => $c->company_name,
                'niche'         => $c->niche,
                'status'        => $c->status,
            ]);

        return Inertia::render('Clients/Index', [
            'tenant'  => $organization->only(['id','slug','name']),
            'filters' => ['search' => $search],
            'clients' => $clients,
        ]);
    }

    public function create(Organization $organization)
    {
        $this->authorize('create', [Client::class, $organization]);

        return Inertia::render('Clients/Create', [
            'tenant' => $organization->only(['id','slug','name']),
        ]);
    }

    public function store(Organization $organization, Request $request)
    {
        $this->authorize('create', [Client::class, $organization]);

        $data = $request->validate([
            'company_name' => 'required|string|max:255',
            'niche'        => 'nullable|string|max:255',
            'status'       => 'nullable|string|max:50',
        ]);

        $data['organization_id'] = $organization->id;

        $client = Client::create($data);

        return to_route('clients.index', ['organization' => $organization->slug])
            ->with('success', 'Client created.');
    }

    public function show(Organization $organization, Client $client)
    {
        $this->authorize('view', $client);

        return Inertia::render('Clients/Show', [
            'tenant' => $organization->only(['id','slug','name']),
            'client' => $client,
        ]);
    }

    public function edit(Organization $organization, Client $client)
    {
        $this->authorize('update', $client);

        return Inertia::render('Clients/Edit', [
            'tenant' => $organization->only(['id','slug','name']),
            'client' => $client,
        ]);
    }

    public function update(Organization $organization, Client $client, Request $request)
    {
        $this->authorize('update', $client);

        $data = $request->validate([
            'company_name' => 'required|string|max:255',
            'niche'        => 'nullable|string|max:255',
            'status'       => 'nullable|string|max:50',
        ]);

        $client->update($data);

        return to_route('clients.index', ['organization' => $organization->slug])
            ->with('success', 'Client updated.');
    }

    public function destroy(Organization $organization, Client $client)
    {
        $this->authorize('delete', $client);

        $client->delete();

        return back()->with('success', 'Client deleted.');
    }
}
