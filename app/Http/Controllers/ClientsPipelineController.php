<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Contracts\Database\Query\Builder as BaseBuilder;
use Illuminate\Validation\Rule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class ClientsPipelineController extends Controller
{
    public function index(Request $request, Organization $organization): Response
    {
        $this->authorize('viewAny', Client::class);

        /** @var User $user */
        $user = $request->user();

        /** @var Builder<Client>|BaseBuilder $query */
        $query = Client::query()
            ->forOrg($organization->id)
            ->visibleTo($user)
            ->orderBy('status')
            ->orderByDesc('created_at')
            ->select(['id', 'company_name', 'status']);

        $clients = $query->paginate(50)->withQueryString();

        return Inertia::render('Clients/Pipeline', [
            'organizationSlug' => $organization->slug,
            'clients' => $clients,
        ]);
    }

    public function update(Request $request, Organization $organization, Client $client): RedirectResponse
    {
        abort_unless((int) $client->organization_id === (int) $organization->id, 404);

        $this->authorize('update', $client);

        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(['lead', 'active', 'inactive', 'paused', 'churned'])],
        ]);

        $client->update([
            'status' => $validated['status'],
        ]);

        return redirect()->back();
    }
}
