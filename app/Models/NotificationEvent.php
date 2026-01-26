<?php

namespace App\Models;

use Database\Factories\NotificationEventFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $organization_id
 * @property string $type
 * @property string $entity
 * @property int $entity_id
 * @property array<string, mixed>|null $data
 * @property \Illuminate\Support\Carbon|null $read_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @method static Builder<NotificationEvent> query()
 * @method static NotificationEventFactory factory($count = null, $state = [])
 *
 * @mixin \Eloquent
 */
final class NotificationEvent extends Model
{
    /**
     * @use HasFactory<NotificationEventFactory>
     */
    use HasFactory;

    protected $table = 'notification_events';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'type',
        'entity',
        'entity_id',
        'data',
        'read_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'data' => 'array',
        'read_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<\App\Models\Organization, \App\Models\NotificationEvent>
     *
     * @phpstan-return BelongsTo<\App\Models\Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
