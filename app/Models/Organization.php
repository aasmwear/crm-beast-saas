<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @phpstan-use \Illuminate\Database\Eloquent\Factories\HasFactory<\Database\Factories\OrganizationFactory>
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 */
class Organization extends Model
{
    /** @use HasFactory<\Database\Factories\OrganizationFactory> */
    use HasFactory;

    protected $guarded = [];

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

    protected static function newFactory(): \Database\Factories\OrganizationFactory
    {
        return \Database\Factories\OrganizationFactory::new();
    }
}
