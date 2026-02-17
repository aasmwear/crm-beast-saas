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
            'clients.delete',
            'clients.manage',
            'projects.create',
            'projects.view',
            'projects.edit',
            'projects.delete',
            'roles.manage',
            'roles.view',
            'roles.assign',
            'users.manage',
            'users.view',
            'users.create',
            'users.edit',
            'users.delete',
            'users.assign-roles',
            'tasks.create',
            'tasks.view',
            'tasks.edit',
            'tasks.delete',
            'messages.create',
            'financials.view',
            'contacts.manage',
            'departments.view',
            'departments.create',
            'departments.update',
            'departments.delete',
            'attendance.view',
            'attendance.view-own',
            'attendance.clock-in',
            'attendance.clock-out',
            'attendance.approve',
            'attendance.manage',
            'announcements.view',
            'announcements.create',
            'announcements.update',
            'announcements.delete',
            'announcements.pin',
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
            'projects.view', 'projects.create', 'projects.edit', 'financials.view',
            'users.view', 'clients.manage', 'clients.view', 'clients.create', 'clients.edit',
            'contacts.manage', 'tasks.view', 'tasks.create', 'tasks.edit',
            'departments.view', 'attendance.view', 'attendance.approve',
            'announcements.view', 'announcements.create', 'announcements.update',
        ]);

        $employee = Role::firstOrCreate(['name' => 'Employee', 'guard_name' => 'web']);
        $employee->syncPermissions([
            'projects.view', 'users.view', 'clients.view', 'tasks.view', 'tasks.create', 'tasks.edit',
            'attendance.view-own', 'attendance.clock-in', 'attendance.clock-out',
            'announcements.view', 'messages.create',
        ]);

        $clientRole = Role::firstOrCreate(['name' => 'Client', 'guard_name' => 'web']);
        $clientRole->syncPermissions([]);

        $this->command->info('✅ Roles and permissions created successfully!');
        $this->command->info('📊 Total Permissions: ' . Permission::count());
        $this->command->info('👥 Roles: Super Admin, Owner, Manager, Employee, Client');
    }
}
