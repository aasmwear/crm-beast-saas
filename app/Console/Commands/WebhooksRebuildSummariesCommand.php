<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Organization;
use App\Services\Webhooks\WebhookSummaryService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

final class WebhooksRebuildSummariesCommand extends Command
{
    protected $signature = 'webhooks:rebuild-summaries
                            {--provider=stripe : Provider key stored on summary rows}
                            {--organization= : Optional organization ID to rebuild one tenant bucket only (omit for full rebuild)}';

    protected $description = 'Rebuild webhook_event_summaries from stripe_webhook_events (read model; idempotent)';

    public function handle(WebhookSummaryService $service): int
    {
        if (! Schema::hasTable('webhook_event_summaries') || ! Schema::hasTable('stripe_webhook_events')) {
            $this->error('Required tables are missing. Run migrations.');

            return self::FAILURE;
        }

        $provider = (string) $this->option('provider');
        $orgOpt = $this->option('organization');

        if ($orgOpt !== null && $orgOpt !== '') {
            $org = Organization::find((int) $orgOpt);
            if ($org === null) {
                $this->error('Organization not found.');

                return self::FAILURE;
            }
            $orgId = (int) $org->id;
            $this->info("Webhook summaries: rebuild provider={$provider}, organization_id={$orgId}");
            $n = $service->rebuildForOrganizationScope($orgId, $provider);
            $this->info("Wrote {$n} summary row(s).");

            return self::SUCCESS;
        }

        $this->info("Webhook summaries: full rebuild provider={$provider}");
        $n = $service->rebuildAll($provider);
        $this->info("Wrote {$n} summary row(s).");

        return self::SUCCESS;
    }
}
