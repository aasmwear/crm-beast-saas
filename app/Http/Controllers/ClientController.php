<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Organization;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
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
                    'fronter_id',
                    'closer_id',
                    'assigned_account_manager_id',
                    'gbp_status',
                    'gbp_access',
                    'client_activation_status',
                    'notes_sales',
                    'notes_cst',
                    'notes_tech',
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
                        $client->fronter_id,
                        $client->closer_id,
                        $client->assigned_account_manager_id,
                        $client->gbp_status,
                        $client->gbp_access,
                        $client->client_activation_status,
                        $client->notes_sales,
                        $client->notes_cst,
                        $client->notes_tech,
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

    private const GBP_STATUS_VALUES = ['not_created', 'created', 'pending', 'verified', 'suspended'];

    private const GBP_ACCESS_VALUES = ['no_access', 'access_granted', 'access_pending'];

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
            'tax_id' => ['nullable', 'string', 'max:100'],
            'currency' => ['nullable', 'string', 'size:3'],

            'tags' => ['nullable', 'array'],

            'fronter_id' => [
                'nullable',
                'integer',
                Rule::exists('organization_user', 'user_id')->where('organization_id', (int) $org->id),
            ],
            'closer_id' => [
                'nullable',
                'integer',
                Rule::exists('organization_user', 'user_id')->where('organization_id', (int) $org->id),
            ],
            'assigned_account_manager_id' => [
                'nullable',
                'integer',
                Rule::exists('organization_user', 'user_id')->where('organization_id', (int) $org->id),
            ],

            'gbp_status' => ['nullable', 'string', Rule::in(self::GBP_STATUS_VALUES)],
            'gbp_access' => ['nullable', 'string', Rule::in(self::GBP_ACCESS_VALUES)],
            'google_business_profile_status' => ['nullable', 'string', Rule::in(self::GBP_STATUS_VALUES)],
            'google_business_profile_access_status' => ['nullable', 'string', Rule::in(self::GBP_ACCESS_VALUES)],

            'client_activation_status' => ['nullable', 'string', 'max:50'],

            'notes_sales' => ['nullable', 'string'],
            'notes_cst' => ['nullable', 'string'],
            'notes_tech' => ['nullable', 'string'],
            'notes_by_sales' => ['nullable', 'string'],
            'notes_by_cst' => ['nullable', 'string'],
            'notes_by_tech' => ['nullable', 'string'],

            'status' => ['nullable', 'string', Rule::in([
                'lead', 'active', 'inactive', 'paused', 'churned',
                'Lead', 'Active', 'Inactive', 'Paused', 'Churned',
            ])],
        ]);

        if (isset($data['status']) && is_string($data['status'])) {
            $data['status'] = strtolower($data['status']);
        }

        if (! isset($data['currency']) || $data['currency'] === '') {
            $data['currency'] = 'USD';
        }

        $data['gbp_status'] = $data['gbp_status'] ?? $data['google_business_profile_status'] ?? null;
        $data['gbp_access'] = $data['gbp_access'] ?? $data['google_business_profile_access_status'] ?? null;
        $data['notes_sales'] = $data['notes_sales'] ?? $data['notes_by_sales'] ?? null;
        $data['notes_cst'] = $data['notes_cst'] ?? $data['notes_by_cst'] ?? null;
        $data['notes_tech'] = $data['notes_tech'] ?? $data['notes_by_tech'] ?? null;

        $fillable = array_flip((new Client)->getFillable());
        $data = array_intersect_key($data, $fillable);
        $data['organization_id'] = (int) $org->id;

        return DB::transaction(function () use ($data, $org, $request): RedirectResponse {
            try {
                // Log the data being inserted for debugging
                Log::info('Creating client with data:', [
                    'organization_id' => $data['organization_id'],
                    'company_name' => $data['company_name'],
                    'fronter_id' => $data['fronter_id'] ?? null,
                    'closer_id' => $data['closer_id'] ?? null,
                    'assigned_account_manager_id' => $data['assigned_account_manager_id'] ?? null,
                ]);

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
            } catch (\Exception $e) {
                // Log the specific error for debugging
                Log::error('Client creation failed', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'data' => $data,
                ]);

                // Re-throw to trigger rollback and show error to user
                throw $e;
            }
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
            'tax_id' => ['nullable', 'string', 'max:100'],
            'currency' => ['nullable', 'string', 'size:3'],

            'tags' => ['nullable', 'array'],
            'fronter_id' => [
                'nullable',
                'integer',
                Rule::exists('organization_user', 'user_id')->where('organization_id', (int) $organization->id),
            ],
            'closer_id' => [
                'nullable',
                'integer',
                Rule::exists('organization_user', 'user_id')->where('organization_id', (int) $organization->id),
            ],
            'assigned_account_manager_id' => [
                'nullable',
                'integer',
                Rule::exists('organization_user', 'user_id')->where('organization_id', (int) $organization->id),
            ],

            'gbp_status' => ['nullable', 'string', Rule::in(self::GBP_STATUS_VALUES)],
            'gbp_access' => ['nullable', 'string', Rule::in(self::GBP_ACCESS_VALUES)],
            'google_business_profile_status' => ['nullable', 'string', Rule::in(self::GBP_STATUS_VALUES)],
            'google_business_profile_access_status' => ['nullable', 'string', Rule::in(self::GBP_ACCESS_VALUES)],

            'client_activation_status' => ['nullable', 'string', 'max:50'],

            'notes_sales' => ['nullable', 'string'],
            'notes_cst' => ['nullable', 'string'],
            'notes_tech' => ['nullable', 'string'],
            'notes_by_sales' => ['nullable', 'string'],
            'notes_by_cst' => ['nullable', 'string'],
            'notes_by_tech' => ['nullable', 'string'],

            'new_note_sales' => ['nullable', 'string', 'max:5000'],
            'new_note_cst' => ['nullable', 'string', 'max:5000'],
            'new_note_tech' => ['nullable', 'string', 'max:5000'],

            'status' => ['nullable', 'string', Rule::in([
                'lead', 'active', 'inactive', 'paused', 'churned',
                'Lead', 'Active', 'Inactive', 'Paused', 'Churned',
            ])],
        ]);

        if (isset($data['status']) && is_string($data['status'])) {
            $data['status'] = strtolower($data['status']);
        }

        $data['gbp_status'] = $data['gbp_status'] ?? $data['google_business_profile_status'] ?? null;
        $data['gbp_access'] = $data['gbp_access'] ?? $data['google_business_profile_access_status'] ?? null;
        $data['notes_sales'] = $data['notes_sales'] ?? $data['notes_by_sales'] ?? null;
        $data['notes_cst'] = $data['notes_cst'] ?? $data['notes_by_cst'] ?? null;
        $data['notes_tech'] = $data['notes_tech'] ?? $data['notes_by_tech'] ?? null;

        $user = $request->user();
        $prefix = '[' . now()->format('Y-m-d H:i') . '] ' . $user->name . ' (#'
            . (int) $user->id . '): ';
        foreach (['sales', 'cst', 'tech'] as $dept) {
            $key = "new_note_{$dept}";
            $note = trim((string) ($data[$key] ?? ''));
            if ($note !== '') {
                $notesKey = "notes_{$dept}";
                $existing = (string) ($client->{$notesKey} ?? '');
                $data[$notesKey] = $existing !== ''
                    ? $existing . "\n\n" . $prefix . $note
                    : $prefix . $note;
            }
        }

        $fillable = array_flip((new Client)->getFillable());
        $data = array_intersect_key($data, $fillable);

        return DB::transaction(function () use ($client, $data, $organization, $request): RedirectResponse {
            try {
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
            } catch (\Exception $e) {
                // Log the specific error for debugging
                Log::error('Client update failed', [
                    'client_id' => $client->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'data' => $data,
                ]);

                // Re-throw to trigger rollback and show error to user
                throw $e;
            }
        });
    }

    /**
     * Export clients as CSV. Requires clients.export or clients.manage.
     */
    public function exportCsv(Request $request, Organization $organization): StreamedResponse
    {
        abort_unless(
            $request->user()?->is_super_admin
            || $request->user()?->can('clients.export')
            || $request->user()?->can('clients.manage'),
            403
        );

        $includeDeleted = $request->boolean('include_deleted');
        $query = Client::query()->where('organization_id', (int) $organization->id);
        if ($includeDeleted) {
            $query->withTrashed();
        }

        return response()->stream(function () use ($query): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fputcsv($out, [
                'id', 'company_name', 'industry', 'niche', 'primary_contact_name',
                'primary_contact_email', 'primary_contact_phone', 'website', 'address',
                'tags', 'fronter_id', 'closer_id', 'assigned_account_manager_id',
                'gbp_status', 'gbp_access', 'client_activation_status',
                'notes_sales', 'notes_cst', 'notes_tech', 'status',
                'created_at', 'updated_at', 'deleted_at',
            ]);
            foreach ($query->orderBy('id')->cursor() as $client) {
                fputcsv($out, [
                    $client->id, $client->company_name, $client->industry, $client->niche,
                    $client->primary_contact_name, $client->primary_contact_email,
                    $client->primary_contact_phone, $client->website, $client->address,
                    json_encode($client->tags), $client->fronter_id, $client->closer_id,
                    $client->assigned_account_manager_id, $client->gbp_status, $client->gbp_access,
                    $client->client_activation_status, $client->notes_sales, $client->notes_cst,
                    $client->notes_tech, $client->status,
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
