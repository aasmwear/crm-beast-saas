<?php

namespace App\Models;

use App\Models\Platform\OrganizationFeature;
use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Laravel\Cashier\Billable;

/**
 * @phpstan-use \Illuminate\Database\Eloquent\Factories\HasFactory<OrganizationFactory>
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $logo_path
 * @property string|null $stripe_id
 * @property string|null $pm_type
 * @property string|null $pm_last_four
 * @property \Illuminate\Support\Carbon|null $trial_ends_at
 * @property array<string, mixed>|null $settings
 * @property string $timezone
 * @property string $week_start
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @method static OrganizationFactory factory($count = null, $state = [])
 */
class Organization extends Model
{
    /** @use HasFactory<OrganizationFactory> */
    use Billable;
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'plan',
        'settings',
        'logo_path',
        'timezone',
        'week_start',
        'trial_ends_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'settings' => 'array', // <-- Casts the array to JSON string for the DB
        'trial_ends_at' => 'datetime',
    ];

    /**
     * Get the email for Stripe customer (billing receipts).
     * Uses the organization owner's email.
     */
    public function stripeEmail(): ?string
    {
        $owner = $this->users()->wherePivot('is_owner', true)->first();

        return $owner?->email ?? null;
    }

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
        $rel = $this->belongsToMany(User::class, 'organization_user')
            ->withPivot('is_owner')
            ->withTimestamps();

        return $rel;
    }

    /**
     * @return HasOne<\App\Models\Platform\OrganizationFeature, \App\Models\Organization>
     */
    public function features(): HasOne
    {
        /** @var HasOne<\App\Models\Platform\OrganizationFeature, \App\Models\Organization> $rel */
        $rel = $this->hasOne(OrganizationFeature::class);

        return $rel;
    }

    protected static function newFactory(): OrganizationFactory
    {
        return OrganizationFactory::new();
    }
}
