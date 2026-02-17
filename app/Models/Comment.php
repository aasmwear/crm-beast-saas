<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Polymorphic comment on Projects or Tasks.
 *
 * @property int $id
 * @property int $user_id
 * @property string $body
 * @property string $commentable_type
 * @property int $commentable_id
 */
final class Comment extends Model
{
    protected $fillable = ['user_id', 'body', 'commentable_type', 'commentable_id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function commentable(): MorphTo
    {
        return $this->morphTo();
    }
}
