<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        \Illuminate\Support\Facades\DB::transaction(function (): void {
            // 1) Organization (tenant/team)
            $org = \App\Models\Organization::firstOrCreate(
                ['slug' => 'acme'],
                ['name' => 'ACME Digital', 'plan' => 'trial', 'settings' => []]
            );

            // 2) Spatie team context (CRITICAL for model_has_roles.team_id)
            $registrar = app(\Spatie\Permission\PermissionRegistrar::class);
            $registrar->forgetCachedPermissions();
            $registrar->setPermissionsTeamId($org->id);

            // 3) Roles (guard: web)
            $owner = \Spatie\Permission\Models\Role::findOrCreate('Owner', 'web');
            $admin = \Spatie\Permission\Models\Role::findOrCreate('Admin', 'web');
            $pm = \Spatie\Permission\Models\Role::findOrCreate('PM', 'web');
            $am = \Spatie\Permission\Models\Role::findOrCreate('AM', 'web');
            $fr = \Spatie\Permission\Models\Role::findOrCreate('Fronter', 'web');
            $cl = \Spatie\Permission\Models\Role::findOrCreate('Closer', 'web');

            // 4) Department (CST) — match your schema (organization_id + code unique; no description column)
            $cst = \App\Models\Department::updateOrCreate(
                ['organization_id' => $org->id, 'code' => 'CST'],
                ['name' => 'Customer Success']
            );

            // 5) Demo users
            $password = \Illuminate\Support\Facades\Hash::make('password');

            $ownerUser = \App\Models\User::firstOrCreate(
                ['email' => 'owner@acme.test'],
                [
                    'name' => 'Owner One',
                    'password' => $password,
                    'active_organization_id' => $org->id,
                    'department_id' => $cst->id,
                    'email_verified_at' => now(),
                ]
            );

            $adminUser = \App\Models\User::firstOrCreate(
                ['email' => 'admin@acme.test'],
                [
                    'name' => 'Admin One',
                    'password' => $password,
                    'active_organization_id' => $org->id,
                    'department_id' => $cst->id,
                    'email_verified_at' => now(),
                ]
            );

            $pmUser = \App\Models\User::firstOrCreate(
                ['email' => 'pm@acme.test'],
                [
                    'name' => 'Project Manager',
                    'password' => $password,
                    'active_organization_id' => $org->id,
                    'department_id' => $cst->id,
                    'email_verified_at' => now(),
                ]
            );

            $amUser = \App\Models\User::firstOrCreate(
                ['email' => 'am@acme.test'],
                [
                    'name' => 'Account Manager',
                    'password' => $password,
                    'active_organization_id' => $org->id,
                    'department_id' => $cst->id,
                    'email_verified_at' => now(),
                ]
            );

            $frUser = \App\Models\User::firstOrCreate(
                ['email' => 'fronter@acme.test'],
                [
                    'name' => 'Fronter',
                    'password' => $password,
                    'active_organization_id' => $org->id,
                    'department_id' => $cst->id,
                    'email_verified_at' => now(),
                ]
            );

            $clUser = \App\Models\User::firstOrCreate(
                ['email' => 'closer@acme.test'],
                [
                    'name' => 'Closer',
                    'password' => $password,
                    'active_organization_id' => $org->id,
                    'department_id' => $cst->id,
                    'email_verified_at' => now(),
                ]
            );

            // 6) Assign roles (team already set → writes team_id)
            $ownerUser->assignRole($owner);
            $adminUser->assignRole($admin);
            $pmUser->assignRole($pm);
            $amUser->assignRole($am);
            $frUser->assignRole($fr);
            $clUser->assignRole($cl);

            // 7) Clients (canonical field per migrations/spec)
            $clients = \App\Models\Client::factory()->count(3)->create([
                'organization_id' => $org->id,
                'client_activation_status' => 'Activated',
            ]);

            // 8) Projects & Tasks (ensure NOT NULL review_status is set)
            foreach ($clients as $c) {
                $project = \App\Models\Project::factory()->create([
                    'organization_id' => $org->id,
                    'client_id' => $c->id,
                    'project_manager_id' => $pmUser->id,
                    'department_id' => $cst->id,
                    'status' => 'Active',
                    'price' => 1500,
                ]);

                \App\Models\Task::factory()->count(3)->create([
                    'organization_id' => $org->id,
                    'project_id' => $project->id,
                    'status' => 'Open',
                    'review_status' => 'Pending',
                ]);
            }
        });
    }
}
