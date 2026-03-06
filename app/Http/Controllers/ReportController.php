<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Organization;
use App\Services\Billing\DailyExportLimitService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as BaseResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    /**
     * Reports landing page.
     */
    public function index(Request $request, Organization $organization): Response
    {
        abort_unless($request->user()?->can('reports.view'), 403);

        $orgId = (int) $organization->id;

        $stats = [
            'clients' => Client::query()->where('organization_id', $orgId)->count(),
            'projects' => DB::table('projects')->where('organization_id', $orgId)->count(),
            'tasks' => DB::table('tasks')->where('organization_id', $orgId)->count(),
            'attendance' => DB::table('attendance')->where('organization_id', $orgId)->count(),
            'invoices' => DB::table('invoices')->where('organization_id', $orgId)->count(),
        ];

        return Inertia::render('Reports/Index', [
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
                'slug' => $organization->slug,
            ],
            'stats' => $stats,
            'canExport' => $request->user()?->can('reports.export'),
        ]);
    }

    /**
     * Stream CSV export (clients), supports include_deleted=1.
     * Tenant-scoped by organization. Uses primary_contact_email, primary_contact_phone.
     */
    public function exportCsv(
        Request $request,
        Organization $organization,
        string $entity
    ): StreamedResponse|BaseResponse {
        abort_unless($request->user()?->can('reports.export'), 403);

        $exportLimiter = app(DailyExportLimitService::class);
        if (! $exportLimiter->allowAndRecord($organization)) {
            return response('Daily export limit reached for your plan.', 429);
        }

        $normalized = \Illuminate\Support\Str::of($entity)->lower()->trim()->rtrim('s')->value();

        if ($normalized !== 'client') {
            abort(404, 'Unsupported export entity.');
        }

        $query = Client::query()
            ->where('organization_id', (int) $organization->id);

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

        $callback = static function () use ($query): void {
            $out = fopen('php://output', 'w');

            fputcsv($out, ['Company', 'Primary Contact Email', 'Primary Contact Phone']);

            foreach ($query->cursor() as $client) {
                /** @var Client $client */
                fputcsv($out, [
                    (string) $client->getAttribute('company_name'),
                    (string) $client->getAttribute('primary_contact_email'),
                    (string) $client->getAttribute('primary_contact_phone'),
                ]);
            }

            fclose($out);
        };

        return response()->streamDownload($callback, $filename, $headers);
    }
}
