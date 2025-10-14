<?php

namespace App\Policies;

use App\Models\Client;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ClientPolicy
{
    // Helper: does the user belong to this org?
    protected function inOrg(User $user, Organization $org): bool
    {
        return DB::table('organization_user')
            ->where('user_id', $user->id)
            ->where('organization_id', $org->id)
            ->exists();
    }

    // viewAny is called as: authorize('viewAny', [Client::class, $organization])
    public function viewAny(User $user, Organization $organization): bool
    {
        return $this->inOrg($user, $organization);
    }

    // All other checks use the actual client record (scoped binding protects cross-org access)
    public function view(User $user, Client $client): bool
    {
        return $this->inOrg($user, $client->organization);
    }

    public function create(User $user, Organization $organization): bool
    {
        return $this->inOrg($user, $organization);
    }

    public function update(User $user, Client $client): bool
    {
        return $this->inOrg($user, $client->organization);
    }

    public function delete(User $user, Client $client): bool
    {
        return $this->inOrg($user, $client->organization);
    }
}
