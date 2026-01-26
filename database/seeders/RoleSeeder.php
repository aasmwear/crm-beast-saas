<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $guard = config('auth.defaults.guard', 'web');

        // Create roles if they don't exist (safe to re-run)
        foreach ([
            'Owner',
            'Admin',
            'PM',
            'AM',
            'Sales',
            'Tech',
            'Viewer',
        ] as $role) {
            Role::findOrCreate($role, $guard);
        }
    }
}
