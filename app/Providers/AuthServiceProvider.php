<?php

namespace App\Providers;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Policies\ClientPolicy;
use App\Policies\InvoicePolicy;
use App\Policies\ProjectPolicy;
use App\Policies\TaskPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Project::class => ProjectPolicy::class,
        Client::class => ClientPolicy::class,
        Invoice::class => InvoicePolicy::class,
        Task::class => TaskPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();

        Gate::before(function (User $user, ?string $ability = null, array $arguments = []) {
            // Global bypass for platform Super Admins
            if ($user->is_super_admin) {
                return true;
            }

            // Team-aware Owner bypass (Spatie team context is set in ResolveTenant)
            if ($user->hasRole('Owner')) {
                return true;
            }

            return null; // fall through to policies/permissions
        });
    }
}
