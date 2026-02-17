<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Activity log entry for Projects, Tasks, Invoices.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $description
 * @property string $subject_type
 * @property int $subject_id
 * @property array|null $properties
 */
final class Activity extends Model
{
    protected $fillable = ['user_id', 'description', 'subject_type', 'subject_id', 'properties'];

    protected $casts = [
        'properties' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
