<?php

namespace App\Models\Platform;

use App\Models\Organization;
use Database\Factories\Platform\OrganizationFeatureFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Organization Feature Model
 * 
 * Manages feature flags and subscription status for each organization.
 * 
 * @property int $id
 * @property int $organization_id
 * @property array $features
 * @property string $subscription_status
 * @property \Illuminate\Support\Carbon|null $trial_ends_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Organization $organization
 */
class OrganizationFeature extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'organization_features';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'organization_id',
        'features',
        'subscription_status',
        'trial_ends_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'features' => 'array',
        'trial_ends_at' => 'datetime',
    ];

    /**
     * Default features for new organizations.
     *
     * @var array<string, mixed>
     */
    public const DEFAULT_FEATURES = [
        'attendance' => true,
        'sms' => false,
        'api_access' => false,
        'storage_gb' => 5,
        'api_rpm' => 60,
        'exports_per_day' => 5,
    ];

    /**
     * Get the organization that owns the features.
     *
     * @return BelongsTo<\App\Models\Organization, \App\Models\Platform\OrganizationFeature>
     */
    public function organization(): BelongsTo
    {
        /** @var BelongsTo<\App\Models\Organization, \App\Models\Platform\OrganizationFeature> $rel */
        $rel = $this->belongsTo(Organization::class);

        return $rel;
    }

    /**
     * Check if a specific feature is enabled.
     *
     * @param string $feature
     * @return bool
     */
    public function hasFeature(string $feature): bool
    {
        return $this->features[$feature] ?? false;
    }

    /**
     * Enable a feature.
     *
     * @param string $feature
     * @return void
     */
    public function enableFeature(string $feature): void
    {
        $features = $this->features;
        $features[$feature] = true;
        $this->features = $features;
        $this->save();
    }

    /**
     * Disable a feature.
     *
     * @param string $feature
     * @return void
     */
    public function disableFeature(string $feature): void
    {
        $features = $this->features;
        $features[$feature] = false;
        $this->features = $features;
        $this->save();
    }

    /**
     * Check if the subscription is active.
     *
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->subscription_status === 'active';
    }

    /**
     * Check if the trial has ended.
     *
     * @return bool
     */
    public function trialEnded(): bool
    {
        return $this->trial_ends_at !== null && $this->trial_ends_at->isPast();
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): OrganizationFeatureFactory
    {
        return OrganizationFeatureFactory::new();
    }
}
