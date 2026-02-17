<?php

namespace App\Models;

use Spatie\Permission\Models\Role as SpatieRole;

/**
 * Custom Role model extending Spatie's Role.
 *
 * Adds field_permissions JSONB column for Field-Level Security.
 *
 * @property int $id
 * @property string $name
 * @property string $guard_name
 * @property int|null $team_id
 * @property array|null $permissions_map
 * @property array|null $field_permissions
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 *
 * @mixin \Eloquent
 */
final class Role extends SpatieRole
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'guard_name',
        'team_id',
        'permissions_map',
        'field_permissions',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'permissions_map' => 'array',
        'field_permissions' => 'array',
    ];

    /**
     * Check if a field is hidden for this role.
     *
     * @param  string  $entity  Entity name (e.g., 'projects', 'clients')
     * @param  string  $field   Field name (e.g., 'budget_cents', 'price_cents')
     * @return bool
     */
    public function isFieldHidden(string $entity, string $field): bool
    {
        if (! $this->field_permissions) {
            return false; // No restrictions = full access
        }

        $entityPermissions = $this->field_permissions[$entity] ?? null;
        if (! $entityPermissions) {
            return false;
        }

        $fieldPermission = $entityPermissions[$field] ?? null;

        return $fieldPermission === 'hidden';
    }

    /**
     * Check if a field is readonly for this role.
     *
     * @param  string  $entity  Entity name (e.g., 'projects', 'clients')
     * @param  string  $field   Field name (e.g., 'budget_cents', 'price_cents')
     * @return bool
     */
    public function isFieldReadonly(string $entity, string $field): bool
    {
        if (! $this->field_permissions) {
            return false; // No restrictions = full access
        }

        $entityPermissions = $this->field_permissions[$entity] ?? null;
        if (! $entityPermissions) {
            return false;
        }

        $fieldPermission = $entityPermissions[$field] ?? null;

        return $fieldPermission === 'readonly';
    }

    /**
     * Get field permission for a specific entity field.
     *
     * @param  string  $entity  Entity name (e.g., 'projects', 'clients')
     * @param  string  $field   Field name (e.g., 'budget_cents', 'price_cents')
     * @return string|null  'hidden', 'readonly', 'read_write', or null (full access)
     */
    public function getFieldPermission(string $entity, string $field): ?string
    {
        if (! $this->field_permissions) {
            return null; // No restrictions = full access
        }

        $entityPermissions = $this->field_permissions[$entity] ?? null;
        if (! $entityPermissions) {
            return null;
        }

        return $entityPermissions[$field] ?? null;
    }
}
