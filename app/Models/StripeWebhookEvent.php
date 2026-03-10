<?php

declare(strict_types=1);

namespace App\Models;

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

    protected $casts = [
        'processed_at' => 'datetime',
        'payload_json' => 'array',
    ];
}
