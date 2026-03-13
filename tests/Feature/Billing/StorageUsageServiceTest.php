<?php

namespace Tests\Feature\Billing;

use App\Models\Client;
use App\Models\Organization;
use App\Models\Platform\OrganizationFeature;
use App\Models\Project;
use App\Models\User;
use App\Services\Billing\StorageUsageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class StorageUsageServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_storage_service_returns_canonical_limit_from_entitlements(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        OrganizationFeature::query()->create([
            'organization_id' => $org->id,
            'features' => [
                'attendance' => true,
                'sms' => false,
                'api_access' => true,
                'storage_gb' => 9,
                'api_rpm' => 60,
                'exports_per_day' => 5,
            ],
            'subscription_status' => 'active',
        ]);

        $service = app(StorageUsageService::class);

        $this->assertSame(9, $service->limitGb($org));
    }

    public function test_storage_over_limit_logic_works(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        OrganizationFeature::query()->create([
            'organization_id' => $org->id,
            'features' => [
                'attendance' => true,
                'sms' => false,
                'api_access' => true,
                'storage_gb' => 1,
                'api_rpm' => 60,
                'exports_per_day' => 5,
            ],
            'subscription_status' => 'active',
        ]);

        $client = Client::factory()->create(['organization_id' => $org->id]);
        $uploader = User::factory()->create(['active_organization_id' => $org->id]);
        $project = Project::factory()->create([
            'organization_id' => $org->id,
            'client_id' => $client->id,
        ]);

        DB::table('project_files')->insert([
            'organization_id' => $org->id,
            'project_id' => $project->id,
            'user_id' => $uploader->id,
            'filename' => 'large.bin',
            'path' => 'project_files/'.$project->id.'/large.bin',
            'mime_type' => 'application/octet-stream',
            'size' => 1_073_741_824 + 10,
            'is_visible_to_client' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = app(StorageUsageService::class);

        $this->assertGreaterThan(1.0, $service->currentUsageGb($org));
        $this->assertTrue($service->isOverLimit($org));
    }

    public function test_repeated_calls_return_consistent_values_per_request(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        $client = Client::factory()->create(['organization_id' => $org->id]);
        $uploader = User::factory()->create(['active_organization_id' => $org->id]);
        $project = Project::factory()->create([
            'organization_id' => $org->id,
            'client_id' => $client->id,
        ]);

        DB::table('project_files')->insert([
            'organization_id' => $org->id,
            'project_id' => $project->id,
            'user_id' => $uploader->id,
            'filename' => 'test.bin',
            'path' => 'project_files/'.$project->id.'/test.bin',
            'mime_type' => 'application/octet-stream',
            'size' => 1000,
            'is_visible_to_client' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = app(StorageUsageService::class);

        $first = $service->currentUsageBytes($org);
        $second = $service->currentUsageBytes($org);

        $this->assertSame(1000, $first);
        $this->assertSame($first, $second, 'Repeated calls should return same value (per-request cache)');
    }
}
