<?php

namespace App\Models;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Polymorphic comment on Projects or Tasks.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $user_id
 * @property string $body
 * @property string $commentable_type
 * @property int $commentable_id
 */
final class Comment extends Model
{
    protected $fillable = ['organization_id', 'user_id', 'body', 'commentable_type', 'commentable_id'];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function scopeForOrganization($query, int $organizationId)
    {
        return $query->where('organization_id', $organizationId);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function commentable(): MorphTo
    {
        return $this->morphTo();
    }
}
