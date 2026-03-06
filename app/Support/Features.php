<?php

namespace App\Support;

use App\Models\Organization;
use App\Models\Platform\OrganizationFeature;

class Features
{
    /**
     * Check if a boolean feature is enabled for the org.
     * When org is provided, uses organization_features table (DB-backed).
     * When org is null, falls back to config (platform/global context).
     */
    public static function enabled(string $key, Organization|int|null $org = null): bool
    {
        if ($org === null) {
            return (bool) config("features.{$key}", false);
        }

        $of = self::resolveOrganizationFeature($org);

        $val = $of->features[$key] ?? null;

        if (is_bool($val)) {
            return $val;
        }

        if (is_numeric($val)) {
            return (int) $val > 0;
        }

        return (bool) (config("features.{$key}", false));
    }

    /**
     * Get numeric/config value (e.g. storage_gb).
     * Returns null if key is boolean-only or not set.
     */
    public static function value(string $key, Organization|int|null $org = null): int|float|null
    {
        if ($org === null) {
            $cfg = config("features.{$key}");
            return is_numeric($cfg) ? (int) $cfg : null;
        }

        $of = self::resolveOrganizationFeature($org);
        $val = $of->features[$key] ?? null;

        return is_numeric($val) ? (int) $val : null;
    }

    /**
     * Resolve or create organization_features row for the given org.
     */
    private static function resolveOrganizationFeature(Organization|int $org): OrganizationFeature
    {
        $orgId = $org instanceof Organization ? (int) $org->id : (int) $org;

        $of = OrganizationFeature::query()->where('organization_id', $orgId)->first();

        if ($of !== null) {
            return $of;
        }

        return OrganizationFeature::query()->create([
            'organization_id' => $orgId,
            'features' => OrganizationFeature::DEFAULT_FEATURES,
            'subscription_status' => 'active',
        ]);
    }
}
