<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\Comment;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait Commentable
{
    /**
     * Get all comments for this model.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany<\App\Models\Comment, $this>
     */
    public function comments(): MorphMany
    {
        $relation = $this->morphMany(Comment::class, 'commentable');

        // Persisted parents only: query stubs used for whereHas() would otherwise bind NULL org.
        if ($this->exists && $this->organization_id !== null) {
            $relation->where('comments.organization_id', $this->organization_id);
        }

        return $relation;
    }
}
