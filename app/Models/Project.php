<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory as EloquentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Models\Concerns\Commentable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Schema;

/**
 * Project model.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $client_id
 * @property string $title
 * @property string|null $project_code
 * @property string|null $description
 * @property int|null $project_manager_id
 * @property int|null $department_id
 * @property \Illuminate\Support\Carbon|null $start_date
 * @property \Illuminate\Support\Carbon|null $end_date
 * @property string $status
 * @property int|null $budget_cents
 * @property int|null $price_cents
 * @property float|null $budget
 * @property float|null $price
 * @property string $currency
 * @property bool $billable
 * @property string|null $gbp_status
 * @property string|null $notes_cst
 * @property string|null $notes_sales
 * @property string|null $notes_tech
 * @property array|null $attachments
 * @property array|null $custom_fields
 * @property int $tasks_count Denormalized: non-trashed tasks for this project.
 * @property int $open_tasks_count Denormalized: tasks not in completed status set.
 * @property int $completed_tasks_count Denormalized: tasks whose status counts as done/completed/closed/finished.
 * @property int $progress_percent Denormalized: 0–100 from completed vs total tasks.
 *
 * @method static Builder<Project> query()
 *
 * @mixin \Eloquent
 */
final class Project extends Model
{
    use Commentable;
    use HasFactory;

    protected $fillable = [
        'organization_id', 'client_id', 'title', 'project_code', 'description',
        'project_manager_id', 'department_id', 'start_date', 'end_date',
        'status', 'budget_cents', 'price_cents', 'budget', 'price', 'currency', 'billable', 'gbp_status',
        'notes_cst', 'notes_sales', 'notes_tech', 'attachments', 'custom_fields',
    ];

    protected $appends = ['budget', 'price'];

    protected $casts = [
        'billable' => 'boolean',
        'attachments' => 'array',
        'custom_fields' => 'array',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\Organization, \App\Models\Project>
     *
     * @phpstan-return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\Client, \App\Models\Project>
     *
     * @phpstan-return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\User, \App\Models\Project>
     *
     * @phpstan-return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\User, $this>
     */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'project_manager_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\Department, \App\Models\Project>
     *
     * @phpstan-return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Budget in decimal form (for display and form binding). Stored as budget_cents in DB when that column exists.
     */
    protected function budget(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::make(
            get: function (): ?float {
                if (\array_key_exists('budget_cents', $this->attributes) && $this->attributes['budget_cents'] !== null) {
                    return (float) ($this->attributes['budget_cents'] / 100);
                }
                if (\array_key_exists('budget', $this->attributes) && $this->attributes['budget'] !== null) {
                    return (float) $this->attributes['budget'];
                }

                return null;
            },
            set: function ($value): array {
                $num = $value !== null && $value !== '' ? (float) $value : null;
                if (Schema::hasColumn($this->getTable(), 'budget_cents')) {
                    return ['budget_cents' => $num !== null ? (int) round($num * 100) : null];
                }

                return ['budget' => $num];
            },
        );
    }

    /**
     * Price (billable amount) in decimal form. Stored as price_cents in DB when that column exists.
     */
    protected function price(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::make(
            get: function (): ?float {
                if (\array_key_exists('price_cents', $this->attributes) && $this->attributes['price_cents'] !== null) {
                    return (float) ($this->attributes['price_cents'] / 100);
                }
                if (\array_key_exists('price', $this->attributes) && $this->attributes['price'] !== null) {
                    return (float) $this->attributes['price'];
                }

                return null;
            },
            set: function ($value): array {
                $num = $value !== null && $value !== '' ? (float) $value : null;
                if (Schema::hasColumn($this->getTable(), 'price_cents')) {
                    return ['price_cents' => $num !== null ? (int) round($num * 100) : null];
                }

                return ['price' => $num];
            },
        );
    }

    /**
     * Team members assigned to the project (many-to-many).
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<\App\Models\User, \App\Models\Project>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_user');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\Task, \App\Models\Project>
     *
     * @phpstan-return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\Task, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\ProjectFile, \App\Models\Project>
     */
    public function files(): HasMany
    {
        return $this->hasMany(ProjectFile::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany<\App\Models\Activity, \App\Models\Project>
     */
    public function activities(): MorphMany
    {
        $relation = $this->morphMany(\App\Models\Activity::class, 'subject');
        if ($this->exists && $this->organization_id !== null) {
            $relation->where('activities.organization_id', $this->organization_id);
        }

        return $relation;
    }

    /**
     * @param  Builder<Project>  $q
     * @return Builder<Project>
     */
    public function scopeVisibleTo(Builder $q, User $user): Builder
    {
        if ((bool) ($user->is_super_admin ?? false) || $user->hasAnyRole(['Owner', 'Admin'])) {
            return $q;
        }

        $uid = $user->id;

        return $q->where(function (Builder $qq) use ($uid): void {
            $qq->where('project_manager_id', $uid)
                ->orWhereHas('tasks', static function (Builder $qt) use ($uid): void {
                    $qt->whereJsonContains('assignees', $uid);
                });
        });
    }

    /**
     * @return \Database\Factories\ProjectFactory
     */
    protected static function newFactory(): EloquentFactory
    {
        return \Database\Factories\ProjectFactory::new();
    }
}
