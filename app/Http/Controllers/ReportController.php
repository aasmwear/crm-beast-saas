<?php

namespace App\Http\Controllers;

use App\Models\Client;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    /**
     * Reports landing page.
     */
    public function index(): Response
    {
        return Inertia::render('Reports/Index');
    }

    /**
     * Stream CSV export (clients), supports include_deleted=1.
     */
    public function exportCsv(
        \Illuminate\Http\Request $request,
        \App\Models\Organization $organization,
        string $entity
    ): \Symfony\Component\HttpFoundation\StreamedResponse {
        // Normalize entity and accept both "client" and "clients"
        $normalized = \Illuminate\Support\Str::of($entity)->lower()->trim()->rtrim('s')->value();

        if ($normalized !== 'client') {
            abort(404, 'Unsupported export entity.');
        }

        // Build query
        $query = \App\Models\Client::query()
            ->where('organization_id', $organization->id);

        if ($request->boolean('include_deleted')) {
            $query = $query->withTrashed();
        }

        $filename = 'clients.csv';
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
            'Pragma' => 'no-cache',
        ];

        // ✅ Correct order: use (...) THEN : void
        $callback = static function () use ($query): void {
            $out = fopen('php://output', 'w');

            // Header row
            fputcsv($out, ['Company', 'Email', 'Phone']);

            // Stream rows
            foreach ($query->cursor() as $client) {
                /** @var \App\Models\Client $client */
                fputcsv($out, [
                    (string) $client->getAttribute('company_name'),
                    (string) $client->getAttribute('email'),
                    (string) $client->getAttribute('phone'),
                ]);
            }

            fclose($out);
        };

        return response()->streamDownload($callback, $filename, $headers);
    }
}
