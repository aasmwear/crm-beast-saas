<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Lifecycle\SoftDeletedPurger;
use Illuminate\Console\Command;

/**
 * Hard-delete soft-deleted rows past the configured grace window (deleted_at).
 *
 * Dry-run by default; use --execute to delete. Not all trashed rows qualify (see clients).
 */
final class LifecyclePurgeSoftDeletedCommand extends Command
{
    protected $signature = 'lifecycle:purge-soft-deleted
                            {--execute : Actually delete rows (default: dry-run only)}
                            {--table= : One of: clients, tasks, attendance, all (default: all)}
                            {--organization= : Limit to organization_id where tables support it}
                            {--batch=500 : Max rows per batch loop}';

    protected $description = 'Hard-delete aged soft-deleted rows (dry-run by default; use --execute)';

    public function handle(SoftDeletedPurger $purger): int
    {
        /** @var array<string, array{grace_days?: int, org_scoped?: bool, description?: string}> $purgeConfig */
        $purgeConfig = config('lifecycle.soft_deleted_purge', []);

        if ($purgeConfig === []) {
            $this->error('config/lifecycle.php soft_deleted_purge is empty or missing.');

            return self::FAILURE;
        }

        $tableOpt = $this->option('table');
        $tableArg = is_string($tableOpt) && $tableOpt !== '' ? strtolower($tableOpt) : 'all';

        $tables = $tableArg === 'all'
            ? array_keys($purgeConfig)
            : [$tableArg];

        $allowed = array_keys($purgeConfig);
        foreach ($tables as $t) {
            if (! in_array($t, $allowed, true)) {
                $this->error("Invalid --table={$t}. Allowed: " . implode(', ', $allowed) . ', all');

                return self::FAILURE;
            }
        }

        $orgOpt = $this->option('organization');
        $organizationId = ($orgOpt !== null && $orgOpt !== '') ? (int) $orgOpt : null;

        $batch = max(1, (int) $this->option('batch'));
        $execute = (bool) $this->option('execute');

        $this->info('Lifecycle: purge soft-deleted rows');
        $this->line('Mode: ' . ($execute ? 'EXECUTE (destructive)' : 'DRY-RUN'));
        if ($organizationId !== null) {
            $this->line('Scope: organization_id = ' . $organizationId);
        }
        $this->newLine();

        // Process lower-risk tables before clients.
        /** @var array<string, int> $order */
        $order = ['attendance' => 0, 'tasks' => 1, 'clients' => 2];
        usort($tables, static fn (string $a, string $b) => ($order[$a] ?? 99) <=> ($order[$b] ?? 99));

        foreach ($tables as $table) {
            $entry = $purgeConfig[$table];
            $graceDays = (int) ($entry['grace_days'] ?? 30);

            $result = $purger->run($table, $graceDays, $execute, $batch, $organizationId);

            $this->line("── Table: {$result['table']} (grace {$result['grace_days']}d) ──");
            $this->line('Cutoff (deleted_at before): ' . $result['cutoff']);
            $this->line('Eligible to purge: ' . number_format($result['candidate_count']));
            if ($result['blocked_count'] > 0) {
                $this->line('Blocked (trashed but has projects/invoices): ' . number_format($result['blocked_count']));
            }
            if (! $execute) {
                $this->comment('[DRY-RUN] Deleted: 0. Run with --execute to hard-delete eligible rows.');
            } else {
                $this->info('Deleted: ' . number_format($result['deleted']));
            }
            $this->newLine();
        }

        if (! $execute) {
            $this->comment('No rows were modified. Use --execute after review.');
        }

        return self::SUCCESS;
    }
}
