<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StripeWebhookEvent extends Model
{
    protected $table = 'stripe_webhook_events';

    protected $fillable = [
        'stripe_event_id',
        'type',
        'status',
        'notes',
        'processed_at',
        'payload_json',
        'organization_id',
    ];

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    /**
     * @param  Builder<StripeWebhookEvent>  $query
     * @return Builder<StripeWebhookEvent>
     */
    public function scopeForOrganization(Builder $query, int $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }

    /**
     * @param  Builder<StripeWebhookEvent>  $query
     * @param  list<int>  $organizationIds
     * @return Builder<StripeWebhookEvent>
     */
    public function scopeForOrganizations(Builder $query, array $organizationIds): Builder
    {
        return $query->whereIn('organization_id', $organizationIds);
    }

    protected $casts = [
        'processed_at' => 'datetime',
        'payload_json' => 'array',
    ];
}
