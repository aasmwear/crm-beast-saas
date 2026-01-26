<?php

namespace App\Policies;

use App\Models\Client;
use App\Models\Organization;
use App\Models\User;
use Spatie\Permission\PermissionRegistrar;

final class ClientPolicy
{
    private function scopeTeamId(int $teamId): void
    {
        if ($teamId <= 0) {
            return;
        }

        // Defensive: align Spatie "teams" scoping for policy checks.
        app(PermissionRegistrar::class)->setPermissionsTeamId($teamId);
    }

    private function currentTeamId(User $user): int
    {
        // Prefer the org resolved by ResolveTenant middleware (if you bind it).
        if (app()->bound('scoped.organization')) {
            $org = app('scoped.organization');

            if ($org instanceof Organization) {
                return (int) $org->id;
            }

            if (is_object($org) && isset($org->id) && is_numeric($org->id)) {
                return (int) $org->id;
            }
        }

        // PHPStan considers this non-null (per your User model PHPDoc/types).
        return (int) $user->active_organization_id;
    }

    public function viewAny(User $user): bool
    {
        // Owners/Admins can always access the Clients module.
        $this->scopeTeamId($this->currentTeamId($user));

        if ($user->hasAnyRole(['Owner', 'Admin']) || $user->can('clients.view')) {
            return true;
        }

        // Otherwise, only allow access if the user has at least one visible client
        // (prevents 403/blank module for task-only team members).
        $orgId = $this->currentTeamId($user);

        return Client::query()
            ->forOrg($orgId)
            ->visibleTo($user)
            ->exists();
    }

    public function view(User $user, Client $client): bool
    {
        // Cross-org guard
        if ((int) $user->active_organization_id !== (int) $client->organization_id) {
            return false;
        }

        $this->scopeTeamId((int) $client->organization_id);

        if ($user->hasAnyRole(['Owner', 'Admin']) || $user->can('clients.view')) {
            return true;
        }

        // Enforce row-level visibility for direct URL access.
        return Client::query()
            ->whereKey($client->id)
            ->forOrg((int) $client->organization_id)
            ->visibleTo($user)
            ->exists();
    }

    public function create(User $user): bool
    {
        $this->scopeTeamId($this->currentTeamId($user));

        return $user->hasAnyRole(['Owner', 'Admin']) || $user->can('clients.create');
    }

    public function update(User $user, Client $client): bool
    {
        $this->scopeTeamId((int) $client->organization_id);

        return $user->hasAnyRole(['Owner', 'Admin']) || $user->can('clients.update');
    }

    public function delete(User $user, Client $client): bool
    {
        $this->scopeTeamId((int) $client->organization_id);

        return $user->hasAnyRole(['Owner', 'Admin']) || $user->can('clients.delete');
    }
}
