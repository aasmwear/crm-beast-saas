<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\RetentionPolicy;
use Illuminate\Console\Command;
use Illuminate\Queue\Failed\PrunableFailedJobProvider;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Prune failed_jobs older than the configured retention window.
 *
 * Uses Laravel's queue:prune-failed under the hood. Dry-run by default;
 * use --execute to actually delete.
 */
final class LifecyclePruneFailedCommand extends Command
{
    protected $signature = 'lifecycle:prune-failed
                            {--execute : Actually delete rows (default: dry-run only)}';

    protected $description = 'Prune failed_jobs older than retention window (dry-run by default, use --execute to delete)';

    public function handle(): int
    {
        $policy = $this->getPolicy();
        if ($policy === null) {
            return self::FAILURE;
        }

        if (! Schema::hasTable('failed_jobs')) {
            $this->warn('Table failed_jobs does not exist.');

            return self::SUCCESS;
        }

        $cutoff = $policy->cutoffDate();
        $hours = $policy->retentionDays * 24;

        $candidateCount = DB::table('failed_jobs')
            ->where('failed_at', '<', $cutoff)
            ->count();

        $this->info('Lifecycle Prune: failed_jobs');
        $this->line('Cutoff date: ' . $cutoff->toDateTimeString() . ' (older than ' . $policy->retentionDays . ' days)');
        $this->line('Candidate rows: ' . number_format($candidateCount));

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

        $failer = $this->laravel['queue.failer'];
        if (! $failer instanceof PrunableFailedJobProvider) {
            $this->error('The configured failed job driver does not support pruning.');

            return self::FAILURE;
        }

        $deleted = $failer->prune(Carbon::now()->subHours($hours));

        $this->info('Deleted: ' . number_format($deleted) . ' rows.');
        $this->newLine();

        return self::SUCCESS;
    }

    private function getPolicy(): ?RetentionPolicy
    {
        /** @var array<string, array{category: string, retention_days: int, created_at_column: string}>|null $tables */
        $tables = config('lifecycle.tables');

        if ($tables === null || ! isset($tables['failed_jobs'])) {
            $this->error('failed_jobs not found in config/lifecycle.php.');

            return null;
        }

        return RetentionPolicy::fromConfig('failed_jobs', $tables['failed_jobs']);
    }
}
