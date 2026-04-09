<?php

declare(strict_types=1);

namespace App\Services\Lifecycle;

use App\Models\Client;
use App\Models\CustomField;
use App\Models\Task;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Hard-deletes soft-deleted rows past a grace window (deleted_at), with table-specific safety rules.
 */
final class SoftDeletedPurger
{
    /**
     * @return array{
     *     table: string,
     *     cutoff: string,
     *     grace_days: int,
     *     candidate_count: int,
     *     blocked_count: int,
     *     deleted: int,
     * }
     */
    public function run(
        string $table,
        int $graceDays,
        bool $execute,
        int $batchSize,
        ?int $organizationId,
    ): array {
        $batchSize = max(1, min(5000, $batchSize));
        $cutoff = CarbonImmutable::now()->subDays($graceDays);

        return match ($table) {
            'clients' => $this->runClients($cutoff, $execute, $batchSize, $organizationId, $graceDays),
            'tasks' => $this->runTasks($cutoff, $execute, $batchSize, $organizationId, $graceDays),
            'attendance' => $this->runAttendance($cutoff, $execute, $batchSize, $organizationId, $graceDays),
            default => throw new \InvalidArgumentException("Unsupported table: {$table}"),
        };
    }

    /**
     * @return array{table: string, cutoff: string, grace_days: int, candidate_count: int, blocked_count: int, deleted: int}
     */
    private function runClients(
        CarbonImmutable $cutoff,
        bool $execute,
        int $batchSize,
        ?int $organizationId,
        int $graceDays,
    ): array {
        $table = 'clients';

        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'deleted_at')) {
            return $this->emptyResult($table, $cutoff, $graceDays);
        }

        $base = Client::onlyTrashed()
            ->where('deleted_at', '<', $cutoff)
            ->when($organizationId !== null, fn ($q) => $q->where('organization_id', $organizationId));

        $blocked = (clone $base)->where(function ($q): void {
            $q->whereHas('projects')
                ->orWhereHas('invoices');
        })->count();

        $candidateCount = (clone $base)
            ->whereDoesntHave('projects')
            ->whereDoesntHave('invoices')
            ->count();

        $deleted = 0;

        if ($execute && $candidateCount > 0) {
            while (true) {
                /** @var list<int> $ids */
                $ids = (clone $base)
                    ->whereDoesntHave('projects')
                    ->whereDoesntHave('invoices')
                    ->orderBy('id')
                    ->limit($batchSize)
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->all();

                if ($ids === []) {
                    break;
                }

                DB::transaction(function () use ($ids, $organizationId): void {
                    $cfv = DB::table('custom_field_values')
                        ->where('entity_type', CustomField::ENTITY_CLIENT)
                        ->whereIn('entity_id', $ids);
                    if ($organizationId !== null) {
                        $cfv->where('organization_id', $organizationId);
                    }
                    $cfv->delete();

                    DB::table('clients')->whereIn('id', $ids)->delete();
                });

                $deleted += count($ids);
            }
        }

        return [
            'table' => $table,
            'cutoff' => $cutoff->toDateTimeString(),
            'grace_days' => $graceDays,
            'candidate_count' => $candidateCount,
            'blocked_count' => $blocked,
            'deleted' => $deleted,
        ];
    }

    /**
     * @return array{table: string, cutoff: string, grace_days: int, candidate_count: int, blocked_count: int, deleted: int}
     */
    private function runTasks(
        CarbonImmutable $cutoff,
        bool $execute,
        int $batchSize,
        ?int $organizationId,
        int $graceDays,
    ): array {
        $table = 'tasks';

        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'deleted_at')) {
            return $this->emptyResult($table, $cutoff, $graceDays);
        }

        $base = Task::onlyTrashed()
            ->where('deleted_at', '<', $cutoff)
            ->when($organizationId !== null, fn ($q) => $q->where('organization_id', $organizationId));

        $candidateCount = (clone $base)->count();
        $deleted = 0;
        $morphClass = (new Task)->getMorphClass();

        if ($execute && $candidateCount > 0) {
            while (true) {
                /** @var list<int> $ids */
                $ids = (clone $base)
                    ->orderBy('id')
                    ->limit($batchSize)
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->all();

                if ($ids === []) {
                    break;
                }

                DB::transaction(function () use ($ids, $morphClass, $organizationId): void {
                    $comments = DB::table('comments')
                        ->where('commentable_type', $morphClass)
                        ->whereIn('commentable_id', $ids);
                    if ($organizationId !== null) {
                        $comments->where('organization_id', $organizationId);
                    }
                    $comments->delete();

                    DB::table('activities')
                        ->where('subject_type', $morphClass)
                        ->whereIn('subject_id', $ids)
                        ->delete();

                    DB::table('tasks')->whereIn('id', $ids)->delete();
                });

                $deleted += count($ids);
            }
        }

        return [
            'table' => $table,
            'cutoff' => $cutoff->toDateTimeString(),
            'grace_days' => $graceDays,
            'candidate_count' => $candidateCount,
            'blocked_count' => 0,
            'deleted' => $deleted,
        ];
    }

    /**
     * @return array{table: string, cutoff: string, grace_days: int, candidate_count: int, blocked_count: int, deleted: int}
     */
    private function runAttendance(
        CarbonImmutable $cutoff,
        bool $execute,
        int $batchSize,
        ?int $organizationId,
        int $graceDays,
    ): array {
        $table = 'attendance';

        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'deleted_at')) {
            return $this->emptyResult($table, $cutoff, $graceDays);
        }

        $baseQuery = DB::table('attendance')
            ->whereNotNull('deleted_at')
            ->where('deleted_at', '<', $cutoff)
            ->when($organizationId !== null, fn ($q) => $q->where('organization_id', $organizationId));

        $candidateCount = (int) (clone $baseQuery)->count();
        $deleted = 0;

        if ($execute && $candidateCount > 0) {
            while (true) {
                /** @var list<int> $ids */
                $ids = (clone $baseQuery)
                    ->orderBy('id')
                    ->limit($batchSize)
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->all();

                if ($ids === []) {
                    break;
                }

                DB::table('attendance')->whereIn('id', $ids)->delete();
                $deleted += count($ids);
            }
        }

        return [
            'table' => $table,
            'cutoff' => $cutoff->toDateTimeString(),
            'grace_days' => $graceDays,
            'candidate_count' => $candidateCount,
            'blocked_count' => 0,
            'deleted' => $deleted,
        ];
    }

    /**
     * @return array{table: string, cutoff: string, grace_days: int, candidate_count: int, blocked_count: int, deleted: int}
     */
    private function emptyResult(string $table, CarbonImmutable $cutoff, int $graceDays): array
    {
        return [
            'table' => $table,
            'cutoff' => $cutoff->toDateTimeString(),
            'grace_days' => $graceDays,
            'candidate_count' => 0,
            'blocked_count' => 0,
            'deleted' => 0,
        ];
    }
}
