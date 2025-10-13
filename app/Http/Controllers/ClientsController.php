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

        $q = Client::query()
            ->forOrg($organization)
            ->visibleTo($request->user(), $organization)
            ->search($request->string('q'));

        return Inertia::render('Clients/Index', [
            'tenant'  => $organization->only(['id','slug','name']),
            'items'   => $q->orderBy('company_name')->paginate(15)->withQueryString(),
            'filters' => ['q' => (string) $request->string('q')],
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
            'company_name' => ['required','string','max:255'],
            'email'        => ['nullable','email'],
            'phone_1'      => ['nullable','string','max:50'],
            'status'       => ['nullable','string','max:50'],
            'niche'        => ['nullable','string','max:100'],
        ]);

        $data['organization_id'] = $organization->id;

        Client::create($data);

        return redirect()
            ->route('clients.index', $organization)
            ->with('flash.success', 'Client created.');
    }

    public function edit(Organization $organization, Client $client)
    {
        abort_if($client->organization_id !== $organization->id, 404);
        $this->authorize('update', $client);

        return Inertia::render('Clients/Edit', [
            'tenant' => $organization->only(['id','slug','name']),
            'client' => $client,
        ]);
    }

    public function update(Organization $organization, Client $client, Request $request)
    {
        abort_if($client->organization_id !== $organization->id, 404);
        $this->authorize('update', $client);

        $data = $request->validate([
            'company_name' => ['required','string','max:255'],
            'email'        => ['nullable','email'],
            'phone_1'      => ['nullable','string','max:50'],
            'status'       => ['nullable','string','max:50'],
            'niche'        => ['nullable','string','max:100'],
        ]);

        $client->update($data);

        return redirect()
            ->route('clients.index', $organization)
            ->with('flash.success', 'Client updated.');
    }

    public function show(Organization $organization, Client $client)
    {
        abort_if($client->organization_id !== $organization->id, 404);
        $this->authorize('view', $client);

        return Inertia::render('Clients/Show', [
            'tenant' => $organization->only(['id','slug','name']),
            'client' => $client,
        ]);
    }

    public function destroy(Organization $organization, Client $client)
    {
        abort_if($client->organization_id !== $organization->id, 404);
        $this->authorize('delete', $client);

        $client->delete();

        return redirect()
            ->route('clients.index', $organization)
            ->with('flash.success', 'Client deleted.');
    }

    // Optional: CSV stubs (hook up later)
    public function export(Organization $organization) {}
    public function import(Organization $organization, Request $request) {}
}
