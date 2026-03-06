<?php

namespace Tests\Feature\Billing;

use App\Models\Organization;
use App\Models\User;
use App\Services\Billing\StripeSubscriptionService;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class StripeSubscriptionStartTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_unauthorized_user_gets_403(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        $employee = $this->attachUserWithRole($org, 'Employee', false);

        $response = $this->actingAs($employee)->postJson(
            route('billing.checkout', ['organization' => $org->slug]),
            ['plan_key' => 'pro']
        );

        $response->assertStatus(403);
    }

    public function test_missing_stripe_config_or_price_returns_safe_validation_message(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        $owner = $this->attachUserWithRole($org, 'Owner', true);

        config()->set('cashier.secret', null);
        config()->set('services.stripe.key', null);
        config()->set('billing.stripe_prices.pro', null);

        $response = $this->actingAs($owner)->postJson(
            route('billing.checkout', ['organization' => $org->slug]),
            ['plan_key' => 'pro']
        );

        $response->assertStatus(422);
        $response->assertJsonStructure(['error']);
    }

    public function test_starting_subscription_creates_or_uses_customer_and_updates_canonical_subscription(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme', 'stripe_id' => null]);
        $owner = $this->attachUserWithRole($org, 'Owner', true);

        config()->set('cashier.secret', 'sk_test_123');
        config()->set('services.stripe.key', 'pk_test_123');
        config()->set('billing.stripe_prices.pro', 'price_pro_123');

        app()->bind(StripeSubscriptionService::class, fn () => new class extends StripeSubscriptionService
        {
            public function startForPlan(Organization $organization, string $priceId, array $urls): array
            {
                if (empty($organization->stripe_id)) {
                    $organization->forceFill(['stripe_id' => 'cus_test_123'])->save();
                }

                return [
                    'mode' => 'checkout',
                    'checkout_url' => 'https://checkout.stripe.test/session_123',
                    'status' => null,
                    'trial_ends_at' => CarbonImmutable::parse('2030-01-15T00:00:00Z'),
                    'current_period_ends_at' => null,
                ];
            }
        });

        $response = $this->actingAs($owner)->postJson(
            route('billing.checkout', ['organization' => $org->slug]),
            ['plan_key' => 'pro']
        );

        $response->assertOk();
        $response->assertJsonPath('url', 'https://checkout.stripe.test/session_123');

        $org->refresh();
        $this->assertNotNull($org->stripe_id);

        $this->assertDatabaseHas('organization_subscriptions', [
            'organization_id' => $org->id,
            'plan_key' => 'pro',
            'status' => 'incomplete',
            'seats_included' => 25,
        ]);
    }

    public function test_billing_page_shows_plan_action_availability_flags(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        $owner = $this->attachUserWithRole($org, 'Owner', true);

        config()->set('cashier.secret', 'sk_test_123');
        config()->set('services.stripe.key', 'pk_test_123');
        config()->set('billing.stripe_prices.starter', null);
        config()->set('billing.stripe_prices.pro', 'price_pro_123');
        config()->set('billing.stripe_prices.enterprise', null);

        $response = $this->actingAs($owner)->get(route('billing.index', ['organization' => $org->slug]));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Billing/Index')
            ->where('canManageBilling', true)
            ->where('stripeEnabled', true)
            ->where('plans.0.id', 'starter')
            ->where('plans.0.has_stripe_price', false)
            ->where('plans.1.id', 'pro')
            ->where('plans.1.has_stripe_price', true)
            ->where('plans.2.id', 'enterprise')
            ->where('plans.2.has_stripe_price', false)
        );
    }

    private function attachUserWithRole(Organization $org, string $roleName, bool $isOwner): User
    {
        $user = User::factory()->create([
            'active_organization_id' => $org->id,
            'client_id' => null,
        ]);

        $org->users()->attach($user->id, ['is_owner' => $isOwner]);

        $role = Role::where('name', $roleName)->whereNull('team_id')->first();
        app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);
        $user->assignRole($role);

        return $user;
    }
}
