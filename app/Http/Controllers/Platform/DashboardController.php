<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Platform Dashboard Controller
 * 
 * Provides system-wide metrics and insights for Super Admins.
 */
class DashboardController extends Controller
{
    /**
     * Display the platform dashboard.
     */
    public function index(Request $request): Response
    {
        // Calculate total MRR (placeholder - can be calculated from actual billing data)
        $totalMrr = $this->calculateTotalMrr();

        // Count active organizations
        $activeTenants = Organization::count();

        // Count total users across all organizations
        $totalUsers = User::count();

        // Get recent organization signups (last 7 days)
        $recentSignups = Organization::query()
            ->withCount('users')
            ->latest()
            ->take(10)
            ->get()
            ->map(function ($org) {
                return [
                    'name' => $org->name,
                    'slug' => $org->slug,
                    'plan' => $org->plan ?? 'trial',
                    'users_count' => $org->users_count,
                    'created_at' => $org->created_at->toISOString(),
                ];
            });

        return Inertia::render('Platform/Dashboard/Index', [
            'total_mrr' => $totalMrr,
            'active_tenants' => $activeTenants,
            'total_users' => $totalUsers,
            'recent_signups' => $recentSignups,
        ]);
    }

    /**
     * Calculate total Monthly Recurring Revenue.
     * 
     * This is a placeholder implementation. In production, this would:
     * - Query Stripe/Cashier for actual subscription data
     * - Sum up all active subscriptions
     * - Calculate based on billing cycles
     * 
     * @return int
     */
    private function calculateTotalMrr(): int
    {
        // Placeholder calculation: estimate based on organization count
        $orgCount = Organization::count();
        
        // Assume average of $50/month per organization (trial + paid mix)
        // In production, replace with actual billing data from Cashier
        $estimatedMrr = $orgCount * 50;

        return $estimatedMrr;
    }
}
