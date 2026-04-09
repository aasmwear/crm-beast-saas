<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Non-authoritative read model over stripe_webhook_events.
 * Rebuilt via WebhookSummaryService / webhooks:rebuild-summaries.
 */
class WebhookEventSummary extends Model
{
    protected $table = 'webhook_event_summaries';

    protected $fillable = [
        'organization_scope',
        'organization_id',
        'provider',
        'event_type',
        'total_count',
        'success_count',
        'failure_count',
        'last_received_at',
        'last_processed_at',
        'last_error_at',
        'last_error_message',
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
            'last_received_at' => 'datetime',
            'last_processed_at' => 'datetime',
            'last_error_at' => 'datetime',
        ];
    }
}
