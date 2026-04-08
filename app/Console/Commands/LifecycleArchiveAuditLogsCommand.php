<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Lifecycle\WarmTableArchiver;
use App\Services\RetentionPolicy;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * Archive audit_logs older than the configured retention window into audit_logs_archive.
 * Dry-run by default; use --execute to move rows in batches.
 */
final class LifecycleArchiveAuditLogsCommand extends Command
{
    protected $signature = 'lifecycle:archive-audit-logs
                            {--execute : Move eligible rows to archive (default: dry-run only)}
                            {--batch=500 : Maximum rows to process per batch}
                            {--organization= : Only archive rows for this organization_id}';

    protected $description = 'Archive aged audit_logs to audit_logs_archive (dry-run by default; use --execute)';

    public function handle(WarmTableArchiver $archiver): int
    {
        $policy = $this->loadPolicy('audit_logs');
        if ($policy === null) {
            return self::FAILURE;
        }

        if (! $policy->isWarm()) {
            $this->error('audit_logs must be category "warm" in config/lifecycle.php for archival.');

            return self::FAILURE;
        }

        if (! Schema::hasTable('audit_logs')) {
            $this->warn('Table audit_logs does not exist.');

            return self::SUCCESS;
        }

        if (! Schema::hasTable('audit_logs_archive')) {
            $this->warn('Table audit_logs_archive does not exist. Run migrations.');

            return self::FAILURE;
        }

        $execute = (bool) $this->option('execute');
        $batch = max(1, (int) $this->option('batch'));
        $orgOpt = $this->option('organization');
        $organizationId = ($orgOpt !== null && $orgOpt !== '') ? (int) $orgOpt : null;

        $this->info('Lifecycle Archive: audit_logs → audit_logs_archive');
        $cutoff = $policy->cutoffDate();
        $this->line('Cutoff: ' . $cutoff->toDateTimeString() . ' (strictly older than ' . $policy->retentionDays . ' days on ' . $policy->createdAtColumn . ')');
        if ($organizationId !== null) {
            $this->line('Scope: organization_id = ' . $organizationId);
        }

        $result = $archiver->run(
            'audit_logs',
            'audit_logs_archive',
            $policy,
            $execute,
            $batch,
            $organizationId,
        );

        $this->line('Candidate rows (hot, not in archive): ' . number_format($result['candidate_count']));

        if (! $execute) {
            if ($result['candidate_count'] === 0) {
                $this->comment('Nothing eligible to archive (dry-run).');
            } else {
                $this->comment('[DRY-RUN] No rows moved. Run with --execute to archive.');
            }

            return self::SUCCESS;
        }

        if ($result['archived_count'] === 0 && $result['reconciled_hot_only'] === 0 && $result['candidate_count'] === 0) {
            $this->comment('Nothing to archive.');

            return self::SUCCESS;
        }

        $this->info('Archived (moved): ' . number_format($result['archived_count']));
        if ($result['reconciled_hot_only'] > 0) {
            $this->comment('Reconciled (hot rows removed; id already in archive): ' . number_format($result['reconciled_hot_only']));
        }

        return self::SUCCESS;
    }

    private function loadPolicy(string $key): ?RetentionPolicy
    {
        /** @var array<string, array<string, mixed>>|null $tables */
        $tables = config('lifecycle.tables');
        if ($tables === null || ! isset($tables[$key])) {
            $this->error($key . ' not found in config/lifecycle.php.');

            return null;
        }

        return RetentionPolicy::fromConfig($key, $tables[$key]);
    }
}
