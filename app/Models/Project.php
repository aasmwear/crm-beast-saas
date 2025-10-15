<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @method static \Illuminate\Database\Eloquent\Builder<\App\Models\Project> query()
 */
class Project extends Model
{
    protected $guarded = [];

    /**
     * @return BelongsTo<\App\Models\Client, \App\Models\Project>
     */
    public function client(): BelongsTo
    {
        /** @var BelongsTo<\App\Models\Client, \App\Models\Project> $rel */
        $rel = $this->belongsTo(Client::class);

        return $rel;
    }

    /**
     * @return BelongsTo<\App\Models\Organization, \App\Models\Project>
     */
    public function organization(): BelongsTo
    {
        /** @var BelongsTo<\App\Models\Organization, \App\Models\Project> $rel */
        $rel = $this->belongsTo(Organization::class);

        return $rel;
    }

    /**
     * @return HasMany<\App\Models\Task, \App\Models\Project>
     */
    public function tasks(): HasMany
    {
        /** @var HasMany<\App\Models\Task, \App\Models\Project> $rel */
        $rel = $this->hasMany(Task::class);

        return $rel;
    }
}
