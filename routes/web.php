<?php

use App\Http\Controllers\ClientsController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\TaskController;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    // Tests expect 200 on GET /
    return response('OK', 200);
});

require __DIR__.'/auth.php';

// Tenant dashboard (requires org in URL)
Route::middleware(['auth', 'resolveTenant'])->get('/org/{organization:slug}/dashboard', function () {
    $tenant = App::has('tenant') ? App::get('tenant') : null;
    $userId = auth()->id();

    $clients = collect();
    $kpis = [
        'activeProjects' => 0,
        'overdueTasks' => 0,
        'myOverdueTasks' => 0,
        'myOpenNext7Days' => 0,
        'myCompleted7' => 0,
        'myCompleted30' => 0,
    ];
    $activity = collect();
    $announcements = collect();
    $taskTrend7 = array_fill(0, 7, 0);
    $statusSplit = ['open' => 0, 'overdue' => 0, 'done' => 0];

    if ($tenant && $userId) {
        // ----- My Clients (visibility rule) -----
        $clients = DB::table('clients')
            ->where('organization_id', $tenant->id)
            ->where(function ($w) use ($userId) {
                $w->whereJsonContains('fronter', $userId)
                    ->orWhereJsonContains('closer', $userId)
                    ->orWhere('assigned_account_manager_id', $userId)
                    ->orWhereExists(function ($q) use ($userId) {
                        $q->from('projects')
                            ->whereColumn('projects.client_id', 'clients.id')
                            ->where(function ($qq) use ($userId) {
                                $qq->where('projects.project_manager_id', $userId)
                                    ->orWhereExists(function ($qqq) use ($userId) {
                                        $qqq->from('tasks')
                                            ->whereColumn('tasks.project_id', 'projects.id')
                                            ->whereJsonContains('assignees', $userId);
                                    });
                            });
                    });
            })
            ->orderBy('company_name')
            ->limit(12)
            ->get(['id', 'company_name', 'niche', 'status']);

        // ----- KPI base windows -----
        $today = now()->startOfDay();
        $last7 = now()->subDays(7);
        $last30 = now()->subDays(30);

        // KPIs
        $kpis['activeProjects'] = DB::table('projects')
            ->where('organization_id', $tenant->id)
            ->where('status', 'Active')->count();

        $kpis['overdueTasks'] = DB::table('tasks')
            ->where('organization_id', $tenant->id)
            ->whereNotIn('status', ['Done', 'Completed'])
            ->whereNotNull('due_date')
            ->where('due_date', '<', $today)->count();

        $kpis['myOverdueTasks'] = DB::table('tasks')
            ->where('organization_id', $tenant->id)
            ->whereJsonContains('assignees', $userId)
            ->whereNotIn('status', ['Done', 'Completed'])
            ->whereNotNull('due_date')
            ->where('due_date', '<', $today)->count();

        $kpis['myOpenNext7Days'] = DB::table('tasks')
            ->where('organization_id', $tenant->id)
            ->whereJsonContains('assignees', $userId)
            ->whereNotIn('status', ['Done', 'Completed'])
            ->whereNotNull('due_date')
            ->whereBetween('due_date', [$today, now()->addDays(7)])->count();

        $kpis['myCompleted7'] = DB::table('tasks')
            ->where('organization_id', $tenant->id)
            ->whereJsonContains('assignees', $userId)
            ->whereIn('status', ['Done', 'Completed'])
            ->where('updated_at', '>=', $last7)->count();

        $kpis['myCompleted30'] = DB::table('tasks')
            ->where('organization_id', $tenant->id)
            ->whereJsonContains('assignees', $userId)
            ->whereIn('status', ['Done', 'Completed'])
            ->where('updated_at', '>=', $last30)->count();

        // ----- Optional charts -----
        $statusSplit = [
            'open' => DB::table('tasks')->where('organization_id', $tenant->id)
                ->whereNotIn('status', ['Done', 'Completed'])->count(),
            'overdue' => DB::table('tasks')->where('organization_id', $tenant->id)
                ->whereNotIn('status', ['Done', 'Completed'])
                ->whereNotNull('due_date')->where('due_date', '<', $today)->count(),
            'done' => DB::table('tasks')->where('organization_id', $tenant->id)
                ->whereIn('status', ['Done', 'Completed'])->count(),
        ];

        $taskTrend7 = [];
        for ($i = 6; $i >= 0; $i--) {
            $from = now()->subDays($i)->startOfDay();
            $to = (clone $from)->endOfDay();
            $taskTrend7[] = DB::table('tasks')
                ->where('organization_id', $tenant->id)
                ->whereIn('status', ['Done', 'Completed'])
                ->whereBetween('updated_at', [$from, $to])->count();
        }

        // Recent Activity
        $activity = DB::table('tasks')
            ->join('projects', 'projects.id', '=', 'tasks.project_id')
            ->join('clients', 'clients.id', '=', 'projects.client_id')
            ->where('tasks.organization_id', $tenant->id)
            ->orderBy('tasks.updated_at', 'desc')
            ->limit(12)
            ->get([
                'tasks.id as id', 'tasks.title as title', 'tasks.status as status', 'tasks.updated_at as ts',
                'projects.title as project_title', 'clients.company_name as client_name',
            ]);

        // Pinned announcements
        $announcements = DB::table('announcements')
            ->where('organization_id', $tenant->id)
            ->where('pinned', true)
            ->orderBy('created_at', 'desc')
            ->limit(3)
            ->get(['id', 'title', 'body', 'created_at']);
    }

    return Inertia::render('Dashboard', [
        'tenant' => $tenant ? $tenant->only(['id', 'name', 'slug']) : null,
        'myClients' => $clients,
        'kpis' => $kpis,
        'activity' => $activity,
        'announcements' => $announcements,
        'taskTrend7' => $taskTrend7,
        'statusSplit' => $statusSplit,
    ]);
})->name('tenant.dashboard');

// Auth'd + tenant prefix routes
Route::middleware('auth')->group(function () {
    Route::prefix('org/{organization:slug}')->group(function () {
        Route::get('/pipeline', [ProjectController::class, 'board'])->name('projects.board');
        Route::post('/projects', [ProjectController::class, 'store'])->name('projects.store');
        Route::post('/tasks', [TaskController::class, 'store'])->name('tasks.store');
        Route::patch('/tasks/{task}/move', [TaskController::class, 'move'])->name('tasks.move');
    });
});

// Tenant-scoped Clients (scoped bindings prevent cross-org leaks)
Route::middleware(['auth', 'resolveTenant'])
    ->scopeBindings()
    ->prefix('org/{organization:slug}')
    ->group(function () {
        Route::get('/clients', [ClientsController::class, 'index'])->name('clients.index');
        Route::get('/clients/create', [ClientsController::class, 'create'])->name('clients.create');
        Route::post('/clients', [ClientsController::class, 'store'])->name('clients.store');
        Route::get('/clients/{client}', [ClientsController::class, 'show'])->name('clients.show');
        Route::get('/clients/{client}/edit', [ClientsController::class, 'edit'])->name('clients.edit');
        Route::put('/clients/{client}', [ClientsController::class, 'update'])->name('clients.update');
        Route::delete('/clients/{client}', [ClientsController::class, 'destroy'])->name('clients.destroy');
    });

// Global /dashboard that figures out the user’s org and redirects
Route::get('/dashboard', function () {
    $slug = 'acme';

    if (auth()->check()) {
        $slug = DB::table('organizations')
            ->join('organization_user', 'organizations.id', '=', 'organization_user.organization_id')
            ->where('organization_user.user_id', auth()->id())
            ->orderBy('organizations.id')
            ->value('slug') ?? 'acme';
    }

    return redirect("/org/{$slug}/dashboard");
})->name('dashboard');

// Profile routes (Breeze expectations)
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});
