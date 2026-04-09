<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
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
 *
 * @method static Builder<Comment> forOrganization(int $organizationId)
 */
final class Comment extends Model
{
    protected $fillable = ['organization_id', 'user_id', 'body', 'commentable_type', 'commentable_id'];

    /**
     * @param  Builder<Comment>  $query
     * @return Builder<Comment>
     */
    public function scopeForOrganization(Builder $query, int $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }

    /**
     * @return BelongsTo<Organization, Comment>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return BelongsTo<User, Comment>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return MorphTo<Model, Comment>
     */
    public function commentable(): MorphTo
    {
        return $this->morphTo();
    }
}
