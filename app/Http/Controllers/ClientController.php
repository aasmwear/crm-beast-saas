<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Organization;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ClientController extends Controller
{
    /**
     * Legacy listing (kept) + CSV export for clients.
     * NOTE: Main clients list UI is handled by ClientsInertiaController.
     */
    public function index(Request $request, Organization $organization): HttpResponse|StreamedResponse
    {
        $includeDeleted = $request->boolean('include_deleted');

        // Support both ?export=csv and ?export=1 styles
        $export = (string) $request->query('export', '');
        $wantsCsv = $export === 'csv' || $request->boolean('export');

        $query = Client::query()->where('organization_id', (int) $organization->id);

        if ($includeDeleted) {
            $query->withTrashed();
        }

        if ($wantsCsv) {
            return response()->stream(function () use ($query): void {
                $out = fopen('php://output', 'w');
                if ($out === false) {
                    return;
                }

                fputcsv($out, [
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
                    'notes_by_cst',
                    'notes_by_sales',
                    'notes_by_tech',
                    'status',
                    'created_at',
                    'updated_at',
                    'deleted_at',
                ]);

                foreach ($query->orderBy('id')->cursor() as $client) {
                    fputcsv($out, [
                        $client->id,
                        $client->company_name,
                        $client->industry,
                        $client->niche,
                        $client->primary_contact_name,
                        $client->primary_contact_email,
                        $client->primary_contact_phone,
                        $client->website,
                        $client->address,
                        json_encode($client->tags),
                        json_encode($client->fronter),
                        json_encode($client->closer),
                        $client->assigned_account_manager_id,
                        $client->google_business_profile_status,
                        $client->google_business_profile_access_status,
                        $client->client_activation_status,
                        $client->notes_by_cst,
                        $client->notes_by_sales,
                        $client->notes_by_tech,
                        $client->status,
                        optional($client->created_at)?->toDateTimeString(),
                        optional($client->updated_at)?->toDateTimeString(),
                        optional($client->deleted_at)?->toDateTimeString(),
                    ]);
                }

                fclose($out);
            }, 200, [
                'content-type' => 'text/csv; charset=UTF-8',
                'content-disposition' => 'attachment; filename=clients.csv',
            ]);
        }

        $filters = [
            'status' => $request->query('status'),
            'industry' => $request->query('industry'),
            'q' => $request->query('q'),
        ];

        if (is_string($filters['status']) && $filters['status'] !== '') {
            $query->where('status', $filters['status']);
        }

        if (is_string($filters['industry']) && $filters['industry'] !== '') {
            $query->where('industry', $filters['industry']);
        }

        if (is_string($filters['q']) && $filters['q'] !== '') {
            $q = $filters['q'];
            $query->where('company_name', 'like', "%{$q}%");
        }

        $clients = $query->orderByDesc('id')->paginate(15)->withQueryString();

        return Inertia::render('Clients/Index', [
            'filters' => $filters,
            'clients' => $clients,
        ])->toResponse($request);
    }

    public function store(Request $request): RedirectResponse
    {
        /** @var Organization $org */
        $org = $request->route('organization');

        $this->authorize('create', Client::class);

        $data = $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'industry' => ['nullable', 'string', 'max:255'],
            'niche' => ['nullable', 'string', 'max:255'],
            'primary_contact_name' => ['required', 'string', 'max:255'],
            'primary_contact_email' => ['required', 'email'],
            'primary_contact_phone' => ['nullable', 'string', 'max:50'],
            'website' => ['nullable', 'url'],
            'address' => ['nullable', 'string'],

            'tags' => ['nullable', 'array'],
            'fronter' => ['nullable', 'array'],
            'closer' => ['nullable', 'array'],

            'assigned_account_manager_id' => ['nullable', 'integer', 'exists:users,id'],

            'google_business_profile_status' => ['nullable', 'string', 'max:255'],
            'google_business_profile_access_status' => ['nullable', 'string', 'max:255'],
            'client_activation_status' => ['nullable', 'string', 'max:50'],

            'notes_by_cst' => ['nullable', 'string'],
            'notes_by_sales' => ['nullable', 'string'],
            'notes_by_tech' => ['nullable', 'string'],

            'status' => ['nullable', 'string', 'max:50'],
        ]);

        return DB::transaction(function () use ($data, $org, $request): RedirectResponse {
            $data['organization_id'] = (int) $org->id;

            $client = Client::query()->create($data);

            AuditLogger::log(
                organization: $org,
                actor: $request->user(),
                action: 'created',
                entity: 'client',
                entityId: (int) $client->id,
                changes: [
                    'before' => null,
                    'after' => $client->getAttributes(),
                ],
            );

            return redirect()
                ->route('clients.show', ['organization' => $org->slug, 'client' => $client->id])
                ->with('success', 'Client created');
        });
    }

    public function show(Organization $organization, Client $client): Response
    {
        $this->authorize('view', $client);

        abort_unless((int) $client->organization_id === (int) $organization->id, 404);

        return Inertia::render('Clients/Show', [
            'organizationSlug' => $organization->slug,
            'client' => $client->loadMissing(['projects', 'accountManager']),
        ]);
    }

    public function edit(Organization $organization, Client $client): Response
    {
        $this->authorize('update', $client);

        abort_unless((int) $client->organization_id === (int) $organization->id, 404);

        return Inertia::render('Clients/Edit', [
            'organizationSlug' => $organization->slug,
            'client' => $client,
        ]);
    }

    public function update(Request $request, Organization $organization, Client $client): RedirectResponse
    {
        $this->authorize('update', $client);

        abort_unless((int) $client->organization_id === (int) $organization->id, 404);

        $data = $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'industry' => ['nullable', 'string', 'max:255'],
            'niche' => ['nullable', 'string', 'max:255'],
            'primary_contact_name' => ['required', 'string', 'max:255'],
            'primary_contact_email' => ['required', 'email'],
            'primary_contact_phone' => ['nullable', 'string', 'max:50'],
            'website' => ['nullable', 'url'],
            'address' => ['nullable', 'string'],

            'tags' => ['nullable', 'array'],
            'fronter' => ['nullable', 'array'],
            'closer' => ['nullable', 'array'],

            'assigned_account_manager_id' => ['nullable', 'integer', 'exists:users,id'],

            'google_business_profile_status' => ['nullable', 'string', 'max:255'],
            'google_business_profile_access_status' => ['nullable', 'string', 'max:255'],
            'client_activation_status' => ['nullable', 'string', 'max:50'],

            'notes_by_cst' => ['nullable', 'string'],
            'notes_by_sales' => ['nullable', 'string'],
            'notes_by_tech' => ['nullable', 'string'],

            'status' => ['nullable', 'string', 'max:50'],
        ]);

        return DB::transaction(function () use ($client, $data, $organization, $request): RedirectResponse {
            $before = $client->getOriginal();

            $client->update($data);

            AuditLogger::log(
                organization: $organization,
                actor: $request->user(),
                action: 'updated',
                entity: 'client',
                entityId: (int) $client->id,
                changes: [
                    'before' => $before,
                    'after' => $client->getAttributes(),
                ],
            );

            return back()->with('success', 'Client updated');
        });
    }

    public function destroy(Request $request, Organization $organization, Client $client): RedirectResponse
    {
        $this->authorize('delete', $client);

        abort_unless((int) $client->organization_id === (int) $organization->id, 404);

        return DB::transaction(function () use ($client, $organization, $request): RedirectResponse {
            $clientId = (int) $client->id;
            $snapshot = $client->toArray();

            $client->delete(); // SoftDeletes

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
                ->with('success', 'Client deleted');
        });
    }
}
