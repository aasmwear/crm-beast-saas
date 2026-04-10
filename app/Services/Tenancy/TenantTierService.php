<?php

declare(strict_types=1);

namespace App\Services\Tenancy;

use App\Models\Organization;
use App\Models\WebhookEventSummary;
use Illuminate\Support\Facades\DB;

/**
 * Read-only tenant size classification (no billing or enforcement).
 * Persists to organizations.tier; recalculate after metrics snapshot or via artisan.
 */
final class TenantTierService
{
    public const TIER_SMALL = 'small';

    public const TIER_MEDIUM = 'medium';

    public const TIER_LARGE = 'large';

    public const TIER_ENTERPRISE = 'enterprise';

    /** @var list<string> */
    private const TIER_ORDER = [
        self::TIER_SMALL,
        self::TIER_MEDIUM,
        self::TIER_LARGE,
        self::TIER_ENTERPRISE,
    ];

    /**
     * Classify an organization from current DB counts (no persistence).
     */
    public function determineTier(Organization $organization): string
    {
        $metrics = $this->resolveMetrics((int) $organization->id);

        return $this->determineTierFromMetrics($metrics);
    }

    /**
     * Recompute tier from live metrics and persist when changed.
     */
    public function recalculateTier(Organization $organization): string
    {
        $tier = $this->determineTier($organization);
        if ($organization->tier !== $tier) {
            $organization->forceFill(['tier' => $tier])->saveQuietly();
        }

        return $tier;
    }

    /**
     * Recalculate all organizations (chunked by id).
     *
     * @return int Number of organizations processed
     */
    public function recalculateAllOrganizations(): int
    {
        $count = 0;
        Organization::query()
            ->select(['id', 'tier'])
            ->orderBy('id')
            ->chunkById(100, function ($orgs) use (&$count): void {
                foreach ($orgs as $org) {
                    $this->recalculateTier($org);
                    $count++;
                }
            });

        return $count;
    }

    /**
     * @param  array{clients: int, projects: int, tasks: int, webhook_events_total: int}  $metrics
     */
    public function determineTierFromMetrics(array $metrics): string
    {
        $thresholds = (array) config('tenant_tiers.thresholds', []);
        $maxRank = 0;

        foreach (['clients', 'projects', 'tasks', 'webhook_events_total'] as $key) {
            $cuts = $thresholds[$key] ?? null;
            if (! is_array($cuts) || count($cuts) !== 3) {
                continue;
            }
            /** @var array{0: int|float, 1: int|float, 2: int|float} $cuts */
            $maxRank = max($maxRank, $this->rankForMetric((int) ($metrics[$key] ?? 0), $cuts));
        }

        return self::TIER_ORDER[$maxRank];
    }

    /**
     * @return array{clients: int, projects: int, tasks: int, webhook_events_total: int}
     */
    public function resolveMetrics(int $organizationId): array
    {
        $clients = (int) DB::table('clients')
            ->where('organization_id', $organizationId)
            ->whereNull('deleted_at')
            ->count();

        $projects = (int) DB::table('projects')
            ->where('organization_id', $organizationId)
            ->count();

        $tasks = (int) DB::table('tasks')
            ->where('organization_id', $organizationId)
            ->whereNull('deleted_at')
            ->count();

        $webhookTotal = (int) WebhookEventSummary::query()
            ->where('provider', 'stripe')
            ->where('organization_id', $organizationId)
            ->sum('total_count');

        return [
            'clients' => $clients,
            'projects' => $projects,
            'tasks' => $tasks,
            'webhook_events_total' => $webhookTotal,
        ];
    }

    /**
     * @param  array{0: int|float, 1: int|float, 2: int|float}  $thresholdsAsc
     */
    private function rankForMetric(int $value, array $thresholdsAsc): int
    {
        $t0 = (int) $thresholdsAsc[0];
        $t1 = (int) $thresholdsAsc[1];
        $t2 = (int) $thresholdsAsc[2];

        if ($value >= $t2) {
            return 3;
        }
        if ($value >= $t1) {
            return 2;
        }
        if ($value >= $t0) {
            return 1;
        }

        return 0;
    }
}
