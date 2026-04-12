<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Organization;
use App\Services\Tenancy\TenantTierService;

/**
 * Decides whether snapshot-first reads (latest org_daily_metrics before live fallback)
 * apply to an organization. Billing and writes are unaffected.
 */
final class OrgSnapshotReadMode
{
    public static function shouldUseSnapshot(Organization $organization): bool
    {
        if (config('org_daily_metrics.snapshot_only_all_tenants', false)) {
            return true;
        }

        return in_array($organization->tier, [
            TenantTierService::TIER_LARGE,
            TenantTierService::TIER_ENTERPRISE,
        ], true);
    }
}
