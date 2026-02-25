<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Organization;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class ClientsImportController extends Controller
{
    public function import(Request $request, string $organization): RedirectResponse
    {
        abort_unless($request->user()?->can('clients.import'), 403);

        $org = Organization::query()->where('slug', $organization)->firstOrFail();
        $file = $request->file('file');
        if (! $file) {
            return back()->with('error', 'No file');
        }
        $fh = fopen($file->getRealPath(), 'r');
        $header = fgetcsv($fh);
        $count = 0;
        while (($row = fgetcsv($fh)) !== false) {
            $rec = array_combine($header, $row);
            if (! $rec || empty($rec['company_name'])) {
                continue;
            }
            Client::query()->updateOrCreate([
                'organization_id' => $org->id,
                'company_name' => $rec['company_name'],
            ], [
                'industry' => $rec['industry'] ?? null,
                'status' => $rec['status'] ?? 'lead',
                'primary_contact_email' => $rec['primary_contact_email'] ?? null,
            ]);
            $count++;
        }
        fclose($fh);

        if ($count > 0) {
            AuditLogger::log(
                $org,
                $request->user(),
                'imported',
                'client',
                0,
                ['count' => $count],
            );
        }

        return back()->with('success', 'Clients imported');
    }
}
