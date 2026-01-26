<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory as EloquentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Project extends Model
{
    /**
     * @use \Illuminate\Database\Eloquent\Factories\HasFactory<\Database\Factories\ProjectFactory>
     *
     * @phpstan-use \Illuminate\Database\Eloquent\Factories\HasFactory<\Database\Factories\ProjectFactory>
     */
    use HasFactory;

    protected $fillable = [
        'organization_id', 'client_id', 'title', 'project_code', 'description',
        'project_manager_id', 'department_id', 'start_date', 'end_date',
        'status', 'budget', 'price', 'billable', 'google_business_profile_status',
        'google_business_profile_access_status', 'client_activation_status',
        'notes_by_cst', 'notes_by_sales', 'notes_by_tech', 'attachments', 'custom_fields',
    ];

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
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\Task, \App\Models\Project>
     *
     * @phpstan-return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\Task, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
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
