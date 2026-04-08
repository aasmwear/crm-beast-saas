<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\RetentionPolicy;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Prune read Laravel database notifications older than the configured retention window.
 *
 * Only rows with read_at set (user has read the notification) and created_at before the
 * cutoff are deleted; unread rows are never pruned. Dry-run by default; --execute deletes.
 */
final class LifecyclePruneNotificationsCommand extends Command
{
    protected $signature = 'lifecycle:prune-notifications
                            {--execute : Actually delete rows (default: dry-run only)}
                            {--organization= : Only prune rows with this organization_id (column match)}';

    protected $description = 'Prune read notifications past retention (dry-run by default; use --execute to delete)';

    public function handle(): int
    {
        $policy = $this->getPolicy();
        if ($policy === null) {
            return self::FAILURE;
        }

        if (! $policy->pruneRequiresReadAt) {
            $this->error('notifications policy must set prune_requires_read_at in config/lifecycle.php.');

            return self::FAILURE;
        }

        if (! Schema::hasTable('notifications')) {
            $this->warn('Table notifications does not exist.');

            return self::SUCCESS;
        }

        $cutoff = $policy->cutoffDate();
        $col = $policy->createdAtColumn;
        $orgOpt = $this->option('organization');
        $organizationId = ($orgOpt !== null && $orgOpt !== '') ? (int) $orgOpt : null;

        $candidateQuery = DB::table('notifications')
            ->whereNotNull('read_at')
            ->where($col, '<', $cutoff);

        if ($organizationId !== null) {
            $candidateQuery->where('organization_id', $organizationId);
        }

        $candidateCount = (int) $candidateQuery->count();

        $this->info('Lifecycle Prune: notifications (read rows only, by '.$col.')');
        $this->line('Cutoff: ' . $cutoff->toDateTimeString() . ' (created before this; read_at must be set)');
        $this->line('Retention: ' . $policy->retentionDays . ' days');
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

        $deleteQuery = DB::table('notifications')
            ->whereNotNull('read_at')
            ->where($col, '<', $cutoff);

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

        if ($tables === null || ! isset($tables['notifications'])) {
            $this->error('notifications not found in config/lifecycle.php.');

            return null;
        }

        return RetentionPolicy::fromConfig('notifications', $tables['notifications']);
    }
}
