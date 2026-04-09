<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Non-authoritative daily rollup over stripe_webhook_events (calendar date in app timezone).
 * Rebuilt via WebhookDailyRollupService / webhooks:rebuild-daily-rollups.
 */
class WebhookEventDailyRollup extends Model
{
    protected $table = 'webhook_event_daily_rollups';

    protected $fillable = [
        'organization_scope',
        'organization_id',
        'provider',
        'event_type',
        'event_date',
        'total_count',
        'success_count',
        'failure_count',
        'last_received_at',
        'last_processed_at',
    ];

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    protected function casts(): array
    {
        return [
            'event_date' => 'date',
            'last_received_at' => 'datetime',
            'last_processed_at' => 'datetime',
        ];
    }
}
