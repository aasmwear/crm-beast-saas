<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * FilterResponseFields Middleware.
 *
 * Implements Field-Level Security by filtering sensitive fields from API responses
 * based on the authenticated user's role permissions.
 *
 * This is "The Vault" from MASTER_SPECIFICATION_v6_1.md Section 4.2.
 */
final class FilterResponseFields
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        // Only filter JSON responses
        if (! $response instanceof JsonResponse) {
            return $response;
        }

        // Only filter for authenticated users
        $user = $request->user();
        if (! $user) {
            return $response;
        }

        // Super admins bypass field filtering
        if ((bool) ($user->is_super_admin ?? false)) {
            return $response;
        }

        // Get user's roles with field permissions
        $roles = $user->roles()->with('permissions')->get();

        // Aggregate field permissions from all roles
        $aggregatedPermissions = $this->aggregateFieldPermissions($roles);

        // If no field restrictions, return as-is
        if (empty($aggregatedPermissions)) {
            return $response;
        }

        // Get response data
        $data = $response->getData(true);

        // Filter the response data
        $filteredData = $this->filterData($data, $aggregatedPermissions);

        // Set filtered data back to response
        $response->setData($filteredData);

        return $response;
    }

    /**
     * Aggregate field permissions from multiple roles.
     *
     * Uses the most restrictive permission when roles conflict.
     *
     * @param  \Illuminate\Database\Eloquent\Collection  $roles
     * @return array<string, array<string, string>>
     */
    private function aggregateFieldPermissions($roles): array
    {
        $aggregated = [];

        foreach ($roles as $role) {
            $fieldPermissions = $role->field_permissions ?? [];

            foreach ($fieldPermissions as $entity => $fields) {
                if (! isset($aggregated[$entity])) {
                    $aggregated[$entity] = [];
                }

                foreach ($fields as $field => $permission) {
                    // If not set yet, use this permission
                    if (! isset($aggregated[$entity][$field])) {
                        $aggregated[$entity][$field] = $permission;
                        continue;
                    }

                    // Use most restrictive: hidden > readonly > read_write
                    $existing = $aggregated[$entity][$field];

                    if ($permission === 'hidden' || $existing === 'hidden') {
                        $aggregated[$entity][$field] = 'hidden';
                    } elseif ($permission === 'readonly' || $existing === 'readonly') {
                        $aggregated[$entity][$field] = 'readonly';
                    }
                }
            }
        }

        return $aggregated;
    }

    /**
     * Recursively filter data based on field permissions.
     *
     * @param  mixed  $data  Data to filter
     * @param  array<string, array<string, string>>  $permissions  Field permissions
     * @param  string|null  $entityContext  Current entity context (e.g., 'projects', 'clients')
     * @return mixed Filtered data
     */
    private function filterData($data, array $permissions, ?string $entityContext = null)
    {
        // Handle arrays
        if (is_array($data)) {
            // Try to detect entity context from keys
            if ($entityContext === null) {
                $entityContext = $this->detectEntityContext($data);
            }

            $filtered = [];

            foreach ($data as $key => $value) {
                // Check if this field should be hidden
                if ($entityContext && $this->isFieldHidden($entityContext, $key, $permissions)) {
                    continue; // Skip hidden fields
                }

                // Recursively filter nested data
                if (is_array($value) || is_object($value)) {
                    $filtered[$key] = $this->filterData($value, $permissions, $this->detectEntityContext(is_array($value) ? $value : (array) $value));
                } else {
                    $filtered[$key] = $value;
                }
            }

            return $filtered;
        }

        // Handle objects
        if (is_object($data)) {
            $dataArray = (array) $data;
            $filteredArray = $this->filterData($dataArray, $permissions, $entityContext);

            return (object) $filteredArray;
        }

        // Scalar values pass through
        return $data;
    }

    /**
     * Detect entity context from data keys.
     *
     * Tries to infer the entity type based on common field patterns.
     *
     * @param  array<string, mixed>  $data
     * @return string|null Entity name (e.g., 'projects', 'clients')
     */
    private function detectEntityContext(array $data): ?string
    {
        // Project indicators
        if (isset($data['project_id']) || isset($data['project_code']) || isset($data['budget_cents'])) {
            return 'projects';
        }

        // Client indicators
        if (isset($data['company_name']) || isset($data['fronter_id']) || isset($data['closer_id'])) {
            return 'clients';
        }

        // Task indicators
        if (isset($data['task_id']) || (isset($data['assignees']) && isset($data['status']) && isset($data['due_date']))) {
            return 'tasks';
        }

        // User indicators
        if (isset($data['user_id']) || isset($data['email']) || isset($data['department_id'])) {
            return 'users';
        }

        return null;
    }

    /**
     * Check if a field should be hidden.
     *
     * @param  string  $entity  Entity name
     * @param  string  $field  Field name
     * @param  array<string, array<string, string>>  $permissions  Field permissions
     * @return bool True if field should be hidden
     */
    private function isFieldHidden(string $entity, string $field, array $permissions): bool
    {
        // Check if entity has permissions
        if (! isset($permissions[$entity])) {
            return false;
        }

        // Check if field has permission
        if (! isset($permissions[$entity][$field])) {
            return false;
        }

        // Return true if field is marked as 'hidden'
        return $permissions[$entity][$field] === 'hidden';
    }
}
