<?php

declare(strict_types=1);

namespace App\Services\Lifecycle;

use App\Services\RetentionPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Moves aged-out rows from a warm lifecycle table into its archive twin.
 * Idempotent: skips rows already present in archive; can delete hot rows that were archived in a partial run.
 */
final class WarmTableArchiver
{
    /**
     * @return array{candidate_count: int, archived_count: int, reconciled_hot_only: int}
     */
    public function run(
        string $sourceTable,
        string $archiveTable,
        RetentionPolicy $policy,
        bool $execute,
        int $batchSize,
        ?int $organizationId = null,
    ): array {
        $batchSize = max(1, min(5000, $batchSize));
        $cutoff = $policy->cutoffDate();
        $ageCol = $policy->createdAtColumn;
        $archivedAt = CarbonImmutable::now()->toDateTimeString();

        $candidateCount = $this->countCandidates($sourceTable, $archiveTable, $ageCol, $cutoff, $organizationId);

        if (! $execute) {
            return [
                'candidate_count' => $candidateCount,
                'archived_count' => 0,
                'reconciled_hot_only' => 0,
            ];
        }

        $archivedCount = 0;
        $reconciledCount = 0;

        // Hot rows whose primary key already exists in archive (e.g. partial run or manual duplicate).
        $reconciledCount += $this->deleteHotRowsAlreadyArchived($sourceTable, $archiveTable, $organizationId);

        if ($candidateCount === 0) {
            return [
                'candidate_count' => $candidateCount,
                'archived_count' => 0,
                'reconciled_hot_only' => $reconciledCount,
            ];
        }

        while (true) {
            $ids = $this->nextCandidateIds($sourceTable, $archiveTable, $ageCol, $cutoff, $batchSize, $organizationId);
            if ($ids === []) {
                break;
            }

            $batchResult = DB::transaction(function () use (
                $sourceTable,
                $archiveTable,
                $ids,
                $archivedAt,
            ): array {
                $archived = 0;
                $reconciled = 0;

                $rows = DB::table($sourceTable)->whereIn('id', $ids)->orderBy('id')->get();
                foreach ($rows as $row) {
                    $id = (int) $row->id;
                    $existsInArchive = DB::table($archiveTable)->where('id', $id)->exists();
                    if ($existsInArchive) {
                        DB::table($sourceTable)->where('id', $id)->delete();
                        $reconciled++;

                        continue;
                    }

                    $payload = [
                        'id' => $id,
                        'organization_id' => (int) $row->organization_id,
                        'created_at' => $row->created_at,
                        'updated_at' => $row->updated_at,
                        'archived_at' => $archivedAt,
                    ];

                    foreach ($this->extraColumnsForRow($sourceTable, $row) as $key => $value) {
                        $payload[$key] = $value;
                    }

                    DB::table($archiveTable)->insert($payload);
                    DB::table($sourceTable)->where('id', $id)->delete();
                    $archived++;
                }

                return ['archived' => $archived, 'reconciled' => $reconciled];
            });

            $archivedCount += $batchResult['archived'];
            $reconciledCount += $batchResult['reconciled'];
        }

        return [
            'candidate_count' => $candidateCount,
            'archived_count' => $archivedCount,
            'reconciled_hot_only' => $reconciledCount,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function extraColumnsForRow(string $sourceTable, object $row): array
    {
        if ($sourceTable === 'audit_logs') {
            return [
                'actor_id' => $row->actor_id !== null ? (int) $row->actor_id : null,
                'action' => (string) $row->action,
                'entity' => (string) $row->entity,
                'entity_id' => (int) $row->entity_id,
                'changes' => $row->changes,
            ];
        }

        if ($sourceTable === 'activities') {
            return [
                'user_id' => $row->user_id !== null ? (int) $row->user_id : null,
                'description' => (string) $row->description,
                'subject_type' => (string) $row->subject_type,
                'subject_id' => (int) $row->subject_id,
                'properties' => $row->properties,
            ];
        }

        return [];
    }

    private function countCandidates(
        string $sourceTable,
        string $archiveTable,
        string $ageCol,
        CarbonImmutable $cutoff,
        ?int $organizationId,
    ): int {
        $q = DB::table($sourceTable . ' as s')
            ->where('s.' . $ageCol, '<', $cutoff)
            ->whereNotExists(function ($sub) use ($archiveTable) {
                $sub->select(DB::raw('1'))
                    ->from($archiveTable . ' as a')
                    ->whereColumn('a.id', 's.id');
            });

        if ($organizationId !== null) {
            $q->where('s.organization_id', $organizationId);
        }

        return (int) $q->count();
    }

    /**
     * @return list<int>
     */
    private function nextCandidateIds(
        string $sourceTable,
        string $archiveTable,
        string $ageCol,
        CarbonImmutable $cutoff,
        int $batchSize,
        ?int $organizationId,
    ): array {
        $q = DB::table($sourceTable . ' as s')
            ->where('s.' . $ageCol, '<', $cutoff)
            ->whereNotExists(function ($sub) use ($archiveTable) {
                $sub->select(DB::raw('1'))
                    ->from($archiveTable . ' as a')
                    ->whereColumn('a.id', 's.id');
            })
            ->orderBy('s.id')
            ->limit($batchSize);

        if ($organizationId !== null) {
            $q->where('s.organization_id', $organizationId);
        }

        return $q->pluck('s.id')->map(fn ($id) => (int) $id)->all();
    }

    private function deleteHotRowsAlreadyArchived(
        string $sourceTable,
        string $archiveTable,
        ?int $organizationId,
    ): int {
        $q = DB::table($sourceTable)
            ->whereIn('id', DB::table($archiveTable)->select('id'));

        if ($organizationId !== null) {
            $q->where('organization_id', $organizationId);
        }

        return (int) $q->delete();
    }
}
