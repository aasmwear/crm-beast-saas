<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\Organization;
use App\Models\OrganizationAddon;
use App\Models\Platform\OrganizationFeature;
use App\Support\PlanCatalog;

/**
 * Single source of truth for org entitlements.
 * Resolution order: plan defaults → org overrides (organization_features) → add-ons.
 * Add-on modes: augment (adds to base) | set (overrides with value_int; multiple set = highest wins).
 */
final class EntitlementsService
{
    /** @var array<int, array<string, bool|int>> per-request cache by organization_id */
    private static array $resolved = [];

    /**
     * Resolved entitlements map (booleans + numbers) for the org.
     *
     * @return array<string, bool|int>
     */
    public function forOrg(Organization $org): array
    {
        $id = (int) $org->getKey();
        if (isset(self::$resolved[$id])) {
            return self::$resolved[$id];
        }

        $planKey = $this->resolvePlanKey($org);
        $plan = PlanCatalog::get($planKey);
        $base = $plan !== null ? $plan['entitlements'] : PlanCatalog::get(PlanCatalog::defaultPlanKey())['entitlements'];

        $overrides = $this->orgOverrides($org);
        $merged = $this->mergeOverrides($base, $overrides);

        $addons = $this->orgAddons($org);
        $result = $this->applyAddons($merged, $addons);

        self::$resolved[$id] = $result;

        return $result;
    }

    public function enabled(string $key, Organization $org): bool
    {
        $entitlements = $this->forOrg($org);
        $val = $entitlements[$key] ?? null;

        if (is_bool($val)) {
            return $val;
        }
        if (is_numeric($val)) {
            return (int) $val > 0;
        }

        return false;
    }

    /**
     * @return int|float|null
     */
    public function value(string $key, Organization $org): int|float|null
    {
        $entitlements = $this->forOrg($org);
        $val = $entitlements[$key] ?? null;

        return is_numeric($val) ? (int) $val : null;
    }

    public function clearCache(?Organization $org = null): void
    {
        if ($org === null) {
            self::$resolved = [];

            return;
        }
        unset(self::$resolved[(int) $org->getKey()]);
    }

    private function resolvePlanKey(Organization $org): string
    {
        $sub = $org->billingSubscription;
        if ($sub !== null && $sub->plan_key !== '') {
            return PlanCatalog::isValidPlanKey($sub->plan_key) ? $sub->plan_key : PlanCatalog::defaultPlanKey();
        }

        $legacyPlan = $org->plan ?? null;
        if ($legacyPlan !== null && $legacyPlan !== '' && PlanCatalog::isValidPlanKey((string) $legacyPlan)) {
            return (string) $legacyPlan;
        }

        return PlanCatalog::defaultPlanKey();
    }

    /**
     * @return array<string, bool|int>
     */
    private function orgOverrides(Organization $org): array
    {
        $of = OrganizationFeature::query()->where('organization_id', $org->getKey())->first();
        if ($of === null || ! is_array($of->features)) {
            return [];
        }

        $out = [];
        foreach ($of->features as $k => $v) {
            if (is_bool($v) || is_int($v)) {
                $out[$k] = $v;
            } elseif (is_numeric($v)) {
                $out[$k] = (int) $v;
            }
        }

        return $out;
    }

    /**
     * @return array<int, array{key: string, mode: string, value: int}>
     */
    private function orgAddons(Organization $org): array
    {
        $rows = OrganizationAddon::query()
            ->where('organization_id', $org->getKey())
            ->active()
            ->get();

        $list = [];
        foreach ($rows as $addon) {
            $val = $addon->value_int ?? $addon->quantity;
            $mode = ($addon->mode === OrganizationAddon::MODE_SET) ? OrganizationAddon::MODE_SET : OrganizationAddon::MODE_AUGMENT;
            $list[] = ['key' => $addon->addon_key, 'mode' => $mode, 'value' => (int) $val];
        }

        return $list;
    }

    /**
     * @param  array<string, bool|int>  $base
     * @param  array<string, bool|int>  $overrides
     * @return array<string, bool|int>
     */
    private function mergeOverrides(array $base, array $overrides): array
    {
        foreach ($overrides as $k => $v) {
            $base[$k] = $v;
        }

        return $base;
    }

    /**
     * Apply add-ons: augment adds to base; set overrides with value_int (multiple set = highest wins).
     *
     * @param  array<string, bool|int>  $merged
     * @param  array<int, array{key: string, mode: string, value: int}>  $addons
     * @return array<string, bool|int>
     */
    private function applyAddons(array $merged, array $addons): array
    {
        $augmentByKey = [];
        $setByKey = [];

        foreach ($addons as $item) {
            $key = $item['key'];
            if ($item['mode'] === OrganizationAddon::MODE_SET) {
                $setByKey[$key] = max($setByKey[$key] ?? 0, $item['value']);
            } else {
                $augmentByKey[$key] = ($augmentByKey[$key] ?? 0) + $item['value'];
            }
        }

        foreach (array_keys($augmentByKey + $setByKey) as $key) {
            $current = $merged[$key] ?? 0;
            $base = is_numeric($current) ? (int) $current : 0;
            $withAugment = $base + ($augmentByKey[$key] ?? 0);

            if (isset($setByKey[$key])) {
                $merged[$key] = $setByKey[$key];
            } else {
                $merged[$key] = $withAugment;
            }
        }

        return $merged;
    }
}
