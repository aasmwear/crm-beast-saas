<?php

declare(strict_types=1);

namespace App\Services\Webhooks;

use App\Models\WebhookEventSummary;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Rebuilds webhook_event_summaries from stripe_webhook_events (read model only).
 * Idempotent: delete scoped rows + insert fresh aggregates from source table.
 */
final class WebhookSummaryService
{
    public const DEFAULT_PROVIDER = 'stripe';

    /**
     * Full rebuild for a provider: replaces all summary rows for that provider.
     *
     * @return int Number of summary rows written
     */
    public function rebuildAll(string $provider = self::DEFAULT_PROVIDER): int
    {
        return DB::transaction(function () use ($provider): int {
            WebhookEventSummary::query()->where('provider', $provider)->delete();

            return $this->insertAggregates($provider, null);
        });
    }

    /**
     * Rebuild summaries for a single tenant bucket (organization_id on events) or the unscoped bucket.
     *
     * @return int Number of summary rows written
     */
    public function rebuildForOrganizationScope(?int $organizationId, string $provider = self::DEFAULT_PROVIDER): int
    {
        return DB::transaction(function () use ($organizationId, $provider): int {
            $scope = $this->organizationScope($organizationId);
            WebhookEventSummary::query()
                ->where('provider', $provider)
                ->where('organization_scope', $scope)
                ->delete();

            return $this->insertAggregates($provider, $organizationId);
        });
    }

    public function organizationScope(?int $organizationId): string
    {
        return $organizationId === null ? 'unscoped' : (string) $organizationId;
    }

    /**
     * @return int Rows inserted
     */
    private function insertAggregates(string $provider, ?int $organizationId): int
    {
        $groups = $this->loadAggregatedGroups($organizationId);
        if ($groups->isEmpty()) {
            return 0;
        }

        $lastErrorNotes = $this->loadLastFailureNotesByGroup($organizationId);
        $now = now();

        $rows = [];
        foreach ($groups as $row) {
            $orgId = $row->organization_id !== null ? (int) $row->organization_id : null;
            $scope = $this->organizationScope($orgId);
            $key = $this->groupKey($orgId, (string) $row->type);

            $rows[] = [
                'organization_scope' => $scope,
                'organization_id' => $orgId,
                'provider' => $provider,
                'event_type' => (string) $row->type,
                'total_count' => (int) $row->total_count,
                'success_count' => (int) $row->success_count,
                'failure_count' => (int) $row->failure_count,
                'last_received_at' => $this->nullableTimestampString($row->last_received_at),
                'last_processed_at' => $this->nullableTimestampString($row->last_processed_at),
                'last_error_at' => $this->nullableTimestampString($row->last_error_at),
                'last_error_message' => $lastErrorNotes[$key] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($rows, 100) as $chunk) {
            DB::table('webhook_event_summaries')->insert($chunk);
        }

        return count($rows);
    }

    private function groupKey(?int $organizationId, string $eventType): string
    {
        return ($organizationId === null ? 'u' : (string) $organizationId)."\0".$eventType;
    }

    /**
     * @return \Illuminate\Support\Collection<int, object{
     *   organization_id: int|null,
     *   type: string,
     *   total_count: int|string,
     *   success_count: int|string,
     *   failure_count: int|string,
     *   last_received_at: string|null,
     *   last_processed_at: string|null,
     *   last_error_at: string|null
     * }>
     */
    private function loadAggregatedGroups(?int $organizationId)
    {
        $q = DB::table('stripe_webhook_events')
            ->select('organization_id', 'type')
            ->selectRaw('COUNT(*) as total_count')
            ->selectRaw("SUM(CASE WHEN status = 'processed' THEN 1 ELSE 0 END) as success_count")
            ->selectRaw("SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failure_count")
            ->selectRaw('MAX(created_at) as last_received_at')
            ->selectRaw("MAX(CASE WHEN status = 'processed' THEN processed_at END) as last_processed_at")
            ->selectRaw("MAX(CASE WHEN status = 'failed' THEN created_at END) as last_error_at")
            ->groupBy('organization_id', 'type');

        if ($organizationId !== null) {
            $q->where('organization_id', $organizationId);
        }

        return $q->get();
    }

    /**
     * @return array<string, string|null>
     */
    private function loadLastFailureNotesByGroup(?int $organizationId): array
    {
        if (DB::getDriverName() === 'pgsql') {
            return $this->loadLastFailureNotesByGroupPostgres($organizationId);
        }

        $q = DB::table('stripe_webhook_events')
            ->where('status', 'failed')
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if ($organizationId !== null) {
            $q->where('organization_id', $organizationId);
        }

        $map = [];
        foreach ($q->get(['organization_id', 'type', 'notes']) as $row) {
            $orgId = $row->organization_id !== null ? (int) $row->organization_id : null;
            $key = $this->groupKey($orgId, (string) $row->type);
            if (! array_key_exists($key, $map)) {
                $map[$key] = $row->notes !== null ? (string) $row->notes : null;
            }
        }

        return $map;
    }

    /**
     * One row per (organization_id, type) without scanning all failures into PHP memory.
     *
     * @return array<string, string|null>
     */
    private function loadLastFailureNotesByGroupPostgres(?int $organizationId): array
    {
        if ($organizationId !== null) {
            $rows = DB::select(
                'SELECT DISTINCT ON (type) type, notes, organization_id '
                .'FROM stripe_webhook_events '
                ."WHERE status = 'failed' AND organization_id = ? "
                .'ORDER BY type, created_at DESC, id DESC',
                [$organizationId],
            );
        } else {
            $rows = DB::select(
                'SELECT DISTINCT ON (organization_id, type) organization_id, type, notes '
                .'FROM stripe_webhook_events '
                ."WHERE status = 'failed' "
                .'ORDER BY organization_id NULLS FIRST, type, created_at DESC, id DESC',
            );
        }

        $map = [];
        foreach ($rows as $row) {
            $orgId = $row->organization_id !== null ? (int) $row->organization_id : null;
            $key = $this->groupKey($orgId, (string) $row->type);
            $map[$key] = $row->notes !== null ? (string) $row->notes : null;
        }

        return $map;
    }

    private function nullableTimestampString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse((string) $value)->format('Y-m-d H:i:s');
    }
}
