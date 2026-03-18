<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\RetentionPolicy;
use Illuminate\Bus\BatchRepository;
use Illuminate\Bus\DatabaseBatchRepository;
use Illuminate\Bus\PrunableBatchRepository;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Prune job_batches older than the configured retention window.
 *
 * Uses Laravel's batch repository prune under the hood. Dry-run by default;
 * use --execute to actually delete.
 */
final class LifecyclePruneBatchesCommand extends Command
{
    protected $signature = 'lifecycle:prune-batches
                            {--execute : Actually delete rows (default: dry-run only)}';

    protected $description = 'Prune job_batches older than retention window (dry-run by default, use --execute to delete)';

    public function handle(): int
    {
        $policy = $this->getPolicy();
        if ($policy === null) {
            return self::FAILURE;
        }

        if (! Schema::hasTable('job_batches')) {
            $this->warn('Table job_batches does not exist.');

            return self::SUCCESS;
        }

        $cutoff = $policy->cutoffDate();
        $hours = $policy->retentionDays * 24;
        $cutoffTimestamp = $cutoff->timestamp;

        $candidateCount = DB::table('job_batches')
            ->whereNotNull('finished_at')
            ->where('finished_at', '<', $cutoffTimestamp)
            ->count();

        $this->info('Lifecycle Prune: job_batches');
        $this->line('Cutoff date: ' . $cutoff->toDateTimeString() . ' (older than ' . $policy->retentionDays . ' days)');
        $this->line('Candidate rows (finished batches): ' . number_format($candidateCount));

        if ($candidateCount === 0) {
            $this->comment('No rows to prune.');
            $this->newLine();

            return self::SUCCESS;
        }

        $execute = (bool) $this->option('execute');

        if (! $execute) {
            $this->comment('[DRY-RUN] No rows deleted. Run with --execute to perform deletion.');
            $this->newLine();

            return self::SUCCESS;
        }

        $repository = $this->laravel[BatchRepository::class];
        if (! $repository instanceof PrunableBatchRepository) {
            $this->error('The configured batch repository does not support pruning.');

            return self::FAILURE;
        }

        $deleted = $repository->prune(Carbon::now()->subHours($hours));

        $this->info('Deleted: ' . number_format($deleted) . ' rows.');
        $this->newLine();

        return self::SUCCESS;
    }

    private function getPolicy(): ?RetentionPolicy
    {
        /** @var array<string, array{category: string, retention_days: int, created_at_column: string}>|null $tables */
        $tables = config('lifecycle.tables');

        if ($tables === null || ! isset($tables['job_batches'])) {
            $this->error('job_batches not found in config/lifecycle.php.');

            return null;
        }

        return RetentionPolicy::fromConfig('job_batches', $tables['job_batches']);
    }
}
