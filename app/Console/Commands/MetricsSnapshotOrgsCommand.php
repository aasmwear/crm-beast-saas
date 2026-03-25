<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\OrgMetricsSnapshotService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Generate daily org metrics snapshots.
 *
 * Defaults to yesterday (the last fully completed day).
 * Safe to re-run: uses upsert semantics.
 */
final class MetricsSnapshotOrgsCommand extends Command
{
    protected $signature = 'metrics:snapshot-orgs
                            {--date= : Date to snapshot (YYYY-MM-DD, defaults to yesterday)}
                            {--org= : Optional organization ID to snapshot a single org}';

    protected $description = 'Generate daily org metrics snapshot (idempotent upsert)';

    public function handle(OrgMetricsSnapshotService $service): int
    {
        $dateStr = $this->option('date');
        $date = $dateStr
            ? CarbonImmutable::parse($dateStr)->startOfDay()
            : CarbonImmutable::yesterday()->startOfDay();

        $orgId = $this->option('org');

        $this->info('Metrics Snapshot: org_daily_metrics');
        $this->line('Date: ' . $date->toDateString());

        if ($orgId) {
            $org = \App\Models\Organization::find((int) $orgId);
            if (! $org) {
                $this->error("Organization {$orgId} not found.");

                return self::FAILURE;
            }

            $service->snapshotOrg($org, $date);
            $this->info('Snapshot created for org #' . $orgId . ' (' . $org->name . ').');

            return self::SUCCESS;
        }

        $count = $service->snapshotAllOrgs($date);
        $this->info("Snapshot created for {$count} organizations.");

        return self::SUCCESS;
    }
}
