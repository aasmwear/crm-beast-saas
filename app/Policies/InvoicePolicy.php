<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;
use Spatie\Permission\PermissionRegistrar;

final class InvoicePolicy
{
    private function scopeTeamId(int $teamId): void
    {
        if ($teamId > 0) {
            app(PermissionRegistrar::class)->setPermissionsTeamId($teamId);
        }
    }

    public function viewAny(User $user): bool
    {
        $this->scopeTeamId((int) $user->active_organization_id);
        return $user->can('clients.view') || $user->can('clients.manage') || $user->is_super_admin;
    }

    public function view(User $user, Invoice $invoice): bool
    {
        $this->scopeTeamId((int) $invoice->organization_id);
        if ($user->is_super_admin) {
            return true;
        }
        return $user->can('view', $invoice->client);
    }

    public function create(User $user): bool
    {
        $this->scopeTeamId((int) $user->active_organization_id);
        return $user->can('clients.manage') || $user->can('clients.view') || $user->is_super_admin;
    }

    public function update(User $user, Invoice $invoice): bool
    {
        return $this->view($user, $invoice);
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        return $this->view($user, $invoice);
    }
}
