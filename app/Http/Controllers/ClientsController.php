<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreClientRequest;
use App\Http\Requests\UpdateClientRequest;
use App\Http\Requests\ImportClientsRequest;
use App\Models\Organization;
use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

        $data = $request->validated();
        $data['organization_id'] = $organization->id;

        $client = Client::create($data);

        return redirect()
            ->route('clients.index', $organization)
            ->with('flash.success', 'Client created.');
    }

    public function show(Organization $organization, Client $client)
{
    $org = $this->org();
    $this->authorize('view', [$client, $org]);

    return Inertia::render('Clients/Show', [
        'tenant' => $org->only(['id','slug','name']),
        'client' => $client,
    ]);
}

    public function edit(Organization $organization, Client $client)
    {
        // ✅ block cross-tenant access
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

        $client->update($request->validated());

        $client->update($data);

        return redirect()
            ->route('clients.index', $organization)
            ->with('flash.success', 'Client updated.');
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

    public function import(ImportClientsRequest $request)
    {
        $org = $this->org();
        $this->authorize('create', [Client::class, $org]);

        $file = $request->file('file')->getRealPath();
        $fh   = fopen($file, 'r');

        $header = fgetcsv($fh) ?: [];
        $map = collect($header)->mapWithKeys(fn($h, $i) => [strtolower(trim($h)) => $i]);

        $required = ['company_name'];
        foreach ($required as $f) if (!isset($map[$f])) {
            return back()->with('flash', ['error' => "Missing column: $f"]);
        }

        $count = 0;
        while (($row = fgetcsv($fh)) !== false) {
            $data = [
                'organization_id' => $org->id,
                'company_name' => $row[$map['company_name']] ?? null,
                'primary_contact_email' => $row[$map['primary_contact_email']] ?? null,
                'primary_contact_name'  => $row[$map['primary_contact_name']] ?? null,
                'primary_contact_phone' => $row[$map['primary_contact_phone']] ?? null,
                'niche'   => $row[$map['niche']] ?? null,
                'industry'=> $row[$map['industry']] ?? null,
                'website' => $row[$map['website']] ?? null,
                'address' => $row[$map['address']] ?? null,
                'status'  => $row[$map['status']] ?? null,
            ];

            if (!$data['company_name']) continue;

            Client::updateOrCreate(
                ['organization_id' => $org->id, 'company_name' => $data['company_name']],
                $data
            );
            $count++;
        }
        fclose($fh);

        return back()->with('flash', ['success' => "Imported {$count} clients."]);
    }

    protected function exportCsv($query): StreamedResponse
    {
        $filename = 'clients_export_'.now()->format('Ymd_His').'.csv';
        $headers  = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];
        $cols = [
            'company_name','industry','niche','primary_contact_name','primary_contact_email',
            'primary_contact_phone','website','address','status'
        ];

        return response()->stream(function () use ($query, $cols) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $cols);
            $query->orderBy('company_name')->chunk(500, function ($chunk) use ($out, $cols) {
                foreach ($chunk as $c) {
                    fputcsv($out, array_map(fn($k)=> data_get($c, $k), $cols));
                }
            });
            fclose($out);
        }, 200, $headers);
    }
}
