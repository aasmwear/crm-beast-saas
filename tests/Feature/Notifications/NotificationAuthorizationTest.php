<?php

namespace Tests\Feature\Notifications;

use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class NotificationAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $org;

    protected User $userWithNotifications;

    protected User $userWithoutNotifications;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->org = Organization::factory()->create(['slug' => 'acme']);

        $ownerRole = Role::where('name', 'Owner')->whereNull('team_id')->first();
        $clientRole = Role::where('name', 'Client')->whereNull('team_id')->first();

        $this->userWithNotifications = User::factory()->create([
            'active_organization_id' => $this->org->id,
        ]);
        $this->userWithNotifications->organizations()->attach($this->org);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->org->id);
        $this->userWithNotifications->assignRole($ownerRole);

        $this->userWithoutNotifications = User::factory()->create([
            'active_organization_id' => $this->org->id,
        ]);
        $this->userWithoutNotifications->organizations()->attach($this->org);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->org->id);
        $this->userWithoutNotifications->assignRole($clientRole);
    }

    public function test_user_with_notifications_view_can_access_index(): void
    {
        $response = $this->actingAs($this->userWithNotifications)
            ->get(route('notifications.index', ['organization' => $this->org->slug]));

        $response->assertOk();
    }

    public function test_user_without_notifications_view_gets_403_on_index(): void
    {
        $response = $this->actingAs($this->userWithoutNotifications)
            ->get(route('notifications.index', ['organization' => $this->org->slug]));

        $response->assertForbidden();
    }

    public function test_user_without_notifications_view_gets_403_on_list(): void
    {
        $response = $this->actingAs($this->userWithoutNotifications)
            ->getJson(route('notifications.list', ['organization' => $this->org->slug]));

        $response->assertForbidden();
    }

    public function test_user_without_notifications_update_gets_403_on_mark_all_read(): void
    {
        $response = $this->actingAs($this->userWithoutNotifications)
            ->post(route('notifications.readAll', ['organization' => $this->org->slug]));

        $response->assertForbidden();
    }
}
