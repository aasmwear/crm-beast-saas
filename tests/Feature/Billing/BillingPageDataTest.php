<?php

namespace Tests\Feature\Billing;

use App\Models\Organization;
use App\Models\OrganizationAddon;
use App\Models\OrganizationSubscription;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class BillingPageDataTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_billing_page_shows_canonical_subscription_data(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        $owner = User::factory()->create(['active_organization_id' => $org->id, 'client_id' => null]);
        $org->users()->attach($owner->id, ['is_owner' => true]);

        $ownerRole = Role::where('name', 'Owner')->whereNull('team_id')->first();
        app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);
        $owner->assignRole($ownerRole);

        OrganizationSubscription::create([
            'organization_id' => $org->id,
            'plan_key' => 'pro',
            'status' => 'active',
            'seats_included' => 25,
            'seat_limit' => 30,
        ]);

        $response = $this->actingAs($owner)
            ->get(route('billing.index', ['organization' => $org->slug]));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Billing/Index')
            ->has('subscription')
            ->where('subscription.plan_key', 'pro')
            ->where('subscription.status', 'active')
            ->where('subscription.seats_included', 25)
            ->where('subscription.seat_limit', 30)
        );
    }

    public function test_billing_page_uses_subscription_plan_key_over_organizations_plan(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme', 'plan' => 'starter']);
        $owner = User::factory()->create(['active_organization_id' => $org->id, 'client_id' => null]);
        $org->users()->attach($owner->id, ['is_owner' => true]);

        $ownerRole = Role::where('name', 'Owner')->whereNull('team_id')->first();
        app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);
        $owner->assignRole($ownerRole);

        OrganizationSubscription::create([
            'organization_id' => $org->id,
            'plan_key' => 'enterprise',
            'status' => 'active',
            'seats_included' => 500,
        ]);

        $response = $this->actingAs($owner)
            ->get(route('billing.index', ['organization' => $org->slug]));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Billing/Index')
            ->where('subscription.plan_key', 'enterprise')
        );
    }

    public function test_billing_page_includes_active_seat_count(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        $owner = User::factory()->create(['active_organization_id' => $org->id, 'client_id' => null]);
        $employee = User::factory()->create(['active_organization_id' => $org->id, 'client_id' => null]);
        $org->users()->attach($owner->id, ['is_owner' => true]);
        $org->users()->attach($employee->id, ['is_owner' => false]);

        $ownerRole = Role::where('name', 'Owner')->whereNull('team_id')->first();
        app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);
        $owner->assignRole($ownerRole);

        $response = $this->actingAs($owner)
            ->get(route('billing.index', ['organization' => $org->slug]));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Billing/Index')
            ->has('seats')
            ->where('seats.active_count', 2)
        );
    }

    public function test_billing_page_includes_resolved_entitlements_and_addons(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        $owner = User::factory()->create(['active_organization_id' => $org->id, 'client_id' => null]);
        $org->users()->attach($owner->id, ['is_owner' => true]);

        $ownerRole = Role::where('name', 'Owner')->whereNull('team_id')->first();
        app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);
        $owner->assignRole($ownerRole);

        OrganizationSubscription::create([
            'organization_id' => $org->id,
            'plan_key' => 'starter',
            'status' => 'active',
            'seats_included' => 5,
        ]);

        OrganizationAddon::create([
            'organization_id' => $org->id,
            'addon_key' => 'storage_gb',
            'quantity' => 1,
            'value_int' => 10,
            'mode' => 'augment',
            'active' => true,
        ]);

        $response = $this->actingAs($owner)
            ->get(route('billing.index', ['organization' => $org->slug]));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Billing/Index')
            ->has('entitlements')
            ->where('entitlements.storage_gb', 15) // 5 base + 10 augment
            ->has('addons')
            ->where('addons.0.addon_key', 'storage_gb')
            ->where('addons.0.mode', 'augment')
            ->where('addons.0.value_int', 10)
            ->where('addons.0.active', true)
        );
    }

    public function test_unauthorized_user_gets_403(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        $employee = User::factory()->create(['active_organization_id' => $org->id, 'client_id' => null]);
        $org->users()->attach($employee->id, ['is_owner' => false]);

        $employeeRole = Role::where('name', 'Employee')->whereNull('team_id')->first();
        app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);
        $employee->assignRole($employeeRole);

        $response = $this->actingAs($employee)
            ->get(route('billing.index', ['organization' => $org->slug]));

        $response->assertStatus(403);
    }
}
