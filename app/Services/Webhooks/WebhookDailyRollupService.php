<?php

declare(strict_types=1);

namespace App\Services\Webhooks;

use App\Models\WebhookEventDailyRollup;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Rebuilds webhook_event_daily_rollups from stripe_webhook_events (read model only).
 * Groups by calendar date in the application timezone (PostgreSQL AT TIME ZONE).
 */
final class WebhookDailyRollupService
{
    public const DEFAULT_PROVIDER = 'stripe';

    public function __construct(
        private WebhookSummaryService $summaryScope,
    ) {}

    /**
     * @param  int|null  $daysWindow  When set, only delete/rebuild rollup rows whose event_date falls in
     *                                [today_app - (days-1), today_app] inclusive (same span as "last N calendar days").
     */
    public function rebuildAll(string $provider = self::DEFAULT_PROVIDER, ?int $daysWindow = null): int
    {
        return DB::transaction(function () use ($provider, $daysWindow): int {
            if ($daysWindow !== null) {
                [$minDate, $maxDate] = $this->calendarDateRangeForLastNDays($daysWindow);
                $this->deleteRollupsForProviderInDateRange($provider, $minDate, $maxDate);

                return $this->insertAggregates($provider, null, $minDate, $maxDate);
            }

            WebhookEventDailyRollup::query()->where('provider', $provider)->delete();

            return $this->insertAggregates($provider, null, null, null);
        });
    }

    /**
     * @param  int|null  $daysWindow  Same semantics as {@see rebuildAll()}
     */
    public function rebuildForOrganizationScope(int $organizationId, string $provider = self::DEFAULT_PROVIDER, ?int $daysWindow = null): int
    {
        return DB::transaction(function () use ($organizationId, $provider, $daysWindow): int {
            $scope = $this->summaryScope->organizationScope($organizationId);

            if ($daysWindow !== null) {
                [$minDate, $maxDate] = $this->calendarDateRangeForLastNDays($daysWindow);
                WebhookEventDailyRollup::query()
                    ->where('provider', $provider)
                    ->where('organization_scope', $scope)
                    ->whereBetween('event_date', [$minDate, $maxDate])
                    ->delete();

                return $this->insertAggregates($provider, $organizationId, $minDate, $maxDate);
            }

            WebhookEventDailyRollup::query()
                ->where('provider', $provider)
                ->where('organization_scope', $scope)
                ->delete();

            return $this->insertAggregates($provider, $organizationId, null, null);
        });
    }

    /**
     * Inclusive calendar dates in app timezone covering the last $n days ending today (n >= 1).
     *
     * @return array{0: string, 1: string} [minDate Y-m-d, maxDate Y-m-d]
     */
    public function calendarDateRangeForLastNDays(int $n): array
    {
        $n = max(1, $n);
        $tz = (string) config('app.timezone');
        $today = CarbonImmutable::now($tz)->startOfDay();
        $min = $today->subDays($n - 1);

        return [$min->toDateString(), $today->toDateString()];
    }

    private function deleteRollupsForProviderInDateRange(string $provider, string $minDate, string $maxDate): void
    {
        WebhookEventDailyRollup::query()
            ->where('provider', $provider)
            ->whereBetween('event_date', [$minDate, $maxDate])
            ->delete();
    }

    /**
     * @return int Rows inserted
     */
    private function insertAggregates(
        string $provider,
        ?int $organizationId,
        ?string $minEventDate,
        ?string $maxEventDate,
    ): int {
        $driver = DB::connection()->getDriverName();
        if ($driver !== 'pgsql') {
            throw new RuntimeException('Webhook daily rollups require PostgreSQL (AT TIME ZONE grouping).');
        }

        $tz = str_replace("'", "''", (string) config('app.timezone'));
        $dateExpr = "((stripe_webhook_events.created_at AT TIME ZONE 'UTC') AT TIME ZONE '{$tz}')::date";

        $bindings = [];
        $where = [];
        if ($organizationId !== null) {
            $where[] = 'stripe_webhook_events.organization_id = ?';
            $bindings[] = $organizationId;
        }
        if ($minEventDate !== null && $maxEventDate !== null) {
            $where[] = "{$dateExpr} BETWEEN ? AND ?";
            $bindings[] = $minEventDate;
            $bindings[] = $maxEventDate;
        }

        $whereSql = $where === [] ? '' : 'WHERE '.implode(' AND ', $where);

        $sql = "
            SELECT
                stripe_webhook_events.organization_id,
                stripe_webhook_events.type,
                {$dateExpr} AS event_date,
                COUNT(*)::int AS total_count,
                SUM(CASE WHEN stripe_webhook_events.status = 'processed' THEN 1 ELSE 0 END)::int AS success_count,
                SUM(CASE WHEN stripe_webhook_events.status = 'failed' THEN 1 ELSE 0 END)::int AS failure_count,
                MAX(stripe_webhook_events.created_at) AS last_received_at,
                MAX(CASE WHEN stripe_webhook_events.status = 'processed' THEN stripe_webhook_events.processed_at END) AS last_processed_at
            FROM stripe_webhook_events
            {$whereSql}
            GROUP BY stripe_webhook_events.organization_id, stripe_webhook_events.type, {$dateExpr}
        ";

        $rows = DB::select($sql, $bindings);
        if ($rows === []) {
            return 0;
        }

        $now = now();
        $insert = [];
        foreach ($rows as $row) {
            $orgId = $row->organization_id !== null ? (int) $row->organization_id : null;
            $scope = $this->summaryScope->organizationScope($orgId);
            $eventDate = $row->event_date;
            if ($eventDate instanceof Carbon || $eventDate instanceof CarbonImmutable) {
                $dateStr = $eventDate->format('Y-m-d');
            } else {
                $dateStr = (string) $eventDate;
            }

            $insert[] = [
                'organization_scope' => $scope,
                'organization_id' => $orgId,
                'provider' => $provider,
                'event_type' => (string) $row->type,
                'event_date' => $dateStr,
                'total_count' => (int) $row->total_count,
                'success_count' => (int) $row->success_count,
                'failure_count' => (int) $row->failure_count,
                'last_received_at' => $this->nullableTimestampString($row->last_received_at),
                'last_processed_at' => $this->nullableTimestampString($row->last_processed_at),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($insert, 100) as $chunk) {
            DB::table('webhook_event_daily_rollups')->insert($chunk);
        }

        return count($insert);
    }

    private function nullableTimestampString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse((string) $value)->format('Y-m-d H:i:s');
    }
}
