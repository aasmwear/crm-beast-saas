<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\RetentionPolicy;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Prune notification_events rows older than the configured retention window on created_at.
 *
 * Dry-run by default; use --execute to delete.
 */
final class LifecyclePruneNotificationEventsCommand extends Command
{
    protected $signature = 'lifecycle:prune-notification-events
                            {--execute : Actually delete rows (default: dry-run only)}
                            {--organization= : Only prune rows for this organization_id}';

    protected $description = 'Prune notification_events older than retention window (dry-run by default; use --execute to delete)';

    public function handle(): int
    {
        $policy = $this->getPolicy();
        if ($policy === null) {
            return self::FAILURE;
        }

        if (! Schema::hasTable('notification_events')) {
            $this->warn('Table notification_events does not exist.');

            return self::SUCCESS;
        }

        $cutoff = $policy->cutoffDate();
        $col = $policy->createdAtColumn;
        $orgOpt = $this->option('organization');
        $organizationId = ($orgOpt !== null && $orgOpt !== '') ? (int) $orgOpt : null;

        $candidateQuery = DB::table('notification_events')->where($col, '<', $cutoff);

        if ($organizationId !== null) {
            $candidateQuery->where('organization_id', $organizationId);
        }

        $candidateCount = (int) $candidateQuery->count();

        $this->info('Lifecycle Prune: notification_events');
        $this->line('Cutoff date: ' . $cutoff->toDateTimeString() . ' (older than ' . $policy->retentionDays . ' days on ' . $col . ')');
        if ($organizationId !== null) {
            $this->line('Scope: organization_id = ' . $organizationId);
        }
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

        $deleteQuery = DB::table('notification_events')->where($col, '<', $cutoff);

        if ($organizationId !== null) {
            $deleteQuery->where('organization_id', $organizationId);
        }

        $deleted = $deleteQuery->delete();

        $this->info('Deleted: ' . number_format($deleted) . ' rows.');
        $this->newLine();

        return self::SUCCESS;
    }

    private function getPolicy(): ?RetentionPolicy
    {
        /** @var array<string, array<string, mixed>>|null $tables */
        $tables = config('lifecycle.tables');

        if ($tables === null || ! isset($tables['notification_events'])) {
            $this->error('notification_events not found in config/lifecycle.php.');

            return null;
        }

        return RetentionPolicy::fromConfig('notification_events', $tables['notification_events']);
    }
}
