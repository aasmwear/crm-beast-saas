<?php

namespace Tests\Feature\Billing;

use App\Models\Organization;
use App\Models\OrganizationAddon;
use App\Models\OrganizationSubscription;
use App\Models\User;
use App\Services\Billing\EntitlementsService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class BillingWriteModelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_authorized_user_can_change_plan_key(): void
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

        $response = $this->actingAs($owner)
            ->patch(route('billing.plan.update', ['organization' => $org->slug]), [
                'plan_key' => 'pro',
            ]);

        $response->assertRedirect(route('billing.index', ['organization' => $org->slug]));
        $this->assertDatabaseHas('organization_subscriptions', [
            'organization_id' => $org->id,
            'plan_key' => 'pro',
            'seats_included' => 25,
        ]);
    }

    public function test_unauthorized_user_gets_403_on_plan_mutation(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        $employee = User::factory()->create(['active_organization_id' => $org->id, 'client_id' => null]);
        $org->users()->attach($employee->id, ['is_owner' => false]);

        $employeeRole = Role::where('name', 'Employee')->whereNull('team_id')->first();
        app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);
        $employee->assignRole($employeeRole);

        $response = $this->actingAs($employee)
            ->patch(route('billing.plan.update', ['organization' => $org->slug]), [
                'plan_key' => 'pro',
            ]);

        $response->assertStatus(403);
    }

    public function test_authorized_user_can_create_addon(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        $owner = User::factory()->create(['active_organization_id' => $org->id, 'client_id' => null]);
        $org->users()->attach($owner->id, ['is_owner' => true]);

        $ownerRole = Role::where('name', 'Owner')->whereNull('team_id')->first();
        app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);
        $owner->assignRole($ownerRole);

        $response = $this->actingAs($owner)
            ->post(route('billing.addons.store', ['organization' => $org->slug]), [
                'addon_key' => 'storage_gb',
                'mode' => 'augment',
                'quantity' => 1,
                'value_int' => 10,
                'active' => true,
            ]);

        $response->assertRedirect(route('billing.index', ['organization' => $org->slug]));
        $this->assertDatabaseHas('organization_addons', [
            'organization_id' => $org->id,
            'addon_key' => 'storage_gb',
            'mode' => 'augment',
            'value_int' => 10,
            'active' => true,
        ]);
    }

    public function test_authorized_user_can_update_addon_mode_value_active(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        $owner = User::factory()->create(['active_organization_id' => $org->id, 'client_id' => null]);
        $org->users()->attach($owner->id, ['is_owner' => true]);

        $ownerRole = Role::where('name', 'Owner')->whereNull('team_id')->first();
        app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);
        $owner->assignRole($ownerRole);

        $addon = OrganizationAddon::create([
            'organization_id' => $org->id,
            'addon_key' => 'storage_gb',
            'mode' => 'augment',
            'quantity' => 1,
            'value_int' => 10,
            'active' => true,
        ]);

        $response = $this->actingAs($owner)
            ->patch(route('billing.addons.update', ['organization' => $org->slug, 'addon' => $addon->id]), [
                'mode' => 'set',
                'value_int' => 100,
                'active' => true,
            ]);

        $response->assertRedirect(route('billing.index', ['organization' => $org->slug]));
        $addon->refresh();
        $this->assertSame('set', $addon->mode);
        $this->assertSame(100, $addon->value_int);
    }

    public function test_authorized_user_can_deactivate_addon(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        $owner = User::factory()->create(['active_organization_id' => $org->id, 'client_id' => null]);
        $org->users()->attach($owner->id, ['is_owner' => true]);

        $ownerRole = Role::where('name', 'Owner')->whereNull('team_id')->first();
        app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);
        $owner->assignRole($ownerRole);

        $addon = OrganizationAddon::create([
            'organization_id' => $org->id,
            'addon_key' => 'storage_gb',
            'mode' => 'augment',
            'quantity' => 1,
            'value_int' => 10,
            'active' => true,
        ]);

        $response = $this->actingAs($owner)
            ->delete(route('billing.addons.destroy', ['organization' => $org->slug, 'addon' => $addon->id]));

        $response->assertRedirect(route('billing.index', ['organization' => $org->slug]));
        $addon->refresh();
        $this->assertFalse($addon->active);
    }

    public function test_changes_affect_entitlements_resolution(): void
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

        $entitlements = app(EntitlementsService::class)->forOrg($org);
        $this->assertSame(5, $entitlements['storage_gb'] ?? 0);

        OrganizationAddon::create([
            'organization_id' => $org->id,
            'addon_key' => 'storage_gb',
            'mode' => 'augment',
            'quantity' => 1,
            'value_int' => 10,
            'active' => true,
        ]);

        app(EntitlementsService::class)->clearCache($org);
        $entitlements = app(EntitlementsService::class)->forOrg($org);
        $this->assertSame(15, $entitlements['storage_gb'] ?? 0);
    }

    public function test_org_b_cannot_mutate_org_a_addon(): void
    {
        $orgA = Organization::factory()->create(['slug' => 'org-a']);
        $orgB = Organization::factory()->create(['slug' => 'org-b']);
        $ownerB = User::factory()->create(['active_organization_id' => $orgB->id, 'client_id' => null]);
        $orgB->users()->attach($ownerB->id, ['is_owner' => true]);

        $ownerRole = Role::where('name', 'Owner')->whereNull('team_id')->first();
        app(PermissionRegistrar::class)->setPermissionsTeamId($orgB->id);
        $ownerB->assignRole($ownerRole);

        $addonA = OrganizationAddon::create([
            'organization_id' => $orgA->id,
            'addon_key' => 'storage_gb',
            'mode' => 'augment',
            'quantity' => 1,
            'value_int' => 10,
            'active' => true,
        ]);

        $response = $this->actingAs($ownerB)
            ->patch(route('billing.addons.update', ['organization' => $orgB->slug, 'addon' => $addonA->id]), [
                'mode' => 'set',
                'value_int' => 999,
            ]);

        $response->assertStatus(404);
        $addonA->refresh();
        $this->assertSame('augment', $addonA->mode);
        $this->assertSame(10, $addonA->value_int);
    }

    public function test_unauthorized_user_gets_403_on_addon_create(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        $employee = User::factory()->create(['active_organization_id' => $org->id, 'client_id' => null]);
        $org->users()->attach($employee->id, ['is_owner' => false]);

        $employeeRole = Role::where('name', 'Employee')->whereNull('team_id')->first();
        app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);
        $employee->assignRole($employeeRole);

        $response = $this->actingAs($employee)
            ->post(route('billing.addons.store', ['organization' => $org->slug]), [
                'addon_key' => 'storage_gb',
                'mode' => 'augment',
                'value_int' => 10,
            ]);

        $response->assertStatus(403);
    }
}
