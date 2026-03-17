<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\RetentionPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Read-only lifecycle report: shows row counts and retention status per table.
 *
 * Does NOT delete, archive, or modify any data.
 */
final class LifecycleReportCommand extends Command
{
    protected $signature = 'lifecycle:report';

    protected $description = 'Report data lifecycle status for hot-growth tables (read-only, no mutations)';

    public function handle(): int
    {
        $policies = RetentionPolicy::all();

        if ($policies === []) {
            $this->warn('No lifecycle policies configured in config/lifecycle.php.');

            return self::SUCCESS;
        }

        $now = CarbonImmutable::now();

        $this->info('Data Lifecycle Report — ' . $now->toDateTimeString());
        $this->newLine();

        $rows = [];

        foreach ($policies as $table => $policy) {
            if (! Schema::hasTable($table)) {
                $rows[] = [
                    $table,
                    $policy->category,
                    $policy->retentionDays . 'd',
                    '—',
                    '—',
                    'TABLE MISSING',
                ];

                continue;
            }

            $totalRows = DB::table($table)->count();

            $cutoff = $policy->cutoffDate($now);
            $col = $policy->createdAtColumn;

            $agedOutRows = DB::table($table)
                ->where($col, '<', $cutoff)
                ->count();

            $status = 'OK';
            if ($policy->hasLifecycleAction() && $agedOutRows > 0) {
                $pct = $totalRows > 0 ? round(($agedOutRows / $totalRows) * 100, 1) : 0;
                $action = $policy->isCold() ? 'PRUNE' : 'ARCHIVE';
                $status = "{$action} candidates: {$agedOutRows} ({$pct}%)";
            }

            $rows[] = [
                $table,
                $policy->category,
                $policy->retentionDays . 'd',
                number_format($totalRows),
                number_format($agedOutRows),
                $status,
            ];
        }

        $this->table(
            ['Table', 'Category', 'Window', 'Total Rows', 'Aged Out', 'Status'],
            $rows,
        );

        $this->newLine();
        $this->comment('This report is read-only. No data was modified.');

        return self::SUCCESS;
    }
}
