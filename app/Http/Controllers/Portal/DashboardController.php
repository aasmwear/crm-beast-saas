<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Project;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class DashboardController extends Controller
{
    /**
     * Portal dashboard: show projects for the authenticated client user.
     */
    public function index(Request $request): Response
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        if (empty($user->client_id)) {
            abort(403, 'Portal access is not configured for your account.');
        }

        $client = $user->client;
        $clientName = $client ? $client->company_name : 'Client';

        $projects = Project::query()
            ->where('client_id', $user->client_id)
            ->select(['id', 'client_id', 'title', 'status', 'start_date', 'end_date'])
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        $invoices = Invoice::query()
            ->where('client_id', $user->client_id)
            ->select(['id', 'number', 'issue_date', 'due_date', 'status', 'total_cents', 'currency', 'paid_at'])
            ->orderByDesc('issue_date')
            ->limit(50)
            ->get();

        return Inertia::render('Portal/Dashboard', [
            'clientName' => $clientName,
            'projects' => $projects,
            'invoices' => $invoices,
        ]);
    }
}
