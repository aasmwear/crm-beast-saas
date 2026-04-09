<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * App\Models\Task
 *
 * @property int $id
 * @property int $organization_id
 * @property int $project_id
 * @property string $title
 * @property string|null $description
 * @property array<int, int>|null $assignees
 * @property \Illuminate\Support\Carbon|null $due_date
 * @property string|null $priority
 * @property string|null $status
 * @property float|null $estimated_hours
 * @property float|null $logged_hours
 * @property array<int, mixed>|null $subtasks
 * @property array<int, mixed>|null $attachments
 * @property array<int, mixed>|null $comments
 * @property array<string, mixed>|null $submission
 * @property string|null $submission_note
 * @property array<int, mixed>|null $submission_files
 * @property string|null $review_status
 * @property int|null $reviewed_by_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read Organization $organization
 * @property-read Project $project
 *
 * @mixin \Eloquent
 */
class Task extends Model
{
    use HasFactory; // @phpstan-ignore-line
    use SoftDeletes;

    /**
     * The table associated with the model.
     */
    protected $table = 'tasks';

    /**
     * The attributes that are mass assignable.
     *
     * (No PHPDoc here so we don't override the parent Model::$fillable type.)
     *
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'project_id',
        'title',
        'description',
        'assignees',
        'due_date',
        'priority',
        'status',
        'estimated_hours',
        'logged_hours',
        'subtasks',
        'attachments',
        'comments',
        'submission',
        'submission_note',
        'submission_files',
        'review_status',
        'reviewed_by_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'assignees' => 'array',
        'due_date' => 'datetime',
        'estimated_hours' => 'float',
        'logged_hours' => 'float',
        'subtasks' => 'array',
        'attachments' => 'array',
        'comments' => 'array',
        'submission' => 'array',
        'submission_files' => 'array',
    ];

    /*
     |--------------------------------------------------------------------------
     | Relationships
     |--------------------------------------------------------------------------
     */

    /**
     * Get the organization that owns the task.
     */
    // @phpstan-ignore-next-line
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Get the project that this task belongs to.
     */
    // @phpstan-ignore-next-line
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Discussion comments (polymorphic). Distinct from the JSON `comments` column used for review feedback.
     */
    public function discussionComments(): MorphMany
    {
        $relation = $this->morphMany(Comment::class, 'commentable');

        if ($this->exists && $this->organization_id !== null) {
            $relation->where('comments.organization_id', $this->organization_id);
        }

        return $relation;
    }

    /**
     * Get all activities for this task.
     */
    public function activities(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(Activity::class, 'subject');
    }

    /**
     * Reviewer of the task (if any).
     */
    // @phpstan-ignore-next-line
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_id');
    }

    /*
     |--------------------------------------------------------------------------
     | Scopes
     |--------------------------------------------------------------------------
     */

    /**
     * Multi-tenant helper: restrict tasks to a given organization.
     *
     * @param  Builder<Task>  $q
     * @return Builder<Task>
     */
    public function scopeForOrg(Builder $q, int $orgId): Builder
    {
        return $q->where('organization_id', $orgId);
    }

    /**
     * Visibility rule for tasks.
     *
     * - Super Admins, Admins and Owners can see **all** tasks in the organization.
     * - Everyone else only sees tasks where:
     *   - they are in `assignees`, OR
     *   - they are `project_manager_id` on the related project, OR
     *   - they are fronter/closer/account manager on the related client.
     *
     * @param  Builder<Task>  $q
     * @return Builder<Task>
     */
    public function scopeVisibleTo(Builder $q, User $user): Builder
    {
        if ((bool) ($user->is_super_admin ?? false) || $user->hasAnyRole(['Owner', 'Admin'])) {
            return $q;
        }

        $uid = $user->id;

        return $q->where(function (Builder $builder) use ($uid): void {
            $builder
                ->whereJsonContains('assignees', $uid)
                ->orWhereHas('project', static function (Builder $projectQuery) use ($uid): void {
                    $projectQuery
                        ->where('project_manager_id', $uid)
                        ->orWhereHas('client', static function (Builder $clientQuery) use ($uid): void {
                            $clientQuery
                                ->where('fronter_id', $uid)
                                ->orWhere('closer_id', $uid)
                                ->orWhere('assigned_account_manager_id', $uid);
                        });
                });
        });
    }
}
