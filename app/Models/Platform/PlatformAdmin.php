<?php

namespace App\Models\Platform;

use Database\Factories\Platform\PlatformAdminFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Platform Admin Model
 * 
 * Represents Super Admins and Support staff who manage the platform
 * and have access to all organizations.
 * 
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $password
 * @property string|null $avatar_path
 * @property string $role
 * @property bool $is_active
 * @property string|null $remember_token
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class PlatformAdmin extends Authenticatable
{
    use HasFactory, Notifiable;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'platform_admins';

    /**
     * The guard name used for authentication.
     *
     * @var string
     */
    protected string $guard_name = 'platform';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'avatar_path',
        'role',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    /**
     * Determine if the admin is a Super Admin.
     *
     * @return bool
     */
    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    /**
     * Determine if the admin is Support staff.
     *
     * @return bool
     */
    public function isSupport(): bool
    {
        return $this->role === 'support';
    }

    /**
     * Determine if the admin account is active.
     *
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->is_active;
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): PlatformAdminFactory
    {
        return PlatformAdminFactory::new();
    }
}
