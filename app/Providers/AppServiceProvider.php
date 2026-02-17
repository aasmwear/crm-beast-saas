<?php

namespace App\Providers;

use App\Models\Client;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Task;
use App\Observers\ClientObserver;
use App\Observers\ProjectObserver;
use App\Observers\TaskObserver;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Laravel\Cashier\Cashier;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Cashier::useCustomerModel(Organization::class);
        URL::defaults(['organization' => 'acme']);
        Client::observe(ClientObserver::class);
        Project::observe(ProjectObserver::class);
        Task::observe(TaskObserver::class);
    }
}
