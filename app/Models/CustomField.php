<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Custom field definition (org-scoped).
 *
 * @property int $id
 * @property int $organization_id
 * @property string $entity
 * @property string $label
 * @property string $slug
 * @property string $type
 * @property array|null $options
 * @property bool $is_required
 * @property int $sort_order
 *
 * @method static Builder<CustomField> forEntity(string $entity)
 * @method static Builder<CustomField> forOrg(int $orgId)
 */
final class CustomField extends Model
{
    public const ENTITY_CLIENT = 'client';

    public const TYPE_TEXT = 'text';
    public const TYPE_NUMBER = 'number';
    public const TYPE_DATE = 'date';
    public const TYPE_SELECT = 'select';
    public const TYPE_MULTISELECT = 'multiselect';

    public const TYPES = [
        self::TYPE_TEXT,
        self::TYPE_NUMBER,
        self::TYPE_DATE,
        self::TYPE_SELECT,
        self::TYPE_MULTISELECT,
    ];

    protected $fillable = [
        'organization_id',
        'entity',
        'label',
        'slug',
        'type',
        'options',
        'is_required',
        'sort_order',
    ];

    protected $casts = [
        'options' => 'array',
        'is_required' => 'boolean',
    ];

    /**
     * @return BelongsTo<Organization, CustomField>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return HasMany<CustomFieldValue, CustomField>
     */
    public function values(): HasMany
    {
        $relation = $this->hasMany(CustomFieldValue::class);

        if ($this->exists) {
            $relation->where('custom_field_values.organization_id', $this->organization_id);
        }

        return $relation;
    }

    /**
     * @param  Builder<CustomField>  $q
     * @return Builder<CustomField>
     */
    public function scopeForOrg(Builder $q, int $orgId): Builder
    {
        return $q->where('organization_id', $orgId);
    }

    /**
     * @param  Builder<CustomField>  $q
     * @return Builder<CustomField>
     */
    public function scopeForEntity(Builder $q, string $entity): Builder
    {
        return $q->where('entity', $entity);
    }

    public static function slugFromLabel(string $label): string
    {
        return strtolower(preg_replace('/[^a-z0-9]+/i', '_', trim($label)) ?: 'field');
    }
}
