<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $organization_id
 * @property int $project_id
 * @property string $title
 * @property string|null $status
 * @property int|null $order_index
 * @property array<int,int>|null $assignees
 * @property \Illuminate\Support\Carbon|null $due_date
 *
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
