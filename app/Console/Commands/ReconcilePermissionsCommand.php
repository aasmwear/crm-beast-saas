<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

final class ReconcilePermissionsCommand extends Command
{
    protected $signature = 'permissions:reconcile
                            {--assign : Assign canonical equivalents to roles that have legacy permissions}
                            {--dry-run : Show what would be done without making changes}';

    protected $description = 'Ensure canonical permissions exist and optionally assign them to roles with legacy perms';

    /**
     * Legacy -> canonical mapping. Used to create canonical perms and optionally assign to roles.
     *
     * @var array<string, string>
     */
    private const LEGACY_TO_CANONICAL = [
        'clients.edit' => 'clients.update',
        'projects.edit' => 'projects.update',
        'tasks.edit' => 'tasks.update',
        'users.edit' => 'users.update',
    ];

    public function handle(): int
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $dryRun = (bool) $this->option('dry-run');
        $assign = (bool) $this->option('assign');

        if ($dryRun) {
            $this->warn('Dry run — no changes will be made.');
        }

        $created = 0;
        foreach (self::LEGACY_TO_CANONICAL as $legacy => $canonical) {
            $exists = Permission::where('name', $canonical)->where('guard_name', 'web')->exists();
            if (! $exists) {
                if (! $dryRun) {
                    Permission::create(['name' => $canonical, 'guard_name' => 'web']);
                }
                $this->line("  <fg=green>+</> Would create: {$canonical}");
                $created++;
            }
        }

        if ($created > 0 && ! $dryRun) {
            $this->info("Created {$created} canonical permission(s).");
        } elseif ($created > 0 && $dryRun) {
            $this->info("Would create {$created} canonical permission(s).");
        } else {
            $this->info('All canonical permissions already exist.');
        }

        if (! $assign) {
            $this->newLine();
            $this->comment('Use --assign to add canonical permissions to roles that have legacy ones.');

            return self::SUCCESS;
        }

        $roles = Role::query()->get();
        $updated = 0;

        foreach ($roles as $role) {
            $legacyPerms = $role->permissions()->whereIn('name', array_keys(self::LEGACY_TO_CANONICAL))->pluck('name')->toArray();
            if (empty($legacyPerms)) {
                continue;
            }

            $toAdd = [];
            foreach ($legacyPerms as $legacy) {
                $canonical = self::LEGACY_TO_CANONICAL[$legacy];
                $hasCanonical = $role->hasPermissionTo($canonical);
                if (! $hasCanonical) {
                    $toAdd[] = $canonical;
                }
            }

            if (! empty($toAdd)) {
                $this->line("  Role <fg=cyan>{$role->name}</> (team_id: " . ($role->team_id ?? 'null') . "): add " . implode(', ', $toAdd));
                if (! $dryRun) {
                    $role->givePermissionTo($toAdd);
                }
                $updated++;
            }
        }

        if ($updated > 0 && ! $dryRun) {
            app()[PermissionRegistrar::class]->forgetCachedPermissions();
            $this->info("Updated {$updated} role(s) with canonical permissions.");
        } elseif ($updated > 0 && $dryRun) {
            $this->info("Would update {$updated} role(s) with canonical permissions.");
        } else {
            $this->info('No roles need canonical permission assignments.');
        }

        return self::SUCCESS;
    }
}
