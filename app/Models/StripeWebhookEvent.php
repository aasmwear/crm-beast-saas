<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
    ];

    protected $casts = [
        'processed_at' => 'datetime',
        'payload_json' => 'array',
    ];
}
