<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Plan definitions for billing: seats, default entitlements.
 * Enterprise can be overridden via config.
 *
 * @return array<string, array{seats_included: int, entitlements: array<string, bool|int>}>
 */
final class PlanCatalog
{
    public const PLAN_STARTER = 'starter';

    public const PLAN_PRO = 'pro';

    public const PLAN_ENTERPRISE = 'enterprise';

    /**
     * @return array<string, array{seats_included: int, entitlements: array<string, bool|int>}>
     */
    public static function plans(): array
    {
        $enterprise = config('billing.enterprise_entitlements', self::defaultEnterpriseEntitlements());

        return [
            self::PLAN_STARTER => [
                'seats_included' => 5,
                'entitlements' => [
                    'attendance' => true,
                    'sms' => false,
                    'api_access' => false,
                    'storage_gb' => 5,
                    'api_rpm' => 60,
                    'exports_per_day' => 5,
                ],
            ],
            self::PLAN_PRO => [
                'seats_included' => 25,
                'entitlements' => [
                    'attendance' => true,
                    'sms' => true,
                    'api_access' => true,
                    'storage_gb' => 50,
                    'api_rpm' => 300,
                    'exports_per_day' => 50,
                ],
            ],
            self::PLAN_ENTERPRISE => [
                'seats_included' => (int) config('billing.enterprise_seats', 500),
                'entitlements' => $enterprise,
            ],
        ];
    }

    /**
     * @return array<string, bool|int>
     */
    private static function defaultEnterpriseEntitlements(): array
    {
        return config('billing.enterprise_entitlements', [
            'attendance' => true,
            'sms' => true,
            'api_access' => true,
            'storage_gb' => 500,
            'api_rpm' => 2000,
            'exports_per_day' => 500,
        ]);
    }

    public static function planKeys(): array
    {
        return [self::PLAN_STARTER, self::PLAN_PRO, self::PLAN_ENTERPRISE];
    }

    public static function isValidPlanKey(string $key): bool
    {
        return in_array($key, self::planKeys(), true);
    }

    /**
     * Default plan when org has no subscription record.
     */
    public static function defaultPlanKey(): string
    {
        return self::PLAN_STARTER;
    }

    /**
     * @return array{seats_included: int, entitlements: array<string, bool|int>}|null
     */
    public static function get(string $planKey): ?array
    {
        $plans = self::plans();

        return $plans[$planKey] ?? null;
    }
}
