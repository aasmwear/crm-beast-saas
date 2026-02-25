<?php

namespace Tests\Feature\Settings;

use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class SettingsAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $org;

    protected User $userWithSettings;

    protected User $userWithoutSettings;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->org = Organization::factory()->create(['slug' => 'acme', 'name' => 'Acme Corp']);

        $ownerRole = Role::where('name', 'Owner')->whereNull('team_id')->first();
        $clientRole = Role::where('name', 'Client')->whereNull('team_id')->first();

        $this->userWithSettings = User::factory()->create([
            'active_organization_id' => $this->org->id,
        ]);
        $this->userWithSettings->organizations()->attach($this->org);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->org->id);
        $this->userWithSettings->assignRole($ownerRole);

        $this->userWithoutSettings = User::factory()->create([
            'active_organization_id' => $this->org->id,
        ]);
        $this->userWithoutSettings->organizations()->attach($this->org);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->org->id);
        $this->userWithoutSettings->assignRole($clientRole);
    }

    public function test_user_with_settings_view_can_access_index(): void
    {
        $response = $this->actingAs($this->userWithSettings)
            ->get(route('settings.index', ['organization' => $this->org->slug]));

        $response->assertOk();
    }

    public function test_user_without_settings_view_gets_403_on_index(): void
    {
        $response = $this->actingAs($this->userWithoutSettings)
            ->get(route('settings.index', ['organization' => $this->org->slug]));

        $response->assertForbidden();
    }

    public function test_user_without_settings_update_gets_403_on_update(): void
    {
        $response = $this->actingAs($this->userWithoutSettings)
            ->post(route('settings.update', ['organization' => $this->org->slug]), [
                'name' => 'Acme Corp',
                'timezone' => 'UTC',
                'week_start' => 'Monday',
            ]);

        $response->assertForbidden();
    }
}
