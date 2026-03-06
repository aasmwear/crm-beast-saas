<?php

namespace Tests\Feature\Billing;

use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class BillingPageDoesNotCrashTest extends TestCase
{
    use RefreshDatabase;

    public function test_billing_page_returns_200_without_500(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $org = Organization::factory()->create(['slug' => 'acme']);
        $owner = User::factory()->create(['active_organization_id' => $org->id, 'client_id' => null]);
        $org->users()->attach($owner->id, ['is_owner' => true]);

        $ownerRole = Role::where('name', 'Owner')->whereNull('team_id')->first();
        app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);
        $owner->assignRole($ownerRole);

        $response = $this->actingAs($owner)
            ->get(route('billing.index', ['organization' => $org->slug]));

        $response->assertStatus(200);
    }
}
