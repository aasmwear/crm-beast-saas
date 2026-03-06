<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\Organization;
use Illuminate\Support\Facades\Cache;

/**
 * Minimal daily export limit hook based on canonical entitlements.
 * This intentionally avoids a broad quota engine in this PR.
 */
final class DailyExportLimitService
{
    public function limitPerDay(Organization $org): ?int
    {
        $value = app(EntitlementsService::class)->value('exports_per_day', $org);
        if (! is_numeric($value)) {
            return null;
        }

        return max(0, (int) $value);
    }

    public function currentCount(Organization $org): int
    {
        return (int) Cache::get($this->cacheKey($org), 0);
    }

    public function allowAndRecord(Organization $org): bool
    {
        $limit = $this->limitPerDay($org);
        if ($limit === null) {
            return true;
        }

        if ($this->currentCount($org) >= $limit) {
            return false;
        }

        Cache::add($this->cacheKey($org), 0, now()->endOfDay());
        Cache::increment($this->cacheKey($org));

        return true;
    }

    private function cacheKey(Organization $org): string
    {
        return sprintf(
            'billing:exports:org:%d:day:%s',
            (int) $org->id,
            now()->format('Y-m-d')
        );
    }
}
