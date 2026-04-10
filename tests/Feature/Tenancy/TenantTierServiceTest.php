<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Models\Client;
use App\Models\Organization;
use App\Models\Project;
use App\Models\WebhookEventSummary;
use App\Services\Tenancy\TenantTierService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

final class TenantTierServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_below_medium_client_threshold_is_small(): void
    {
        $org = Organization::factory()->create();
        Client::factory()->count(24)->create(['organization_id' => $org->id]);

        $tier = app(TenantTierService::class)->determineTier($org);

        $this->assertSame(TenantTierService::TIER_SMALL, $tier);
    }

    public function test_exact_medium_client_threshold_is_medium(): void
    {
        $org = Organization::factory()->create();
        Client::factory()->count(25)->create(['organization_id' => $org->id]);

        $tier = app(TenantTierService::class)->determineTier($org);

        $this->assertSame(TenantTierService::TIER_MEDIUM, $tier);
    }

    public function test_highest_dimension_wins(): void
    {
        $org = Organization::factory()->create();
        Client::factory()->count(1)->create(['organization_id' => $org->id]);
        $client = Client::where('organization_id', $org->id)->first();
        $this->assertNotNull($client);

        Project::factory()->count(200)->create([
            'organization_id' => $org->id,
            'client_id' => $client->id,
        ]);

        $tier = app(TenantTierService::class)->determineTier($org);

        $this->assertSame(TenantTierService::TIER_ENTERPRISE, $tier);
    }

    public function test_metrics_isolated_per_organization(): void
    {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();

        Client::factory()->count(100)->create(['organization_id' => $orgA->id]);
        Client::factory()->count(2)->create(['organization_id' => $orgB->id]);

        $svc = app(TenantTierService::class);
        $this->assertSame(TenantTierService::TIER_LARGE, $svc->determineTier($orgA));
        $this->assertSame(TenantTierService::TIER_SMALL, $svc->determineTier($orgB));
    }

    public function test_recalculate_tier_persists_when_changed(): void
    {
        $org = Organization::factory()->create();
        $this->assertSame('small', $org->tier);

        Client::factory()->count(30)->create(['organization_id' => $org->id]);
        $tier = app(TenantTierService::class)->recalculateTier($org);

        $this->assertSame(TenantTierService::TIER_MEDIUM, $tier);
        $org->refresh();
        $this->assertSame(TenantTierService::TIER_MEDIUM, $org->tier);
    }

    public function test_webhook_summary_total_can_raise_tier(): void
    {
        $org = Organization::factory()->create();

        WebhookEventSummary::query()->create([
            'organization_scope' => (string) $org->id,
            'organization_id' => $org->id,
            'provider' => 'stripe',
            'event_type' => 'invoice.payment_succeeded',
            'total_count' => 5000,
            'success_count' => 5000,
            'failure_count' => 0,
        ]);

        $tier = app(TenantTierService::class)->determineTier($org);

        $this->assertSame(TenantTierService::TIER_LARGE, $tier);
    }

    public function test_artisan_recalculate_single_org(): void
    {
        $org = Organization::factory()->create();
        Client::factory()->count(500)->create(['organization_id' => $org->id]);

        Artisan::call('tenant-tiers:recalculate', ['--org' => (string) $org->id]);

        $org->refresh();
        $this->assertSame(TenantTierService::TIER_ENTERPRISE, $org->tier);
    }
}
