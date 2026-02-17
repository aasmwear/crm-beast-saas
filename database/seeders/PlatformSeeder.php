<?php

namespace Database\Seeders;

use App\Models\Platform\PlatformAdmin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Platform Seeder
 * 
 * Seeds initial Super Admin account for platform administration.
 */
class PlatformSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create Super Admin
        PlatformAdmin::firstOrCreate(
            ['email' => 'admin@crmbeast.com'],
            [
                'name' => 'CRM Beast Super Admin',
                'password' => Hash::make('password'),
                'role' => 'super_admin',
                'is_active' => true,
            ]
        );

        $this->command->info('✓ Platform Super Admin created: admin@crmbeast.com / password');
    }
}
