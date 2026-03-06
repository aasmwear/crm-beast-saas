<?php

namespace Tests\Feature\Billing;

use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Organization;
use App\Models\OrganizationSubscription;
use App\Models\User;
use App\Support\PlanCatalog;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class SeatEnforcementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_allows_creating_staff_user_when_under_limit(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        OrganizationSubscription::query()->create([
            'organization_id' => $org->id,
            'plan_key' => PlanCatalog::PLAN_STARTER,
            'status' => 'active',
            'seats_included' => 5,
        ]);
        $admin = User::factory()->create(['active_organization_id' => $org->id, 'client_id' => null]);
        $org->users()->attach($admin->id, ['is_owner' => true]);
        $ownerRole = Role::where('name', 'Owner')->whereNull('team_id')->first();
        app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);
        $admin->assignRole($ownerRole);

        $response = $this->actingAs($admin)->postJson(route('hrm.store', ['organization' => $org->slug]), [
            'name' => 'New Employee',
            'email' => 'employee@acme.test',
            'role' => 'Employee',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('users', ['email' => 'employee@acme.test']);
        $this->assertDatabaseHas('organization_user', [
            'organization_id' => $org->id,
            'user_id' => User::where('email', 'employee@acme.test')->value('id'),
        ]);
    }

    public function test_blocks_creating_staff_user_when_at_limit_returns_422(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        OrganizationSubscription::query()->create([
            'organization_id' => $org->id,
            'plan_key' => PlanCatalog::PLAN_STARTER,
            'status' => 'active',
            'seats_included' => 2,
        ]);
        $admin = User::factory()->create(['active_organization_id' => $org->id, 'client_id' => null]);
        $emp1 = User::factory()->create(['client_id' => null]);
        $org->users()->attach([$admin->id => ['is_owner' => true], $emp1->id => ['is_owner' => false]]);

        $ownerRole = Role::where('name', 'Owner')->whereNull('team_id')->first();
        app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);
        $admin->assignRole($ownerRole);

        $response = $this->actingAs($admin)->postJson(route('hrm.store', ['organization' => $org->slug]), [
            'name' => 'Extra Employee',
            'email' => 'extra@acme.test',
            'role' => 'Employee',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['seats']);
        $response->assertJsonFragment(['Seat limit reached for your plan. Upgrade or add seats to invite more users.']);
        $this->assertDatabaseMissing('users', ['email' => 'extra@acme.test']);
    }

    public function test_allows_creating_portal_user_even_when_at_staff_seat_limit(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        OrganizationSubscription::query()->create([
            'organization_id' => $org->id,
            'plan_key' => PlanCatalog::PLAN_STARTER,
            'status' => 'active',
            'seats_included' => 2,
        ]);
        $admin = User::factory()->create(['active_organization_id' => $org->id, 'client_id' => null]);
        $emp1 = User::factory()->create(['client_id' => null]);
        $org->users()->attach([$admin->id => ['is_owner' => true], $emp1->id => ['is_owner' => false]]);

        $client = Client::factory()->create(['organization_id' => $org->id]);
        $contact = ClientContact::create([
            'client_id' => $client->id,
            'email' => 'portal@client.test',
            'name' => 'Portal Contact',
        ]);

        $ownerRole = Role::where('name', 'Owner')->whereNull('team_id')->first();
        app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);
        $admin->assignRole($ownerRole);

        $response = $this->actingAs($admin)->post(route('clients.contacts.portal.store', [
            'organization' => $org->slug,
            'client' => $client->id,
            'contact' => $contact->id,
        ]));

        $response->assertRedirect();
        $this->assertDatabaseHas('users', ['email' => 'portal@client.test', 'client_id' => $client->id]);
    }

    public function test_tenant_scoping_org_b_seat_limit_does_not_affect_org_a(): void
    {
        $orgA = Organization::factory()->create(['slug' => 'org-a']);
        $orgB = Organization::factory()->create(['slug' => 'org-b']);

        OrganizationSubscription::query()->create([
            'organization_id' => $orgB->id,
            'plan_key' => PlanCatalog::PLAN_STARTER,
            'status' => 'active',
            'seats_included' => 2,
        ]);
        $userB1 = User::factory()->create(['client_id' => null]);
        $userB2 = User::factory()->create(['client_id' => null]);
        $orgB->users()->attach([$userB1->id => ['is_owner' => true], $userB2->id => ['is_owner' => false]]);

        $adminA = User::factory()->create(['active_organization_id' => $orgA->id, 'client_id' => null]);
        $orgA->users()->attach($adminA->id, ['is_owner' => true]);
        $ownerRole = Role::where('name', 'Owner')->whereNull('team_id')->first();
        app(PermissionRegistrar::class)->setPermissionsTeamId($orgA->id);
        $adminA->assignRole($ownerRole);

        $response = $this->actingAs($adminA)->postJson(route('hrm.store', ['organization' => $orgA->slug]), [
            'name' => 'Org A Employee',
            'email' => 'orga@test.test',
            'role' => 'Employee',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('users', ['email' => 'orga@test.test']);
    }
}
