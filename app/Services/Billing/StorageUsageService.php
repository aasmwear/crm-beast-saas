<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\Organization;
use Illuminate\Support\Facades\DB;

/**
 * Canonical storage usage read model for runtime billing enforcement hooks.
 *
 * Current implementation uses project file attachments (`project_files.size`)
 * scoped via `projects.organization_id`. This is intentionally incremental and
 * can be extended later when additional storage-backed modules are introduced.
 */
final class StorageUsageService
{
    private const BYTES_PER_GB = 1_073_741_824;

    public function currentUsageBytes(Organization $org): int
    {
        $bytes = DB::table('project_files')
            ->join('projects', 'projects.id', '=', 'project_files.project_id')
            ->where('projects.organization_id', (int) $org->id)
            ->sum('project_files.size');

        return (int) $bytes;
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
}
