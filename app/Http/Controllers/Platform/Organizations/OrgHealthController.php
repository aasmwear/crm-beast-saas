<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform\Organizations;

use App\Http\Controllers\Controller;
use App\Models\OrgDailyMetric;
use App\Models\Organization;
use App\Models\StripeWebhookEvent;
use App\Services\Billing\EntitlementsService;
use App\Services\Billing\SeatCounter;
use App\Services\Billing\StorageUsageService;
use App\Support\PlanCatalog;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Read-only Org Health Dashboard for platform operators.
 * Surfaces billing, seat, storage, and webhook health indicators per tenant.
 *
 * Hybrid read-model: `seats_active` uses `org_daily_metrics.users_count` for yesterday
 * when a row exists for that org (same query semantics as live seat counting in
 * OrgMetricsSnapshotService). Otherwise live counts. Billing, webhooks, and storage stay live.
 */
final class OrgHealthController extends Controller
{
    public function __construct(
        private EntitlementsService $entitlements,
        private SeatCounter $seatCounter,
        private StorageUsageService $storageUsage,
    ) {
    }

    /**
     * Aligns with scheduled snapshots and other platform hybrid dashboards (app timezone).
     */
    private function orgHealthSnapshotReferenceDate(): CarbonImmutable
    {
        return CarbonImmutable::yesterday();
    }

    public function index(Request $request): Response
    {
        $query = Organization::query()
            ->with(['billingSubscription', 'addons'])
            ->orderBy('name');

        if ($search = $request->filled('search') ? (string) $request->input('search') : null) {
            $term = '%' . trim($search) . '%';
            $query->where(function ($q) use ($term) {
                $q->where('name', 'ilike', $term)
                    ->orWhere('slug', 'ilike', $term);
            });
        }

        if ($request->filled('status')) {
            $status = (string) $request->input('status');
            if ($status === 'none') {
                $query->whereDoesntHave('billingSubscription');
            } else {
                $query->whereHas('billingSubscription', fn ($q) => $q->where('status', $status));
            }
        }

        if ($request->filled('health')) {
            $health = (string) $request->input('health');
            $this->applyHealthFilter($query, $health);
        }

        $paginator = $query->paginate(perPage: min((int) $request->input('per_page', 20), 50))
            ->withQueryString();

        $orgIds = $paginator->getCollection()->pluck('id')->all();
        $webhookStats = $this->loadWebhookStats($orgIds);
        $seatCounts = $this->loadSeatCountsWithSnapshotFallback($orgIds);
        $storageSummaries = $this->loadStorageSummaries($paginator->getCollection());

        $organizations = $paginator->getCollection()->map(function (Organization $org) use ($webhookStats, $seatCounts, $storageSummaries) {
            return $this->mapOrgToHealthRow($org, $webhookStats[$org->id] ?? [], $seatCounts[$org->id] ?? 0, $storageSummaries[$org->id] ?? null);
        });

        $paginator->setCollection($organizations);

        return Inertia::render('Platform/Organizations/OrgHealth', [
            'organizations' => $paginator,
            'filters' => [
                'search' => $request->input('search'),
                'status' => $request->input('status'),
                'health' => $request->input('health'),
            ],
            'statusOptions' => [
                'none',
                'active',
                'trialing',
                'past_due',
                'incomplete',
                'canceled',
                'unpaid',
            ],
            'healthOptions' => [
                'healthy',
                'warning',
                'critical',
            ],
        ]);
    }

    /**
     * Batch-load webhook stats for the given org IDs to avoid N+1.
     *
     * @param  array<int>  $orgIds
     * @return array<int, array{last_type: string|null, last_processed_at: string|null, last_status: string|null, recent_failed_count: int}>
     */
    private function loadWebhookStats(array $orgIds): array
    {
        if (count($orgIds) === 0) {
            return [];
        }

        $recentFailedCutoff = now()->subDays(7);

        $lastPerOrg = StripeWebhookEvent::query()
            ->whereIn('organization_id', $orgIds)
            ->whereNotNull('organization_id')
            ->orderByDesc('processed_at')
            ->orderByDesc('created_at')
            ->get(['organization_id', 'type', 'status', 'processed_at'])
            ->groupBy('organization_id')
            ->map(fn ($events) => $events->first())
            ->all();

        $failedCounts = StripeWebhookEvent::query()
            ->whereIn('organization_id', $orgIds)
            ->whereNotNull('organization_id')
            ->where('status', 'failed')
            ->where('created_at', '>=', $recentFailedCutoff)
            ->groupBy('organization_id')
            ->selectRaw('organization_id, count(*) as cnt')
            ->pluck('cnt', 'organization_id')
            ->all();

        $result = [];
        foreach ($orgIds as $orgId) {
            $last = $lastPerOrg[$orgId] ?? null;
            $result[$orgId] = [
                'last_type' => $last?->type,
                'last_processed_at' => $last?->processed_at?->toIso8601String(),
                'last_status' => $last?->status,
                'recent_failed_count' => (int) ($failedCounts[$orgId] ?? 0),
            ];
        }

        return $result;
    }

    /**
     * Active tenant users per org (excludes portal/client-linked users).
     * Same definition as `OrgMetricsSnapshotService` users_count.
     *
     * @param  array<int>  $orgIds
     * @return array<int, int>
     */
    private function loadSeatCountsLive(array $orgIds): array
    {
        if (count($orgIds) === 0) {
            return [];
        }

        return DB::table('organization_user')
            ->join('users', 'users.id', '=', 'organization_user.user_id')
            ->whereIn('organization_user.organization_id', $orgIds)
            ->whereNull('users.client_id')
            ->groupBy('organization_user.organization_id')
            ->selectRaw('organization_user.organization_id, count(*) as cnt')
            ->pluck('cnt', 'organization_id')
            ->mapWithKeys(fn ($cnt, $orgId) => [(int) $orgId => (int) $cnt])
            ->all();
    }

    /**
     * Prefer `users_count` from yesterday's org_daily_metrics per org when present; else live.
     *
     * @param  array<int>  $orgIds
     * @return array<int, int>
     */
    private function loadSeatCountsWithSnapshotFallback(array $orgIds): array
    {
        if (count($orgIds) === 0) {
            return [];
        }

        $live = $this->loadSeatCountsLive($orgIds);
        $dateStr = $this->orgHealthSnapshotReferenceDate()->toDateString();

        $snapshotRows = OrgDailyMetric::query()
            ->where('metric_date', $dateStr)
            ->whereIn('organization_id', $orgIds)
            ->get(['organization_id', 'users_count']);

        $fromSnapshot = [];
        foreach ($snapshotRows as $row) {
            $fromSnapshot[(int) $row->organization_id] = (int) $row->users_count;
        }

        $result = [];
        foreach ($orgIds as $orgId) {
            $orgId = (int) $orgId;
            if (array_key_exists($orgId, $fromSnapshot)) {
                $result[$orgId] = $fromSnapshot[$orgId];
            } else {
                $result[$orgId] = (int) ($live[$orgId] ?? 0);
            }
        }

        return $result;
    }

    /**
     * Batch-load storage usage summaries for the current page of orgs.
     *
     * @param  \Illuminate\Support\Collection<int, Organization>  $orgs
     * @return array<int, array{used_gb: float, limit_gb: int|float, over_limit: bool}>
     */
    private function loadStorageSummaries($orgs): array
    {
        $orgIds = $orgs->pluck('id')->all();
        if (count($orgIds) === 0) {
            return [];
        }

        $usageBytes = DB::table('project_files')
            ->whereIn('organization_id', $orgIds)
            ->groupBy('organization_id')
            ->selectRaw('organization_id, coalesce(sum(size), 0) as total_bytes')
            ->pluck('total_bytes', 'organization_id')
            ->all();

        $result = [];
        $bytesPerGb = 1_073_741_824;

        foreach ($orgs as $org) {
            $bytes = (int) ($usageBytes[$org->id] ?? 0);
            $usedGb = round($bytes / $bytesPerGb, 2);
            $limitGb = $this->entitlements->value('storage_gb', $org);
            $limitGb = is_numeric($limitGb) ? (int) $limitGb : 0;
            $overLimit = $limitGb > 0 && $bytes > (int) round($limitGb * $bytesPerGb);

            $result[$org->id] = [
                'used_gb' => $usedGb,
                'limit_gb' => $limitGb,
                'over_limit' => $overLimit,
            ];
        }

        return $result;
    }

    /**
     * @param  array{last_type?: string|null, last_processed_at?: string|null, last_status?: string|null, recent_failed_count?: int}  $webhookStats
     * @param  array{used_gb: float, limit_gb: int|float, over_limit: bool}|null  $storageSummary
     * @return array<string, mixed>
     */
    private function mapOrgToHealthRow(Organization $org, array $webhookStats, int $activeSeats, ?array $storageSummary): array
    {
        $sub = $org->billingSubscription;
        $planKey = $sub?->plan_key ?? null;
        if ($planKey === null || $planKey === '' || ! PlanCatalog::isValidPlanKey($planKey)) {
            $legacyPlan = $org->plan ?? null;
            $planKey = ($legacyPlan !== null && $legacyPlan !== '' && PlanCatalog::isValidPlanKey((string) $legacyPlan))
                ? (string) $legacyPlan
                : PlanCatalog::defaultPlanKey();
        } else {
            $planKey = (string) $planKey;
        }

        $planDefaults = PlanCatalog::get($planKey);
        $seatsIncluded = $sub?->seats_included ?? $planDefaults['seats_included'] ?? 5;
        $seatLimit = $sub?->seat_limit;
        $effectiveSeatLimit = $seatLimit ?? $seatsIncluded;

        $webhook = [
            'last_type' => $webhookStats['last_type'] ?? null,
            'last_processed_at' => $webhookStats['last_processed_at'] ?? null,
            'last_status' => $webhookStats['last_status'] ?? null,
            'recent_failed_count' => $webhookStats['recent_failed_count'] ?? 0,
        ];

        $storage = $storageSummary ?? ['used_gb' => 0, 'limit_gb' => 0, 'over_limit' => false];

        $healthFlags = $this->computeHealthFlags(
            status: $sub?->status ?? 'none',
            activeSeats: $activeSeats,
            effectiveSeatLimit: $effectiveSeatLimit,
            webhook: $webhook,
            storage: $storage,
        );

        return [
            'id' => $org->id,
            'name' => $org->name,
            'slug' => $org->slug,
            'has_stripe_id' => $org->hasStripeId(),
            'plan_key' => $planKey,
            'status' => $sub?->status ?? 'none',
            'seats_active' => $activeSeats,
            'seats_included' => $seatsIncluded,
            'seat_limit' => $seatLimit,
            'storage' => $storage,
            'webhook' => $webhook,
            'health_flags' => $healthFlags,
            'health_state' => $this->overallHealthState($healthFlags),
            'subscriptions_url' => route('platform.organizations.subscriptions', [
                'search' => $org->slug,
            ]),
            'tenant_billing_url' => url("/org/{$org->slug}/billing"),
        ];
    }

    /**
     * Compute individual health flags for an org.
     *
     * @param  array{last_type: string|null, last_processed_at: string|null, last_status: string|null, recent_failed_count: int}  $webhook
     * @param  array{used_gb: float, limit_gb: int|float, over_limit: bool}  $storage
     * @return array<string, string>
     */
    private function computeHealthFlags(string $status, int $activeSeats, int $effectiveSeatLimit, array $webhook, array $storage): array
    {
        $flags = [];

        if (in_array($status, ['past_due', 'unpaid'], true)) {
            $flags['billing'] = 'critical';
        } elseif ($status === 'canceled') {
            $flags['billing'] = 'warning';
        } elseif ($status === 'incomplete') {
            $flags['billing'] = 'warning';
        }

        if ($effectiveSeatLimit > 0 && $activeSeats >= $effectiveSeatLimit) {
            $flags['seats'] = 'critical';
        } elseif ($effectiveSeatLimit > 0 && $activeSeats >= (int) round($effectiveSeatLimit * 0.9)) {
            $flags['seats'] = 'warning';
        }

        if ($storage['over_limit']) {
            $flags['storage'] = 'critical';
        } elseif ($storage['limit_gb'] > 0 && $storage['used_gb'] >= round($storage['limit_gb'] * 0.9, 2)) {
            $flags['storage'] = 'warning';
        }

        if ($webhook['recent_failed_count'] >= 3) {
            $flags['webhooks'] = 'critical';
        } elseif ($webhook['recent_failed_count'] > 0 || $webhook['last_status'] === 'failed') {
            $flags['webhooks'] = 'warning';
        }

        return $flags;
    }

    /**
     * Derive overall health state from individual flags.
     */
    private function overallHealthState(array $flags): string
    {
        if (in_array('critical', $flags, true)) {
            return 'critical';
        }
        if (in_array('warning', $flags, true)) {
            return 'warning';
        }

        return 'healthy';
    }

    /**
     * Apply health state filter to the org query.
     */
    private function applyHealthFilter($query, string $health): void
    {
        if ($health === 'critical') {
            $query->where(function ($q) {
                $q->whereHas('billingSubscription', fn ($sub) => $sub->whereIn('status', ['past_due', 'unpaid']));
            });
        } elseif ($health === 'warning') {
            $query->where(function ($q) {
                $q->whereHas('billingSubscription', fn ($sub) => $sub->whereIn('status', ['canceled', 'incomplete']));
            });
        } elseif ($health === 'healthy') {
            $query->where(function ($q) {
                $q->whereHas('billingSubscription', fn ($sub) => $sub->whereIn('status', ['active', 'trialing']))
                    ->orWhereDoesntHave('billingSubscription');
            });
        }
    }
}
