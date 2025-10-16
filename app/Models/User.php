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
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * @phpstan-use \Illuminate\Database\Eloquent\Factories\HasFactory<\Database\Factories\UserFactory>
 *
 * @property int $id
 * @property string $name
 * @property string $email
 */
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    protected string $guard_name = 'web';

    protected $fillable = [
        'name',
        'email',
        'password',
        'department_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

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
        $rel = $this->hasMany(Project::class, 'project_manager_id');

        return $rel;
    }

    protected static function newFactory(): \Database\Factories\UserFactory
    {
        return \Database\Factories\UserFactory::new();
    }
}
