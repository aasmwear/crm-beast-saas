<?php

namespace App\Models;

use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @phpstan-use \Illuminate\Database\Eloquent\Factories\HasFactory<OrganizationFactory>
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property array<string, mixed>|null $settings
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @method static OrganizationFactory factory($count = null, $state = [])
 */
class Organization extends Model
{
    /** @use HasFactory<OrganizationFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [ // <-- ADD THIS
        'name',            // <-- ADD THIS
        'slug',            // <-- ADD THIS
        'plan',
        'settings',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'settings' => 'array', // <-- Casts the array to JSON string for the DB
    ];

    /**
     * Get the route key for the model.
     *
     * @return string
     */
    public function getRouteKeyName()
    {
        return 'slug';
    }

    /**
     * @return HasMany<\App\Models\Client, \App\Models\Organization>
     */
    public function clients(): HasMany
    {
        /** @var HasMany<\App\Models\Client, \App\Models\Organization> $rel */
        $rel = $this->hasMany(Client::class);

        return $rel;
    }

    /**
     * @return HasMany<\App\Models\Project, \App\Models\Organization>
     */
    public function projects(): HasMany
    {
        /** @var HasMany<\App\Models\Project, \App\Models\Organization> $rel */
        $rel = $this->hasMany(Project::class);

        return $rel;
    }

    /**
     * @return BelongsToMany<\App\Models\User, \App\Models\Organization, \Illuminate\Database\Eloquent\Relations\Pivot, 'pivot'>
     */
    public function users(): BelongsToMany
    {
        /** @var BelongsToMany<\App\Models\User, \App\Models\Organization, \Illuminate\Database\Eloquent\Relations\Pivot, 'pivot'> $rel */
        $rel = $this->belongsToMany(User::class)->withTimestamps();

        return $rel;
    }

    protected static function newFactory(): OrganizationFactory
    {
        return OrganizationFactory::new();
    }
}
