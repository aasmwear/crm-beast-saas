<?php

namespace Tests\Feature\Billing;

use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class BillingPortalAndInvoicesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_unauthorized_user_cannot_access_billing_portal_action(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        $employee = $this->attachUserWithRole($org, 'Employee', false);

        $response = $this->actingAs($employee)
            ->get(route('billing.portal', ['organization' => $org->slug]));

        $response->assertStatus(403);
    }

    public function test_billing_portal_action_fails_safely_when_stripe_not_configured_or_customer_missing(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme', 'stripe_id' => null]);
        $owner = $this->attachUserWithRole($org, 'Owner', true);

        config()->set('cashier.secret', null);
        config()->set('services.stripe.key', null);

        $missingConfigResponse = $this->actingAs($owner)
            ->from(route('billing.index', ['organization' => $org->slug]))
            ->get(route('billing.portal', ['organization' => $org->slug]));

        $missingConfigResponse->assertRedirect(route('billing.index', ['organization' => $org->slug]));
        $missingConfigResponse->assertSessionHas('error', 'Stripe is not configured.');

        config()->set('cashier.secret', 'sk_test_123');
        config()->set('services.stripe.key', 'pk_test_123');

        $missingCustomerResponse = $this->actingAs($owner)
            ->from(route('billing.index', ['organization' => $org->slug]))
            ->get(route('billing.portal', ['organization' => $org->slug]));

        $missingCustomerResponse->assertRedirect(route('billing.index', ['organization' => $org->slug]));
        $missingCustomerResponse->assertSessionHas('error', 'No Stripe billing customer is linked to this organization yet.');
    }

    public function test_billing_page_includes_normalized_invoice_data_when_available(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        $owner = $this->attachUserWithRole($org, 'Owner', true);

        config()->set('billing.invoice_overrides', [
            [
                'id' => 'in_test_001',
                'number' => 'INV-001',
                'total_minor' => 7900,
                'subtotal_minor' => 7000,
                'currency' => 'USD',
                'status' => 'paid',
                'created_at' => '2026-03-01T00:00:00Z',
                'period_start' => null,
                'period_end' => null,
                'hosted_invoice_url' => 'https://invoice.stripe.com/i/acct_test/invst_test',
                'invoice_pdf' => 'https://pay.stripe.com/invoice/acct_test/pdf',
                'receipt_url' => 'https://pay.stripe.com/receipts/acct_test/rcpt_test',
            ],
        ]);

        $response = $this->actingAs($owner)
            ->get(route('billing.index', ['organization' => $org->slug]));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Billing/Index')
            ->has('invoices', 1)
            ->where('invoices.0.id', 'in_test_001')
            ->where('invoices.0.number', 'INV-001')
            ->where('invoices.0.total_minor', 7900)
            ->where('invoices.0.currency', 'USD')
            ->where('invoices.0.status', 'Paid')
            ->where('invoices.0.hosted_invoice_url', 'https://invoice.stripe.com/i/acct_test/invst_test')
            ->where('invoices.0.invoice_pdf', 'https://pay.stripe.com/invoice/acct_test/pdf')
            ->where('invoices.0.receipt_url', 'https://pay.stripe.com/receipts/acct_test/rcpt_test')
        );
    }

    public function test_billing_page_handles_empty_invoice_list_safely(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        $owner = $this->attachUserWithRole($org, 'Owner', true);

        config()->set('billing.invoice_overrides', []);

        $response = $this->actingAs($owner)
            ->get(route('billing.index', ['organization' => $org->slug]));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Billing/Index')
            ->has('invoices', 0)
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
