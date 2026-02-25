<?php

namespace App\Providers;

use App\Models\Client;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Task;
use App\Observers\ClientObserver;
use App\Observers\ProjectObserver;
use App\Observers\TaskObserver;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Laravel\Cashier\Cashier;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Cashier::useCustomerModel(Organization::class);

        // Populate organization_id on new notifications from data JSON (tenant isolation)
        DatabaseNotification::creating(function (DatabaseNotification $n) {
            $data = $n->data;
            if (is_array($data) && isset($data['organization_id'])) {
                $n->organization_id = (int) $data['organization_id'];
            }
        });
        URL::defaults(['organization' => 'acme']);
        Client::observe(ClientObserver::class);
        Project::observe(ProjectObserver::class);
        Task::observe(TaskObserver::class);
    }
}
