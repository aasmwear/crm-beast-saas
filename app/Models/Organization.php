<?php

namespace App\Models;

use App\Models\Platform\OrganizationFeature;
use App\Services\Billing\SeatCounter;
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
        'plan', // Legacy/display-only. Canonical plan_key is organization_subscriptions.plan_key; org.plan used as fallback when no subscription.
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
     * @return HasMany<\App\Models\Task, \App\Models\Organization>
     */
    public function tasks(): HasMany
    {
        /** @var HasMany<\App\Models\Task, \App\Models\Organization> $rel */
        $rel = $this->hasMany(Task::class);

        return $rel;
    }

    /**
     * @return HasMany<\App\Models\Attendance, \App\Models\Organization>
     */
    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
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

    /**
     * @return HasMany<OrganizationApiKey, \App\Models\Organization>
     */
    public function customFields(): HasMany
    {
        return $this->hasMany(CustomField::class);
    }

    public function apiKeys(): HasMany
    {
        /** @var HasMany<OrganizationApiKey, \App\Models\Organization> $rel */
        $rel = $this->hasMany(OrganizationApiKey::class);

        return $rel;
    }

    /**
     * Our billing subscription (plan_key, status, seats, etc.).
     * Named billingSubscription to avoid colliding with Cashier Billable::subscription('default').
     *
     * @return HasOne<OrganizationSubscription, \App\Models\Organization>
     */
    public function billingSubscription(): HasOne
    {
        return $this->hasOne(OrganizationSubscription::class, 'organization_id');
    }

    /**
     * @return HasMany<OrganizationAddon, \App\Models\Organization>
     */
    public function addons(): HasMany
    {
        return $this->hasMany(OrganizationAddon::class, 'organization_id');
    }

    /**
     * Whether the org can add another seat (under seat limit).
     * No UI blocking in this PR; used by enforcement helpers.
     */
    public function canAddSeat(): bool
    {
        return app(SeatCounter::class)->canAddSeat($this);
    }

    protected static function newFactory(): OrganizationFactory
    {
        return OrganizationFactory::new();
    }
}
