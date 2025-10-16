<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @method static \Illuminate\Database\Eloquent\Builder<\App\Models\Task> query()
 */
class Task extends Model
{
    protected $guarded = [];

    protected $casts = [
        'assignees' => 'array',
        'due_date' => 'datetime',
    ];

    /**
     * @return BelongsTo<\App\Models\Project, \App\Models\Task>
     */
    public function project(): BelongsTo
    {
        /** @var BelongsTo<\App\Models\Project, \App\Models\Task> $rel */
        $rel = $this->belongsTo(Project::class);

        return $rel;
    }

    /**
     * @return BelongsTo<\App\Models\Organization, \App\Models\Task>
     */
    public function organization(): BelongsTo
    {
        /** @var BelongsTo<\App\Models\Organization, \App\Models\Task> $rel */
        $rel = $this->belongsTo(Organization::class);

        return $rel;
    }
}
