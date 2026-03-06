<?php

namespace Tests\Feature\Billing;

use App\Models\Client;
use App\Models\Organization;
use App\Models\OrganizationSubscription;
use App\Models\User;
use App\Services\Billing\SeatCounter;
use App\Support\PlanCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeatCounterTest extends TestCase
{
    use RefreshDatabase;

    private SeatCounter $seatCounter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seatCounter = app(SeatCounter::class);
    }

    public function test_counts_only_active_tenant_users_in_organization_user(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        $user1 = User::factory()->create(['client_id' => null]);
        $user2 = User::factory()->create(['client_id' => null]);
        $org->users()->attach([$user1->id => ['is_owner' => true], $user2->id => ['is_owner' => false]]);

        $count = $this->seatCounter->countActiveSeats($org);

        $this->assertSame(2, $count);
    }

    public function test_excludes_portal_users_from_seat_count(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        $client = Client::factory()->create(['organization_id' => $org->id]);
        $staff = User::factory()->create(['client_id' => null]);
        $portalUser = User::factory()->create(['client_id' => $client->id]);
        $org->users()->attach([$staff->id => ['is_owner' => true], $portalUser->id => ['is_owner' => false]]);

        $count = $this->seatCounter->countActiveSeats($org);

        $this->assertSame(1, $count);
    }

    public function test_can_add_seat_when_under_included_seats(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        OrganizationSubscription::query()->create([
            'organization_id' => $org->id,
            'plan_key' => PlanCatalog::PLAN_STARTER,
            'status' => 'active',
            'seats_included' => 5,
        ]);
        $user = User::factory()->create(['client_id' => null]);
        $org->users()->attach($user->id, ['is_owner' => true]);

        $this->assertTrue($this->seatCounter->canAddSeat($org));
        $this->assertTrue($org->canAddSeat());
    }

    public function test_cannot_add_seat_when_at_seat_limit(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        OrganizationSubscription::query()->create([
            'organization_id' => $org->id,
            'plan_key' => PlanCatalog::PLAN_STARTER,
            'status' => 'active',
            'seats_included' => 2,
        ]);
        $u1 = User::factory()->create(['client_id' => null]);
        $u2 = User::factory()->create(['client_id' => null]);
        $org->users()->attach([$u1->id => ['is_owner' => true], $u2->id => ['is_owner' => false]]);

        $this->assertFalse($this->seatCounter->canAddSeat($org));
        $this->assertFalse($org->canAddSeat());
    }

    public function test_can_add_seat_respects_optional_seat_limit_override(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        OrganizationSubscription::query()->create([
            'organization_id' => $org->id,
            'plan_key' => PlanCatalog::PLAN_STARTER,
            'status' => 'active',
            'seats_included' => 10,
            'seat_limit' => 3,
        ]);
        $u1 = User::factory()->create(['client_id' => null]);
        $u2 = User::factory()->create(['client_id' => null]);
        $org->users()->attach([$u1->id => ['is_owner' => true], $u2->id => ['is_owner' => false]]);

        $this->assertTrue($this->seatCounter->canAddSeat($org));

        $u3 = User::factory()->create(['client_id' => null]);
        $org->users()->attach($u3->id, ['is_owner' => false]);

        $this->assertFalse($this->seatCounter->canAddSeat($org));
    }

    public function test_tenant_scoping_only_counts_users_for_that_org(): void
    {
        $orgA = Organization::factory()->create(['slug' => 'org-a']);
        $orgB = Organization::factory()->create(['slug' => 'org-b']);
        $user = User::factory()->create(['client_id' => null]);
        $orgA->users()->attach($user->id, ['is_owner' => true]);

        $this->assertSame(1, $this->seatCounter->countActiveSeats($orgA));
        $this->assertSame(0, $this->seatCounter->countActiveSeats($orgB));
    }
}
