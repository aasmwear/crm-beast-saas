<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Cashier\Billable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * @phpstan-use \Illuminate\Database\Eloquent\Factories\HasFactory<\Database\Factories\UserFactory>
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property int|null $active_organization_id
 * @property bool $is_super_admin
 * @property array<string, mixed>|null $notification_prefs
 * @property \App\Models\Organization $activeOrganization
 */
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use Billable, HasApiTokens, HasFactory, HasRoles, Notifiable;

    protected string $guard_name = 'web';

    protected $fillable = [
        'name',
        'email',
        'password',
        'department_id',
        'active_organization_id',
        'notification_prefs',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'notification_prefs' => 'array',
        'is_super_admin' => 'boolean',
    ];

    // --------------------------------------------------------------------------
    // RELATIONSHIPS
    // --------------------------------------------------------------------------

    /**
     * @return BelongsTo<\App\Models\Organization, \App\Models\User>
     */
    public function activeOrganization(): BelongsTo
    {
        /** @var BelongsTo<\App\Models\Organization, \App\Models\User> $rel */
        $rel = $this->belongsTo(Organization::class, 'active_organization_id');

        return $rel;
    }

    /**
     * @return BelongsToMany<\App\Models\Organization, \App\Models\User, Pivot, 'pivot'>
     */
    public function organizations(): BelongsToMany
    {
        /** @var BelongsToMany<\App\Models\Organization, \App\Models\User, Pivot, 'pivot'> $rel */
        $rel = $this->belongsToMany(Organization::class, 'organization_user')->withTimestamps();

        return $rel;
    }

    /**
     * @return BelongsTo<\App\Models\Organization, \App\Models\User>
     */
    public function organization(): BelongsTo
    {
        /** @var BelongsTo<\App\Models\Organization, \App\Models\User> $rel */
        $rel = $this->belongsTo(Organization::class, 'active_organization_id');

        return $rel;
    }

    /**
     * @return BelongsTo<\App\Models\Department, \App\Models\User>
     */
    public function department(): BelongsTo
    {
        /** @var BelongsTo<\App\Models\Department, \App\Models\User> $rel */
        $rel = $this->belongsTo(Department::class);

        return $rel;
    }

    /**
     * @return HasMany<\App\Models\Project, \App\Models\User>
     */
    public function projects(): HasMany
    {
        /** @var HasMany<\App\Models\Project, \App\Models\User> $rel */
        $rel = $this->hasMany(Project::class);

        return $rel;
    }

    /**
     * @return HasMany<\App\Models\Task, \App\Models\User>
     */
    public function tasks(): HasMany
    {
        /** @var HasMany<\App\Models\Task, \App\Models\User> $rel */
        $rel = $this->hasMany(Task::class);

        return $rel;
    }
}
