<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\OrganizationSubscription;
use App\Models\StripeWebhookEvent;
use App\Services\Billing\EntitlementsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Read-only System Performance Dashboard for platform operators.
 *
 * Surfaces infrastructure readiness, queue health, webhook reliability,
 * and storage pressure using signals already available in the app and database.
 * No external APM, no mutations.
 */
final class SystemPerformanceDashboardController extends Controller
{
    public function __construct(
        private EntitlementsService $entitlements,
    ) {
    }

    public function index(Request $request): Response
    {
        return Inertia::render('Platform/SystemPerformance/Index', [
            'readiness' => $this->loadReadinessSnapshot(),
            'queue_health' => $this->loadQueueHealth(),
            'webhook_health' => $this->loadWebhookHealth(),
            'storage_pressure' => $this->loadStoragePressure(),
            'at_risk_orgs' => $this->loadAtRiskOrgs(),
            'platform_summary' => $this->loadPlatformSummary(),
        ]);
    }

    /**
     * Derive readiness status from the same checks the /_readiness endpoint uses.
     *
     * @return array{status: string, checks: array<string, array{ok: bool, driver: string, error?: string}>, timestamp: string}
     */
    private function loadReadinessSnapshot(): array
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
            'queue' => $this->checkQueueConfig(),
        ];

        $healthy = collect($checks)->every(fn (array $c) => $c['ok']);

        return [
            'status' => $healthy ? 'healthy' : 'degraded',
            'checks' => $checks,
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * @return array{ok: bool, driver: string, error?: string}
     */
    private function checkDatabase(): array
    {
        try {
            DB::connection()->getPdo();

            return [
                'ok' => true,
                'driver' => (string) config('database.default'),
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'driver' => (string) config('database.default'),
                'error' => 'Connection failed',
            ];
        }
    }

    /**
     * @return array{ok: bool, driver: string, error?: string}
     */
    private function checkCache(): array
    {
        $driver = (string) config('cache.default');

        try {
            $testKey = '_sysperf_probe_' . bin2hex(random_bytes(4));
            Cache::put($testKey, true, 5);
            $result = Cache::get($testKey) === true;
            Cache::forget($testKey);

            return [
                'ok' => $result,
                'driver' => $driver,
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'driver' => $driver,
                'error' => 'Cache unavailable',
            ];
        }
    }

    /**
     * @return array{ok: bool, driver: string}
     */
    private function checkQueueConfig(): array
    {
        $driver = (string) config('queue.default');
        $validDrivers = ['sync', 'database', 'redis', 'beanstalkd', 'sqs'];

        return [
            'ok' => in_array($driver, $validDrivers, true),
            'driver' => $driver,
        ];
    }

    /**
     * Summarize failed_jobs table for queue health indicators.
     *
     * @return array{total_failed: int, failed_last_24h: int, failed_last_7d: int, queue_driver: string, recent_failures: array<int, array{id: int, queue: string, failed_at: string}>}
     */
    private function loadQueueHealth(): array
    {
        $totalFailed = (int) DB::table('failed_jobs')->count();

        $failed24h = (int) DB::table('failed_jobs')
            ->where('failed_at', '>=', now()->subDay())
            ->count();

        $failed7d = (int) DB::table('failed_jobs')
            ->where('failed_at', '>=', now()->subDays(7))
            ->count();

        $recentFailures = DB::table('failed_jobs')
            ->orderByDesc('failed_at')
            ->limit(5)
            ->get(['id', 'queue', 'failed_at'])
            ->map(fn ($row) => [
                'id' => $row->id,
                'queue' => $row->queue,
                'failed_at' => $row->failed_at,
            ])
            ->all();

        return [
            'total_failed' => $totalFailed,
            'failed_last_24h' => $failed24h,
            'failed_last_7d' => $failed7d,
            'queue_driver' => (string) config('queue.default'),
            'recent_failures' => $recentFailures,
        ];
    }

    /**
     * Aggregate webhook health metrics from stripe_webhook_events.
     *
     * @return array{total_events: int, processed_count: int, failed_count: int, failed_last_24h: int, failed_last_7d: int, orgs_with_failures_7d: int, recent_failures: array<int, array{id: int, stripe_event_id: string, type: string, status: string, organization_id: int|null, created_at: string}>}
     */
    private function loadWebhookHealth(): array
    {
        $totalEvents = (int) StripeWebhookEvent::count();

        $statusCounts = StripeWebhookEvent::query()
            ->selectRaw("status, count(*) as cnt")
            ->groupBy('status')
            ->pluck('cnt', 'status')
            ->all();

        $processedCount = (int) ($statusCounts['processed'] ?? 0);
        $failedCount = (int) ($statusCounts['failed'] ?? 0);

        $failed24h = (int) StripeWebhookEvent::query()
            ->where('status', 'failed')
            ->where('created_at', '>=', now()->subDay())
            ->count();

        $failed7d = (int) StripeWebhookEvent::query()
            ->where('status', 'failed')
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        $orgsWithFailures7d = (int) StripeWebhookEvent::query()
            ->where('status', 'failed')
            ->where('created_at', '>=', now()->subDays(7))
            ->whereNotNull('organization_id')
            ->distinct('organization_id')
            ->count('organization_id');

        $recentFailures = StripeWebhookEvent::query()
            ->where('status', 'failed')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get(['id', 'stripe_event_id', 'type', 'status', 'organization_id', 'created_at'])
            ->map(fn ($e) => [
                'id' => $e->id,
                'stripe_event_id' => $e->stripe_event_id,
                'type' => $e->type,
                'status' => $e->status,
                'organization_id' => $e->organization_id,
                'created_at' => $e->created_at?->toIso8601String(),
            ])
            ->all();

        return [
            'total_events' => $totalEvents,
            'processed_count' => $processedCount,
            'failed_count' => $failedCount,
            'failed_last_24h' => $failed24h,
            'failed_last_7d' => $failed7d,
            'orgs_with_failures_7d' => $orgsWithFailures7d,
            'recent_failures' => $recentFailures,
        ];
    }

    /**
     * Identify orgs near or over their storage limit using batch queries.
     *
     * @return array{orgs_over_limit: int, orgs_near_limit: int, total_storage_used_gb: float, details: array<int, array{id: int, name: string, slug: string, used_gb: float, limit_gb: int|float, pct_used: float, over_limit: bool}>}
     */
    private function loadStoragePressure(): array
    {
        $bytesPerGb = 1_073_741_824;

        $totalUsedBytes = (int) DB::table('project_files')->sum('size');
        $totalStorageUsedGb = round($totalUsedBytes / $bytesPerGb, 2);

        $orgUsages = DB::table('project_files')
            ->groupBy('organization_id')
            ->selectRaw('organization_id, coalesce(sum(size), 0) as total_bytes')
            ->having(DB::raw('coalesce(sum(size), 0)'), '>', 0)
            ->pluck('total_bytes', 'organization_id')
            ->all();

        if (count($orgUsages) === 0) {
            return [
                'orgs_over_limit' => 0,
                'orgs_near_limit' => 0,
                'total_storage_used_gb' => $totalStorageUsedGb,
                'details' => [],
            ];
        }

        $orgs = Organization::query()
            ->whereIn('id', array_keys($orgUsages))
            ->with(['billingSubscription', 'addons', 'features'])
            ->get();

        $overLimit = 0;
        $nearLimit = 0;
        $details = [];

        foreach ($orgs as $org) {
            $bytes = (int) ($orgUsages[$org->id] ?? 0);
            $usedGb = round($bytes / $bytesPerGb, 2);
            $limitGb = $this->entitlements->value('storage_gb', $org);
            $limitGb = is_numeric($limitGb) ? (int) $limitGb : 0;

            if ($limitGb <= 0) {
                continue;
            }

            $limitBytes = (int) round($limitGb * $bytesPerGb);
            $pctUsed = round(($bytes / $limitBytes) * 100, 1);
            $isOver = $bytes > $limitBytes;
            $isNear = ! $isOver && $pctUsed >= 90;

            if ($isOver) {
                $overLimit++;
            }
            if ($isNear) {
                $nearLimit++;
            }

            if ($isOver || $isNear) {
                $details[] = [
                    'id' => $org->id,
                    'name' => $org->name,
                    'slug' => $org->slug,
                    'used_gb' => $usedGb,
                    'limit_gb' => $limitGb,
                    'pct_used' => $pctUsed,
                    'over_limit' => $isOver,
                ];
            }
        }

        usort($details, fn ($a, $b) => $b['pct_used'] <=> $a['pct_used']);

        return [
            'orgs_over_limit' => $overLimit,
            'orgs_near_limit' => $nearLimit,
            'total_storage_used_gb' => $totalStorageUsedGb,
            'details' => array_slice($details, 0, 10),
        ];
    }

    /**
     * Count orgs with operational risk signals: billing issues + repeated webhook failures.
     *
     * @return array{billing_at_risk: int, webhook_at_risk: int, past_due_count: int, unpaid_count: int, orgs_3plus_webhook_failures: int}
     */
    private function loadAtRiskOrgs(): array
    {
        $pastDueCount = (int) OrganizationSubscription::where('status', 'past_due')->count();
        $unpaidCount = (int) OrganizationSubscription::where('status', 'unpaid')->count();

        $orgs3PlusWebhookFailures = (int) StripeWebhookEvent::query()
            ->where('status', 'failed')
            ->where('created_at', '>=', now()->subDays(7))
            ->whereNotNull('organization_id')
            ->groupBy('organization_id')
            ->havingRaw('count(*) >= 3')
            ->selectRaw('organization_id')
            ->get()
            ->count();

        return [
            'billing_at_risk' => $pastDueCount + $unpaidCount,
            'webhook_at_risk' => $orgs3PlusWebhookFailures,
            'past_due_count' => $pastDueCount,
            'unpaid_count' => $unpaidCount,
            'orgs_3plus_webhook_failures' => $orgs3PlusWebhookFailures,
        ];
    }

    /**
     * High-level platform summary for context.
     *
     * @return array{total_orgs: int, total_users: int, active_subscriptions: int, stripe_linked: int}
     */
    private function loadPlatformSummary(): array
    {
        return [
            'total_orgs' => (int) Organization::count(),
            'total_users' => (int) DB::table('users')->count(),
            'active_subscriptions' => (int) OrganizationSubscription::query()
                ->whereIn('status', ['active', 'trialing'])
                ->count(),
            'stripe_linked' => (int) Organization::query()
                ->whereNotNull('stripe_id')
                ->where('stripe_id', '!=', '')
                ->count(),
        ];
    }
}
