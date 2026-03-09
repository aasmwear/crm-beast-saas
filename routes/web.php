<?php

// -----------------------------
// Auth (Breeze) Controllers
// -----------------------------
use App\Http\Controllers\ActivityController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
// -----------------------------
// App Controllers
// -----------------------------
use App\Http\Controllers\Auth\RegisteredTenantController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\Admin\SubscriptionController;
use App\Http\Controllers\ClientContactController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\PortalAccessController;
use App\Http\Controllers\ClientsImportController;
use App\Http\Controllers\ClientsInertiaController;
use App\Http\Controllers\ClientsPipelineController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\HRMController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\NotificationCenterController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectBoardController;
use App\Http\Controllers\ProjectCalendarController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\ProjectFileController;
use App\Http\Controllers\ProjectMessagesController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RolePermissionController;
use App\Http\Controllers\SettingsApiKeysController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\Portal\DashboardController as PortalDashboardController;
use App\Http\Controllers\Portal\InvoiceController as PortalInvoiceController;
use App\Http\Controllers\Portal\PaymentController as PortalPaymentController;
use App\Http\Controllers\Portal\ProjectController as PortalProjectController;
use App\Http\Controllers\WebhookController;
use App\Http\Controllers\HealthCheckController;
use App\Http\Controllers\TaskBoardController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\UserManagementController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

if (app()->environment('local')) {
    Route::get('/dev/login', function () {
        // NOTE: With Spatie "teams", role checks can misbehave if tenant isn't resolved yet.
        // Keep this helper independent of roles, and prefer a Super Admin user.
        $orgSlug = (string) request()->query('org', 'acme');
        $as = (string) request()->query('as', ''); // ?as=owner forces non-super login

        /** @var int|null $orgId */
        $orgId = \App\Models\Organization::query()
            ->where('slug', $orgSlug)
            ->value('id');

        /** @var \App\Models\User|null $user */
        $user = null;

        // Prefer Super Admin unless explicitly forcing owner
        if ($as !== 'owner') {
            $user = \App\Models\User::query()
                ->where('is_super_admin', true)
                ->orderBy('id')
                ->first();
        }

        // Fallbacks for local/dev convenience
        $user = $user
            ?: \App\Models\User::query()->where('email', "owner@{$orgSlug}.test")->first()
            ?: \App\Models\User::query()->orderBy('id')->first();

        if ($user === null) {
            abort(404, 'No users found. Run migrate:fresh --seed first.');
        }

        // If we're not forcing owner, ensure this dev user can access everything
        if ($as !== 'owner' && ! $user->is_super_admin) {
            $user->forceFill([
                'is_super_admin' => true,
                'email_verified_at' => $user->email_verified_at ?? now(),
            ])->save();
        }

        if ($orgId !== null) {
            // Ensure tenant attachment + active org so org-scoped pages work predictably.
            $user->organizations()->syncWithoutDetaching([$orgId]);

            if ((int) $user->active_organization_id !== (int) $orgId) {
                $user->forceFill(['active_organization_id' => $orgId])->save();
            }
        }

        Auth::login($user);

        /** @var string|null $slug */
        $slug = \App\Models\Organization::query()
            ->whereKey($user->active_organization_id) // handles null safely
            ->value('slug');

        $slug = $slug ?? $orgSlug;

        return redirect("/org/{$slug}/dashboard");
    })->name('dev.login')->middleware('web');

    Route::get('/dev/logout', function () {
        Auth::guard('web')->logout();

        $request = request();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    })->name('dev.logout')->middleware('web');
}

/*
|--------------------------------------------------------------------------
| Public / Health Route
|--------------------------------------------------------------------------
| A plain 200 OK keeps smoke tests happy and avoids auth redirects on `/`.
*/
Route::get('/', fn () => response('OK', 200))->name('root');

/*
|--------------------------------------------------------------------------
| Internal Readiness Probe
|--------------------------------------------------------------------------
| Verifies DB, cache, queue config. Internal-only — protect via
| reverse-proxy / firewall in production.
*/
Route::get('/_readiness', HealthCheckController::class)->name('readiness');

/*
|--------------------------------------------------------------------------
| Breeze Auth Routes (Consolidated here)
|--------------------------------------------------------------------------
*/
Route::middleware(['web', 'auth', 'verified', 'superAdmin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/dashboard', [AdminDashboardController::class, 'index'])
            ->name('admin.dashboard');
    });

Route::middleware('guest')->group(function () {
    // Registration
    Route::get('register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('register', [RegisteredUserController::class, 'store']);

    // Public SaaS tenant registration (create org + become owner)
    Route::get('register-company', [RegisteredTenantController::class, 'create'])->name('register.company');
    Route::post('register-company', [RegisteredTenantController::class, 'store'])->name('register.company.store');

    // Login
    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store']);

    // Password reset
    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])
        ->name('password.request');
    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->name('password.email');
    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])
        ->name('password.reset');
    Route::post('reset-password', [NewPasswordController::class, 'store'])
        ->name('password.store');
});

// Logout
Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

// Email verification
Route::get('verify-email', EmailVerificationPromptController::class)
    ->middleware('auth')
    ->name('verification.notice');

Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
    ->middleware(['auth', 'signed', 'throttle:6,1'])
    ->name('verification.verify');

Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
    ->middleware(['auth', 'throttle:6,1'])
    ->name('verification.send');

/*
|--------------------------------------------------------------------------
| Org-Scoped Application Routes
|--------------------------------------------------------------------------
| Multitenant URLs: /org/{organization}/...
*/
Route::prefix('org/{organization:slug}')
    ->middleware(['auth', 'verified', 'resolveTenant'])
    ->group(function () {
        // Dashboard
        Route::get('/dashboard', [DashboardController::class, 'index'])
            ->name('dashboard');

        /*
        |------------------------------------
        | Clients
        |------------------------------------
        */
        Route::get('/clients', [ClientsInertiaController::class, 'index'])->name('clients.index');

        Route::get('/clients/import', [ClientsInertiaController::class, 'import'])->name('clients.import');
        Route::post('/clients/import', [ClientsImportController::class, 'import'])->name('clients.import.store');

        Route::get('/clients/create', [ClientsInertiaController::class, 'create'])->name('clients.create');

        // ✅ Ziggy expects this route name for the pipeline page
        Route::get('/clients/pipeline', [ClientsPipelineController::class, 'index'])->name('clients.pipeline');

        // ✅ Update a single client's pipeline status (keep before /clients/{client})
        Route::post('/clients/{client}/pipeline', [ClientsPipelineController::class, 'update'])->name('clients.pipeline.update')->scopeBindings();

        Route::post('/clients', [ClientController::class, 'store'])->name('clients.store');
        Route::get('/clients/{client}', [ClientsInertiaController::class, 'show'])->name('clients.show')->scopeBindings();
        Route::get('/clients/{client}/edit', [ClientsInertiaController::class, 'edit'])->name('clients.edit')->scopeBindings();
        Route::put('/clients/{client}', [ClientController::class, 'update'])->name('clients.update')->scopeBindings();
        Route::delete('/clients/{client}', [ClientController::class, 'destroy'])->name('clients.destroy')->scopeBindings();

        Route::post('/clients/{client}/contacts', [ClientContactController::class, 'store'])->name('clients.contacts.store');
        Route::put('/clients/{client}/contacts/{contact}', [ClientContactController::class, 'update'])->name('clients.contacts.update');
        Route::delete('/clients/{client}/contacts/{contact}', [ClientContactController::class, 'destroy'])->name('clients.contacts.destroy');
        Route::post('/clients/{client}/contacts/{contact}/portal-access', [PortalAccessController::class, 'store'])->name('clients.contacts.portal.store');

        Route::get('/clients/export', [ClientController::class, 'exportCsv'])->name('clients.export');

        /*
        |------------------------------
        | Projects (Final Correct Order)
        |------------------------------
        */
        Route::get('/projects/board', [ProjectBoardController::class, 'index'])->name('projects.board');
        Route::get('/projects/calendar', [ProjectCalendarController::class, 'index'])->name('projects.calendar');

        Route::post('/projects/{project}/comments', [CommentController::class, 'store'])->name('projects.comments.store');
        Route::delete('/comments/{comment}', [CommentController::class, 'destroy'])->name('comments.destroy')->scopeBindings();

        Route::post('/projects/{project}/files', [ProjectFileController::class, 'store'])->name('projects.files.store');
        Route::delete('/projects/{project}/files/{projectFile}', [ProjectFileController::class, 'destroy'])->name('projects.files.destroy');
        Route::patch('/projects/{project}/files/{projectFile}/visibility', [ProjectFileController::class, 'toggleVisibility'])->name('projects.files.toggleVisibility');
        Route::get('/projects/{project}/files/{projectFile}/download', [ProjectFileController::class, 'download'])->name('projects.files.download');

        Route::get('/projects/{project}/messages', [ProjectMessagesController::class, 'index'])->name('projects.messages.index');
        Route::post('/projects/{project}/messages', [ProjectMessagesController::class, 'store'])->name('projects.messages.store');
        Route::get('/projects/{project}/messages/{message}/download/{index}', [ProjectMessagesController::class, 'download'])->name('projects.messages.download');

        Route::post('/projects/{project}/status', [ProjectController::class, 'updateStatus'])->name('projects.pipeline.update');

        Route::scopeBindings()->group(function () {
            Route::resource('projects', ProjectController::class);
        });

        /*
        |------------------------------
        | Tasks
        |------------------------------
        */
        Route::middleware('feature:tasks')->group(function () {
            // IMPORTANT: keep this before /tasks/{task} so `board` does not get treated as a Task id.
            Route::get('/tasks/board', [TaskBoardController::class, 'index'])->name('tasks.board');

            Route::get('/tasks', [TaskController::class, 'index'])->name('tasks.index');
            Route::post('/tasks', [TaskController::class, 'store'])->name('tasks.store');
            Route::get('/tasks/{task}', [TaskController::class, 'show'])->name('tasks.show')->scopeBindings();
            Route::put('/tasks/{task}', [TaskController::class, 'update'])->name('tasks.update')->scopeBindings();
            Route::delete('/tasks/{task}', [TaskController::class, 'destroy'])->name('tasks.destroy')->scopeBindings();

            Route::post('/tasks/{task}/submit', [TaskController::class, 'submit'])->name('tasks.submit')->scopeBindings();
            Route::post('/tasks/{task}/review', [TaskController::class, 'review'])->name('tasks.review')->scopeBindings();
        });

        /*
        |------------------------------
        | Invoices
        |------------------------------
        */
        Route::get('/invoices', [InvoiceController::class, 'index'])->name('invoices.index');
        Route::get('/invoices/create', [InvoiceController::class, 'create'])->name('invoices.create');
        Route::post('/invoices', [InvoiceController::class, 'store'])->name('invoices.store');
        Route::get('/invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
        Route::put('/invoices/{invoice}', [InvoiceController::class, 'update'])->name('invoices.update');
        Route::get('/invoices/{invoice}/download', [InvoiceController::class, 'download'])->name('invoices.download');
        Route::post('/invoices/{invoice}/mark-sent', [InvoiceController::class, 'markSent'])->name('invoices.mark-sent');
        Route::post('/invoices/{invoice}/mark-paid', [InvoiceController::class, 'markPaid'])->name('invoices.mark-paid');

        /*
        |------------------------------
        | Billing
        |------------------------------
        */
        Route::get('/billing', [SubscriptionController::class, 'index'])->name('billing.index');
        Route::post('/billing/checkout', [SubscriptionController::class, 'checkout'])->name('billing.checkout');
        Route::get('/billing/portal', [SubscriptionController::class, 'portal'])->name('billing.portal');
        Route::patch('/billing/plan', [SubscriptionController::class, 'updatePlan'])->name('billing.plan.update');
        Route::post('/billing/addons', [SubscriptionController::class, 'storeAddon'])->name('billing.addons.store');
        Route::patch('/billing/addons/{addon}', [SubscriptionController::class, 'updateAddon'])->name('billing.addons.update')->scopeBindings();
        Route::delete('/billing/addons/{addon}', [SubscriptionController::class, 'destroyAddon'])->name('billing.addons.destroy')->scopeBindings();

        /*
        |------------------------------
        | Announcements
        |------------------------------
        */
        Route::get('/announcements', [AnnouncementController::class, 'index'])->name('announcements.index');
        Route::post('/announcements', [AnnouncementController::class, 'store'])->name('announcements.store');
        Route::get('/announcements/{announcement}', [AnnouncementController::class, 'show'])->name('announcements.show');
        Route::put('/announcements/{announcement}', [AnnouncementController::class, 'update'])->name('announcements.update');
        Route::delete('/announcements/{announcement}', [AnnouncementController::class, 'destroy'])->name('announcements.destroy');

        /*
        |------------------------------
        | HRM / Employees
        |------------------------------
        */
        Route::get('/hrm', [HRMController::class, 'index'])->name('hrm.index');
        Route::post('/hrm', [HRMController::class, 'store'])->name('hrm.store');
        Route::put('/hrm/{user}', [HRMController::class, 'update'])->name('hrm.update');
        Route::delete('/hrm/{user}', [HRMController::class, 'destroy'])->name('hrm.destroy');

        /*
        |------------------------------
        | Attendance
        |------------------------------
        */
        Route::middleware('feature:attendance')->group(function () {
            Route::get('/attendance', [AttendanceController::class, 'index'])->name('attendance.index');
            Route::post('/attendance/clock-in', [AttendanceController::class, 'clockIn'])->name('attendance.clockIn');
            Route::post('/attendance/clock-out', [AttendanceController::class, 'clockOut'])->name('attendance.clockOut');
            Route::post('/attendance/{attendance}/approve', [AttendanceController::class, 'approve'])->name('attendance.approve');
            Route::patch('/attendance/{attendance}', [AttendanceController::class, 'update'])->name('attendance.update');
        });

        /*
        |------------------------------
        | Settings
        |------------------------------
        */
        Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
        Route::match(['put', 'post'], '/settings', [SettingsController::class, 'update'])->name('settings.update');
        Route::post('/settings/test-slack', [SettingsController::class, 'testSlack'])->name('settings.testSlack');
        Route::post('/settings/test-smtp', [SettingsController::class, 'testSmtp'])->name('settings.testSmtp');
        Route::post('/settings/features', [SettingsController::class, 'updateFeatures'])->name('settings.features');
        Route::post('/settings/api-keys', [SettingsApiKeysController::class, 'store'])->name('settings.api-keys.store');
        Route::delete('/settings/api-keys/{apiKey}', [SettingsApiKeysController::class, 'destroy'])->name('settings.api-keys.destroy')->scopeBindings();

        /*
        |------------------------------
        | Activity / Audit Log
        |------------------------------
        */
        Route::get('/activity', [ActivityController::class, 'index'])->name('activity.index');

        /*
        |------------------------------
        | Notifications
        |------------------------------
        */
        Route::get('/notifications', [NotificationCenterController::class, 'index'])->name('notifications.index');
        Route::get('/notifications/list', [NotificationCenterController::class, 'list'])->name('notifications.list');
        Route::post('/notifications/read-all', [NotificationCenterController::class, 'markAllRead'])->name('notifications.readAll');
        Route::post('/notifications/{notification}/read', [NotificationCenterController::class, 'markRead'])->name('notifications.read');

        /*
        |------------------------------
        | Departments
        |------------------------------
        */
        Route::get('/departments', [DepartmentController::class, 'index'])->name('departments.index');
        Route::post('/departments', [DepartmentController::class, 'store'])->name('departments.store');
        Route::put('/departments/{department}', [DepartmentController::class, 'update'])->name('departments.update');
        Route::delete('/departments/{department}', [DepartmentController::class, 'destroy'])->name('departments.destroy');

        /*
        |------------------------------
        | User Management & Role Assignment
        |------------------------------
        */
        Route::get('/users', [UserManagementController::class, 'index'])->name('users.index');
        Route::put('/users/{user}', [UserManagementController::class, 'update'])->name('users.update');

        /*
        |------------------------------
        | Roles & Permissions (Spatie) — /settings/roles
        |------------------------------
        */
        Route::get('/settings/roles', [RolePermissionController::class, 'index'])->name('roles.index');
        Route::post('/settings/roles', [RolePermissionController::class, 'store'])->name('roles.store');
        Route::put('/settings/roles', [RolePermissionController::class, 'update'])->name('roles.update');
        Route::post('/roles-permissions/save', [RolePermissionController::class, 'save'])->name('roles.save');

        /*
        |------------------------------
        | Reports / Exports / Backups
        |------------------------------
        */
        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/export/csv/{entity}', [ReportController::class, 'exportCsv'])->name('export.csv');
        Route::post('/backup/snapshot', [ReportController::class, 'snapshot'])->name('backup.snapshot');

});

/*
|--------------------------------------------------------------------------
| Breeze auth + profile endpoints added to satisfy tests
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store']);

    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.attempt');

    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])
        ->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])
        ->name('password.email');
    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])
        ->name('password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])
        ->name('password.store');
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/confirm-password', [ConfirmablePasswordController::class, 'show'])
        ->name('password.confirm');
    Route::post('/confirm-password', [ConfirmablePasswordController::class, 'store']);

    Route::put('/password', [PasswordController::class, 'update'])
        ->name('password.update');

    Route::get('/profile', [ProfileController::class, 'edit'])
        ->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])
        ->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])
        ->name('profile.destroy');
});

/*
|--------------------------------------------------------------------------
| Client Portal (role: Client, user has client_id)
|--------------------------------------------------------------------------
*/
Route::middleware(['web', 'auth', 'portal'])
    ->prefix('portal')
    ->name('portal.')
    ->group(function () {
        Route::get('/dashboard', [PortalDashboardController::class, 'index'])->name('dashboard');
        Route::post('/invoices/{invoice}/pay', [PortalPaymentController::class, 'pay'])->name('invoices.pay');
        Route::get('/invoices/{invoice}/success', [PortalPaymentController::class, 'success'])->name('invoices.success');
        Route::get('/projects/{project}', [PortalProjectController::class, 'show'])->name('projects.show');
        Route::get('/projects/{project}/files/{projectFile}/download', [PortalProjectController::class, 'downloadFile'])->name('projects.files.download');
        Route::get('/invoices/{invoice}/download', [PortalInvoiceController::class, 'download'])->name('invoices.download');
    });

/*
|--------------------------------------------------------------------------
| Stripe Webhook (no auth, no CSRF)
|--------------------------------------------------------------------------
*/
Route::post('/webhooks/stripe', [WebhookController::class, 'handleStripe'])->name('webhooks.stripe');

// Fallback Inertia route for 404s, etc. (optional)
Route::fallback(function () {
    return Inertia::render('Errors/NotFound');
});
