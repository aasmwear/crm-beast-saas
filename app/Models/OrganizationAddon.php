<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Add-on entitlements per organization (e.g. extra storage_gb, api_rpm).
 * mode: "augment" adds to base value; "set" overrides with value_int (highest wins if multiple).
 *
 * @property int $id
 * @property int $organization_id
 * @property string $addon_key
 * @property int $quantity
 * @property int|null $value_int
 * @property string $mode
 * @property bool $active
 * @property \Illuminate\Support\Carbon|null $starts_at
 * @property \Illuminate\Support\Carbon|null $ends_at
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class OrganizationAddon extends Model
{
    public const MODE_AUGMENT = 'augment';

    public const MODE_SET = 'set';

    protected $table = 'organization_addons';

    protected $fillable = [
        'organization_id',
        'addon_key',
        'quantity',
        'value_int',
        'mode',
        'active',
        'starts_at',
        'ends_at',
    ];

    protected $attributes = [
        'mode' => self::MODE_AUGMENT,
    ];

    protected $casts = [
        'quantity' => 'integer',
        'value_int' => 'integer',
        'active' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @param  Builder<OrganizationAddon>  $query
     * @return Builder<OrganizationAddon>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true)
            ->where(function (Builder $q) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function (Builder $q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            });
    }
}
