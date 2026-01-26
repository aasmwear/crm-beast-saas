<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
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
