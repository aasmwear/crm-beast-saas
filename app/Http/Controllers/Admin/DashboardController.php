<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Global Cockpit: System Overview for Super Admins.
     * KPIs: total tenants, total users, active tenants (30d), recent tenants.
     * Chart: tenant growth per month (last 6 months).
     */
    public function index(): Response
    {
        $now = now();

        // KPIs
        $totalTenants = (int) DB::table('organizations')->count();
        $totalUsers = (int) DB::table('users')->count();

        // Active tenants: orgs with login or activity in last 30 days (proxy: orgs with recent project/task/client/org creation)
        $thirtyDaysAgo = $now->copy()->subDays(30);
        $activeTenants30d = (int) DB::table('organizations')
            ->where(function ($q) use ($thirtyDaysAgo) {
                $q->where('organizations.created_at', '>=', $thirtyDaysAgo)
                    ->orWhereExists(function ($sub) use ($thirtyDaysAgo) {
                        $sub->selectRaw(1)
                            ->from('projects')
                            ->whereColumn('projects.organization_id', 'organizations.id')
                            ->where('projects.created_at', '>=', $thirtyDaysAgo);
                    })
                    ->orWhereExists(function ($sub) use ($thirtyDaysAgo) {
                        $sub->selectRaw(1)
                            ->from('tasks')
                            ->whereColumn('tasks.organization_id', 'organizations.id')
                            ->where('tasks.created_at', '>=', $thirtyDaysAgo);
                    })
                    ->orWhereExists(function ($sub) use ($thirtyDaysAgo) {
                        $sub->selectRaw(1)
                            ->from('clients')
                            ->whereColumn('clients.organization_id', 'organizations.id')
                            ->where('clients.created_at', '>=', $thirtyDaysAgo);
                    });
            })
            ->count();

        // Recent tenants: last 5 created organizations, with owner email
        $recentTenants = DB::table('organizations')
            ->select([
                'organizations.id',
                'organizations.name',
                'organizations.slug',
                'organizations.created_at',
                DB::raw("(SELECT u.email FROM organization_user ou JOIN users u ON u.id = ou.user_id WHERE ou.organization_id = organizations.id AND ou.is_owner = true LIMIT 1) as owner_email"),
            ])
            ->orderByDesc('organizations.created_at')
            ->limit(5)
            ->get()
            ->map(fn ($row) => [
                'id' => $row->id,
                'name' => $row->name,
                'slug' => $row->slug,
                'created_at' => $row->created_at,
                'owner_email' => $row->owner_email ?? '—',
            ])
            ->values()
            ->all();

        // Tenant growth: new tenants per month for last 6 months
        $tenantGrowth = $this->monthlyTenantGrowth(6);

        return Inertia::render('Admin/Dashboard', [
            'kpis' => [
                'total_tenants' => $totalTenants,
                'total_users' => $totalUsers,
                'active_tenants_30d' => $activeTenants30d,
            ],
            'recent_tenants' => $recentTenants,
            'tenant_growth' => $tenantGrowth,
            'system_status' => 'operational',
        ]);
    }

    /**
     * New tenants created per month for the last N months.
     *
     * @return array{labels: list<string>, values: list<int>}
     */
    private function monthlyTenantGrowth(int $months = 6): array
    {
        $labels = [];
        $values = [];

        for ($i = $months - 1; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $labels[] = $date->format('M Y');
            $start = $date->copy()->startOfMonth();
            $end = $date->copy()->endOfMonth();

            $values[] = (int) DB::table('organizations')
                ->whereBetween('created_at', [$start, $end])
                ->count();
        }

        return ['labels' => $labels, 'values' => $values];
    }
}
