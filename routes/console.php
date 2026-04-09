<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Schema;

Artisan::command('inspire', function (): void {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('user:super-admin {email} {--name=} {--password=} {--attach-all}', function (): int {
    $email = (string) $this->argument('email');
    $name = (string) ($this->option('name') ?: 'Super Admin');
    $password = $this->option('password');

    if (! Schema::hasColumn('users', 'is_super_admin')) {
        $this->error('Column users.is_super_admin not found. Run migrations first.');

        return 1;
    }

    if (! is_string($password) || $password === '') {
        // Local/dev convenience default.
        $password = 'password';
    }

    /** @var User $user */
    $user = User::query()->firstOrNew(['email' => $email]);
    $isNew = ! $user->exists;

    $user->name = $name;
    $user->email = $email;
    $user->password = Hash::make($password);

    // Mark verified + super admin
    $user->email_verified_at = now();
    $user->is_super_admin = true;

    $user->save();

    // Optional: attach to all orgs so org switcher / org-based UI can list them.
    if ((bool) $this->option('attach-all')) {
        $orgIds = Organization::query()->pluck('id')->all();

        if (count($orgIds) > 0) {
            $user->organizations()->syncWithoutDetaching($orgIds);

            // PHPStan treats active_organization_id as int (non-null), so we normalize via cast.
            $activeOrgId = (int) $user->active_organization_id;

            if ($activeOrgId <= 0) {
                $user->active_organization_id = (int) $orgIds[0];
                $user->saveQuietly();
            }
        }
    }

    $this->info(($isNew ? 'Created' : 'Updated').' Super Admin: '.$email);
    $this->line('Login with password: '.$password);

    return 0;
})->purpose('Create or update a Super Admin user (bypasses all authorization)');

/*
|--------------------------------------------------------------------------
| Lifecycle Prune Schedule (Phase 2)
|--------------------------------------------------------------------------
|
| Prune cold-data tables daily. Each command defaults to dry-run; --execute
| triggers actual deletion. Operators must ensure cron runs schedule:run.
| Run manually with dry-run first: php artisan lifecycle:prune-webhooks
|
*/
Schedule::command('lifecycle:prune-webhooks', ['--execute'])->dailyAt('02:00');
/*
| Stripe webhook read-model: rebuild denormalized summaries after webhook row prune so aggregates
| match retained stripe_webhook_events. Idempotent; safe to re-run manually.
*/
Schedule::command('webhooks:rebuild-summaries')->dailyAt('02:30');
/*
| Stripe webhook daily rollups: time-window read model (calendar days in app timezone).
| Runs after summary rebuild; 02:40 UTC so it follows prune + lifetime summary in typical UTC schedules.
*/
Schedule::command('webhooks:rebuild-daily-rollups')
    ->dailyAt('02:40')
    ->timezone('UTC');
Schedule::command('lifecycle:prune-failed', ['--execute'])->dailyAt('02:05');
Schedule::command('lifecycle:prune-batches', ['--execute'])->dailyAt('02:10');
/*
| Notification lifecycle prunes (warm tables, delete-only — no archive in this release).
| Run after other daily prunes. Keeps `notifications` / `notification_events` bounded.
| See docs/DATA_LIFECYCLE.md for read vs unread rules on `notifications`.
*/
Schedule::command('lifecycle:prune-notifications', ['--execute'])->dailyAt('02:15');
Schedule::command('lifecycle:prune-notification-events', ['--execute'])->dailyAt('02:20');

/*
|--------------------------------------------------------------------------
| Lifecycle Archive (Phase 3)
|--------------------------------------------------------------------------
|
| Warm-table archival is scheduled so hot `audit_logs` and `activities` do not grow
| without bound. Rows older than the retention window (90 days on `created_at` from
| config/lifecycle.php) move to `audit_logs_archive` / `activities_archive`.
|
| Why weekly: eligibility is time-based (90 days); most weeks add a small slice of new
| archivable rows. Weekly runs spread database load while keeping hot tables bounded.
| Sunday 03:00 UTC targets a typically low-traffic window (operators should confirm
| against their traffic profile).
|
| Safety: manual invocations remain dry-run unless `--execute` is passed. Scheduled
| entries pass `--execute` only here. `WarmTableArchiver` processes in batches (default
| `--batch=500`), is idempotent, and supports `--organization=` for scoped manual runs.
|
*/
Schedule::command('lifecycle:archive-audit-logs', ['--execute'])
    ->weeklyOn(0, '3:00')
    ->timezone('UTC');

Schedule::command('lifecycle:archive-activities', ['--execute'])
    ->weeklyOn(0, '3:00')
    ->timezone('UTC');

/*
| Comments archive (warm, 180-day retention on created_at → comments_archive).
| Scheduled weekly at 03:30 UTC (after audit_logs / activities at 03:00) to stagger I/O.
| Same safety model: CLI default is dry-run; scheduled entry passes --execute only here.
| Hot `comments` is what project pages read — archived rows are not shown until a future read path exists.
*/
Schedule::command('lifecycle:archive-comments', ['--execute'])
    ->weeklyOn(0, '3:30')
    ->timezone('UTC');

/*
|--------------------------------------------------------------------------
| Metrics Snapshot Schedule
|--------------------------------------------------------------------------
|
| Daily org metrics snapshot. Runs after midnight to capture the previous
| day's data. Idempotent upsert; safe to re-run.
|
*/
Schedule::command('metrics:snapshot-orgs')->dailyAt('01:00');
