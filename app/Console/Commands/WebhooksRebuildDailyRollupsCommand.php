<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Organization;
use App\Services\Webhooks\WebhookDailyRollupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

final class WebhooksRebuildDailyRollupsCommand extends Command
{
    protected $signature = 'webhooks:rebuild-daily-rollups
                            {--provider=stripe : Provider key stored on rollup rows}
                            {--organization= : Optional organization ID to rebuild one tenant bucket only}
                            {--days= : Limit rebuild to the last N calendar days in app timezone (inclusive of today)}';

    protected $description = 'Rebuild webhook_event_daily_rollups from stripe_webhook_events (read model; idempotent)';

    public function handle(WebhookDailyRollupService $service): int
    {
        if (! Schema::hasTable('webhook_event_daily_rollups') || ! Schema::hasTable('stripe_webhook_events')) {
            $this->error('Required tables are missing. Run migrations.');

            return self::FAILURE;
        }

        $provider = (string) $this->option('provider');
        $orgOpt = $this->option('organization');
        $daysOpt = $this->option('days');
        $days = ($daysOpt !== null && $daysOpt !== '') ? max(1, (int) $daysOpt) : null;

        if ($orgOpt !== null && $orgOpt !== '') {
            $org = Organization::find((int) $orgOpt);
            if ($org === null) {
                $this->error('Organization not found.');

                return self::FAILURE;
            }
            $orgId = (int) $org->id;
            $this->info("Webhook daily rollups: rebuild provider={$provider}, organization_id={$orgId}".($days !== null ? ", days={$days}" : ''));
            $n = $service->rebuildForOrganizationScope($orgId, $provider, $days);
            $this->info("Wrote {$n} rollup row(s).");

            return self::SUCCESS;
        }

        $this->info("Webhook daily rollups: full rebuild provider={$provider}".($days !== null ? ", days={$days}" : ''));
        $n = $service->rebuildAll($provider, $days);
        $this->info("Wrote {$n} rollup row(s).");

        return self::SUCCESS;
    }
}
