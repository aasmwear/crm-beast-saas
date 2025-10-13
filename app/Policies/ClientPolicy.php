<?php

namespace App\Policies;

use App\Models\Client;
use App\Models\Organization;
use App\Models\User;

class ClientPolicy
{
    // Everyone must be in org; Admin/DepartmentHead manage; others restricted
    public function viewAny(User $user, Organization $org): bool
    {
        return true;
    }

    public function view(User $user, Client $client, Organization $org): bool
    {
        if ($user->hasRole(['Admin','DepartmentHead'], $org)) return true;

        // reuse visibility rule
        return Client::query()->forOrg($org)->visibleTo($user, $org)->whereKey($client->id)->exists();
    }

    public function create(User $user, Organization $org): bool
    {
        return $user->hasRole(['Admin','DepartmentHead','Manager','ProjectManager'], $org);
    }

    public function update(User $user, Client $client, Organization $org): bool
    {
        if ($user->hasRole(['Admin','DepartmentHead'], $org)) return true;

        // AM or PM on a project may edit notes/status
        return $client->assigned_account_manager_id === $user->id
            || $client->projects()->where('project_manager_id', $user->id)->exists();
    }

    public function delete(User $user, Client $client, Organization $org): bool
    {
        return $user->hasRole(['Admin','DepartmentHead'], $org);
    }
}
