<?php

namespace App\Policies;

use App\Models\Client;
use App\Models\Organization;
use App\Models\User;

class ClientPolicy
{
    /**
     * Temporarily permissive; routes are org-scoped and controllers check org match.
     * Later, tighten per role (owner, AM, sales, etc.).
     */
    public function viewAny(User $user, Organization $organization): bool
    {
        return true;
    }

    public function view(User $user, Client $client, Organization $organization): bool
    {
        return $client->organization_id === $organization->id;
    }

    public function create(User $user, Organization $organization): bool
    {
        return true;
    }

    public function update(User $user, Client $client, Organization $organization): bool
    {
        return $client->organization_id === $organization->id;
    }

    public function delete(User $user, Client $client, Organization $organization): bool
    {
        return $client->organization_id === $organization->id;
    }
}
