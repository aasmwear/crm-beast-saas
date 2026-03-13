<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\Organization;
use Illuminate\Support\Facades\DB;

/**
 * Canonical storage usage read model for runtime billing enforcement hooks.
 *
 * Current implementation uses project file attachments (`project_files.size`)
 * scoped directly via `project_files.organization_id`. This is intentionally incremental and
 * can be extended later when additional storage-backed modules are introduced.
 */
final class StorageUsageService
{
    private const BYTES_PER_GB = 1_073_741_824;

    /** @var array<int, int> Per-request cache: org_id => bytes */
    private static array $usageCache = [];

    public function currentUsageBytes(Organization $org): int
    {
        $key = (int) $org->id;
        if (array_key_exists($key, self::$usageCache)) {
            return self::$usageCache[$key];
        }

        $bytes = DB::table('project_files')
            ->where('project_files.organization_id', $key)
            ->sum('project_files.size');

        self::$usageCache[$key] = (int) $bytes;

        return self::$usageCache[$key];
    }

    public function currentUsageGb(Organization $org): float
    {
        return $this->currentUsageBytes($org) / self::BYTES_PER_GB;
    }

    public function limitGb(Organization $org): int|float
    {
        $value = app(EntitlementsService::class)->value('storage_gb', $org);

        return is_numeric($value) ? (int) $value : 0;
    }

    public function isOverLimit(Organization $org): bool
    {
        $limitGb = (float) $this->limitGb($org);
        if ($limitGb <= 0) {
            return false;
        }

        return $this->currentUsageBytes($org) > (int) round($limitGb * self::BYTES_PER_GB);
    }

    /**
     * Whether adding the given number of bytes would exceed the org's storage limit.
     * Used for pre-upload validation; limit of 0 means unlimited (returns false).
     */
    public function wouldExceedLimit(Organization $org, int $additionalBytes): bool
    {
        $limitGb = (float) $this->limitGb($org);
        if ($limitGb <= 0) {
            return false;
        }

        $limitBytes = (int) round($limitGb * self::BYTES_PER_GB);
        $projectedUsage = $this->currentUsageBytes($org) + $additionalBytes;

        return $projectedUsage > $limitBytes;
    }
}
