<?php

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
        return $this->morphMany(Comment::class, 'commentable');
    }
}
