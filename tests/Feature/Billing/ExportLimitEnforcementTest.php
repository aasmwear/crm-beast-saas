<?php

namespace Tests\Feature\Billing;

use App\Models\Client;
use App\Models\Organization;
use App\Models\Platform\OrganizationFeature;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

final class ExportLimitEnforcementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Cache::flush();
    }

    public function test_export_enforcement_blocks_when_daily_limit_exceeded(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        $owner = $this->attachUserWithRole($org, 'Owner');

        OrganizationFeature::query()->create([
            'organization_id' => $org->id,
            'features' => [
                'attendance' => true,
                'sms' => false,
                'api_access' => true,
                'storage_gb' => 5,
                'api_rpm' => 60,
                'exports_per_day' => 1,
            ],
            'subscription_status' => 'active',
        ]);

        Client::factory()->create([
            'organization_id' => $org->id,
            'company_name' => 'Alpha Co',
        ]);

        $route = route('export.csv', [
            'organization' => $org->slug,
            'entity' => 'clients',
        ]);

        $this->actingAs($owner)->get($route)->assertOk();

        $this->actingAs($owner)->get($route)
            ->assertStatus(429)
            ->assertSeeText('Daily export limit reached for your plan.');
    }

    private function attachUserWithRole(Organization $org, string $roleName): User
    {
        $user = User::factory()->create([
            'active_organization_id' => $org->id,
            'client_id' => null,
        ]);

        $org->users()->attach($user->id, ['is_owner' => true]);

        $role = Role::where('name', $roleName)->whereNull('team_id')->first();
        app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);
        $user->assignRole($role);

        return $user;
    }
}
