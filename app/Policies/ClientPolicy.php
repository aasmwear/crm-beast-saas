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
        $this->scopeTeamId($this->currentTeamId($user));

        return $user->is_super_admin || $user->can('clients.view');
    }

    public function view(User $user, Client $client): bool
    {
        if ((int) $client->organization_id !== (int) $user->active_organization_id) {
            return false;
        }

        $this->scopeTeamId((int) $client->organization_id);

        return $user->is_super_admin || $user->can('clients.view');
    }

    public function create(User $user): bool
    {
        $this->scopeTeamId($this->currentTeamId($user));

        return $user->is_super_admin || $user->can('clients.create');
    }

    public function update(User $user, Client $client): bool
    {
        if ((int) $client->organization_id !== (int) $user->active_organization_id) {
            return false;
        }

        $this->scopeTeamId((int) $client->organization_id);

        return $user->is_super_admin || $user->can('clients.edit') || $user->can('clients.update');
    }

    public function delete(User $user, Client $client): bool
    {
        if ((int) $client->organization_id !== (int) $user->active_organization_id) {
            return false;
        }

        $this->scopeTeamId((int) $client->organization_id);

        return $user->is_super_admin || $user->can('clients.delete');
    }
}
