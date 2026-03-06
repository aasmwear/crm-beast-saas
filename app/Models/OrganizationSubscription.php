<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Subscription basics per organization (plan, status, trial, period).
 * Stripe fields can be added in a later PR.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $plan_key
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $trial_ends_at
 * @property \Illuminate\Support\Carbon|null $current_period_ends_at
 * @property int $seats_included
 * @property int|null $seat_limit
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class OrganizationSubscription extends Model
{
    public const STATUS_TRIALING = 'trialing';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_PAST_DUE = 'past_due';

    public const STATUS_CANCELED = 'canceled';

    protected $table = 'organization_subscriptions';

    protected $fillable = [
        'organization_id',
        'plan_key',
        'status',
        'trial_ends_at',
        'current_period_ends_at',
        'seats_included',
        'seat_limit',
    ];

    protected $casts = [
        'trial_ends_at' => 'datetime',
        'current_period_ends_at' => 'datetime',
        'seats_included' => 'integer',
        'seat_limit' => 'integer',
    ];

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function isActive(): bool
    {
        return in_array($this->status, [self::STATUS_TRIALING, self::STATUS_ACTIVE], true);
    }
}
