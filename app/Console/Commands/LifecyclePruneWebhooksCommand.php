<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\RetentionPolicy;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Prune stripe_webhook_events older than the configured retention window.
 *
 * Dry-run by default; use --execute to actually delete.
 */
final class LifecyclePruneWebhooksCommand extends Command
{
    protected $signature = 'lifecycle:prune-webhooks
                            {--execute : Actually delete rows (default: dry-run only)}';

    protected $description = 'Prune stripe_webhook_events older than retention window (dry-run by default, use --execute to delete)';

    public function handle(): int
    {
        $policy = $this->getPolicy();
        if ($policy === null) {
            return self::FAILURE;
        }

        if (! Schema::hasTable('stripe_webhook_events')) {
            $this->warn('Table stripe_webhook_events does not exist.');

            return self::SUCCESS;
        }

        $cutoff = $policy->cutoffDate();
        $col = $policy->createdAtColumn;

        $candidateCount = DB::table('stripe_webhook_events')
            ->where($col, '<', $cutoff)
            ->count();

        $this->info('Lifecycle Prune: stripe_webhook_events');
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

        $deleted = DB::table('stripe_webhook_events')
            ->where($col, '<', $cutoff)
            ->delete();

        $this->info('Deleted: ' . number_format($deleted) . ' rows.');
        $this->newLine();

        return self::SUCCESS;
    }

    private function getPolicy(): ?RetentionPolicy
    {
        /** @var array<string, array{category: string, retention_days: int, created_at_column: string}>|null $tables */
        $tables = config('lifecycle.tables');

        if ($tables === null || ! isset($tables['stripe_webhook_events'])) {
            $this->error('stripe_webhook_events not found in config/lifecycle.php.');

            return null;
        }

        return RetentionPolicy::fromConfig('stripe_webhook_events', $tables['stripe_webhook_events']);
    }
}
