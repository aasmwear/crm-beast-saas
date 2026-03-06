<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\Organization;
use App\Support\PlanCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Count active seats for an organization.
 * Active seat = user in organization_user for that org, excluding portal users (user.client_id IS NOT NULL).
 */
final class SeatCounter
{
    public const SEAT_LIMIT_MESSAGE = 'Seat limit reached for your plan. Upgrade or add seats to invite more users.';

    /**
     * Assert the org can add another staff seat. Throws ValidationException (422) if at limit.
     *
     * @throws ValidationException
     */
    public function assertCanAddSeat(Organization $org): void
    {
        if ($this->canAddSeat($org)) {
            return;
        }

        throw ValidationException::withMessages([
            'seats' => [self::SEAT_LIMIT_MESSAGE],
        ]);
    }
    /**
     * Count active (tenant app) seats: members of organization_user for this org
     * where the user is not a portal-only user (client_id is null).
     */
    public function countActiveSeats(Organization $org): int
    {
        return (int) DB::table('organization_user')
            ->join('users', 'users.id', '=', 'organization_user.user_id')
            ->where('organization_user.organization_id', $org->getKey())
            ->whereNull('users.client_id')
            ->count();
    }

    /**
     * Whether the org can add another seat (under seat limit from subscription).
     */
    public function canAddSeat(Organization $org): bool
    {
        $sub = $org->billingSubscription;
        $planKey = $sub?->plan_key ?? null;
        if ($planKey === null || $planKey === '') {
            $legacyPlan = $org->plan ?? null;
            $planKey = ($legacyPlan !== null && $legacyPlan !== '' && PlanCatalog::isValidPlanKey((string) $legacyPlan))
                ? (string) $legacyPlan
                : PlanCatalog::defaultPlanKey();
        } elseif (! PlanCatalog::isValidPlanKey($planKey)) {
            $planKey = PlanCatalog::defaultPlanKey();
        }
        $plan = PlanCatalog::get($planKey);
        $included = $sub?->seats_included ?? $plan['seats_included'] ?? 5;
        $limit = $sub?->seat_limit;
        $current = $this->countActiveSeats($org);

        if ($limit !== null && $limit > 0) {
            return $current < $limit;
        }

        return $current < $included;
    }
}
