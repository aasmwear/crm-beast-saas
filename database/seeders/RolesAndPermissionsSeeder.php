<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Bulletproof: create ALL permissions used in the app and assign to Super Admin + Owner.
     */
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'clients.create',
            'clients.view',
            'clients.edit',
            'clients.update',
            'clients.delete',
            'clients.manage',
            'clients.import',
            'clients.export',
            'projects.create',
            'projects.view',
            'projects.edit',
            'projects.update',
            'projects.delete',
            'projects.manage',
            'roles.manage',
            'roles.view',
            'roles.assign',
            'users.manage',
            'users.view',
            'users.create',
            'users.edit',
            'users.update',
            'users.delete',
            'users.assign-roles',
            'tasks.create',
            'tasks.view',
            'tasks.edit',
            'tasks.update',
            'tasks.delete',
            'tasks.manage',
            'tasks.review',
            'messages.create',
            'financials.view',
            'contacts.manage',
            'departments.view',
            'departments.create',
            'departments.update',
            'departments.delete',
            'attendance.view',
            'attendance.view-own',
            'attendance.create',
            'attendance.edit',
            'attendance.delete',
            'attendance.manage',
            'attendance.clock-in',
            'attendance.clock-out',
            'attendance.approve',
            'announcements.view',
            'announcements.create',
            'announcements.update',
            'announcements.delete',
            'announcements.pin',
            'reports.view',
            'reports.export',
            'activity.view',
            'notifications.view',
            'notifications.update',
            'settings.view',
            'settings.update',
            'billing.view',
            'billing.manage',
            'billing.update',
            'api_keys.view',
            'api_keys.create',
            'api_keys.delete',
            'hrm.view',
            'hrm.create',
            'hrm.edit',
            'hrm.delete',
            'hrm.manage',
            'custom-fields.manage',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $allPermissions = Permission::all()->pluck('name')->toArray();

        $superAdmin = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        $superAdmin->syncPermissions($allPermissions);

        $owner = Role::firstOrCreate(['name' => 'Owner', 'guard_name' => 'web']);
        $owner->syncPermissions($allPermissions);

        $manager = Role::firstOrCreate(['name' => 'Manager', 'guard_name' => 'web']);
        $manager->syncPermissions([
            'projects.view', 'projects.create', 'projects.edit', 'projects.update', 'financials.view',
            'users.view', 'users.create', 'users.update', 'users.delete', 'hrm.view', 'hrm.create', 'hrm.edit', 'hrm.manage',
            'clients.manage', 'clients.view', 'clients.create', 'clients.edit', 'clients.update', 'clients.import',
            'contacts.manage', 'tasks.view', 'tasks.create', 'tasks.edit', 'tasks.update',
            'departments.view', 'attendance.view', 'attendance.edit', 'attendance.manage', 'attendance.approve',
            'announcements.view', 'announcements.create', 'announcements.update',
            'reports.view', 'reports.export',
            'activity.view',
            'notifications.view', 'notifications.update',
            'settings.view', 'settings.update',
            'api_keys.view', 'api_keys.create', 'api_keys.delete',
            'custom-fields.manage',
        ]);

        $employee = Role::firstOrCreate(['name' => 'Employee', 'guard_name' => 'web']);
        $employee->syncPermissions([
            'projects.view', 'users.view', 'clients.view', 'tasks.view', 'tasks.create', 'tasks.edit', 'tasks.update',
            'attendance.view-own', 'attendance.create', 'attendance.clock-in', 'attendance.clock-out',
            'announcements.view', 'messages.create',
            'reports.view',
            'activity.view',
            'notifications.view', 'notifications.update',
            'settings.view',
        ]);

        $clientRole = Role::firstOrCreate(['name' => 'Client', 'guard_name' => 'web']);
        $clientRole->syncPermissions([]);

        if ($this->command) {
            $this->command->info('✅ Roles and permissions created successfully!');
            $this->command->info('📊 Total Permissions: ' . Permission::count());
            $this->command->info('👥 Roles: Super Admin, Owner, Manager, Employee, Client');
        }
    }
}
