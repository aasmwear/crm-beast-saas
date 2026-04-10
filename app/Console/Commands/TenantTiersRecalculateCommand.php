<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Organization;
use App\Services\Tenancy\TenantTierService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

final class TenantTiersRecalculateCommand extends Command
{
    protected $signature = 'tenant-tiers:recalculate {--org= : Optional organization ID}';

    protected $description = 'Recalculate read-only organizations.tier from usage metrics (no billing impact)';

    public function handle(TenantTierService $tiers): int
    {
        if (! Schema::hasColumn('organizations', 'tier')) {
            $this->error('Column organizations.tier is missing. Run migrations.');

            return self::FAILURE;
        }

        $orgId = $this->option('org');
        if ($orgId !== null && $orgId !== '') {
            $org = Organization::find((int) $orgId);
            if ($org === null) {
                $this->error('Organization not found.');

                return self::FAILURE;
            }
            $tier = $tiers->recalculateTier($org);
            $this->info("Organization #{$org->id} tier = {$tier}");

            return self::SUCCESS;
        }

        $n = $tiers->recalculateAllOrganizations();
        $this->info("Recalculated tier for {$n} organization(s).");

        return self::SUCCESS;
    }
}
