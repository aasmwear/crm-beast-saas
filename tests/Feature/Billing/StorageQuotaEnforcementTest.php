<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Models\Client;
use App\Models\Organization;
use App\Models\OrganizationSubscription;
use App\Models\Platform\OrganizationFeature;
use App\Models\Project;
use App\Models\User;
use App\Services\Billing\StorageUsageService;
use App\Support\PlanCatalog;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

final class StorageQuotaEnforcementTest extends TestCase
{
    use RefreshDatabase;

    private const BYTES_PER_GB = 1_073_741_824;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_upload_allowed_under_quota(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        OrganizationSubscription::query()->create([
            'organization_id' => $org->id,
            'plan_key' => PlanCatalog::PLAN_STARTER,
            'status' => 'active',
            'seats_included' => 5,
        ]);
        $client = Client::factory()->create(['organization_id' => $org->id]);
        $project = Project::factory()->create([
            'organization_id' => $org->id,
            'client_id' => $client->id,
        ]);
        $user = User::factory()->create(['active_organization_id' => $org->id, 'client_id' => null]);
        $org->users()->attach($user->id, ['is_owner' => true]);
        $ownerRole = Role::where('name', 'Owner')->whereNull('team_id')->first();
        app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);
        $user->assignRole($ownerRole);

        $file = UploadedFile::fake()->create('document.pdf', 1024);

        $response = $this->actingAs($user)->post(
            route('projects.files.store', ['organization' => $org->slug, 'project' => $project->id]),
            ['file' => $file],
            ['Accept' => 'application/json']
        );

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('project_files', [
            'project_id' => $project->id,
            'filename' => 'document.pdf',
        ]);
    }

    public function test_upload_blocked_over_quota(): void
    {
        $org = Organization::factory()->create(['slug' => 'blocked']);
        OrganizationFeature::query()->create([
            'organization_id' => $org->id,
            'features' => [
                'attendance' => true,
                'storage_gb' => 1,
                'api_rpm' => 60,
                'exports_per_day' => 5,
            ],
            'subscription_status' => 'active',
        ]);
        $client = Client::factory()->create(['organization_id' => $org->id]);
        $project = Project::factory()->create([
            'organization_id' => $org->id,
            'client_id' => $client->id,
        ]);
        $user = User::factory()->create(['active_organization_id' => $org->id, 'client_id' => null]);
        $org->users()->attach($user->id, ['is_owner' => true]);
        $ownerRole = Role::where('name', 'Owner')->whereNull('team_id')->first();
        app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);
        $user->assignRole($ownerRole);

        DB::table('project_files')->insert([
            'project_id' => $project->id,
            'user_id' => $user->id,
            'filename' => 'existing.bin',
            'path' => 'project_files/' . $project->id . '/existing.bin',
            'mime_type' => 'application/octet-stream',
            'size' => self::BYTES_PER_GB,
            'is_visible_to_client' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $file = UploadedFile::fake()->create('extra.bin', 1);

        $response = $this->actingAs($user)->post(
            route('projects.files.store', ['organization' => $org->slug, 'project' => $project->id]),
            ['file' => $file],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['file']);
        $response->assertJsonFragment(['Storage limit reached for your plan.']);
        $this->assertDatabaseCount('project_files', 1);
    }

    public function test_org_with_larger_storage_gb_can_upload_larger_files(): void
    {
        $smallOrg = Organization::factory()->create(['slug' => 'small']);
        OrganizationFeature::query()->create([
            'organization_id' => $smallOrg->id,
            'features' => [
                'attendance' => true,
                'storage_gb' => 1,
                'api_rpm' => 60,
                'exports_per_day' => 5,
            ],
            'subscription_status' => 'active',
        ]);

        $largeOrg = Organization::factory()->create(['slug' => 'large']);
        OrganizationSubscription::query()->create([
            'organization_id' => $largeOrg->id,
            'plan_key' => PlanCatalog::PLAN_PRO,
            'status' => 'active',
            'seats_included' => 25,
        ]);

        $smallClient = Client::factory()->create(['organization_id' => $smallOrg->id]);
        $smallProject = Project::factory()->create([
            'organization_id' => $smallOrg->id,
            'client_id' => $smallClient->id,
        ]);
        $smallUser = User::factory()->create(['active_organization_id' => $smallOrg->id, 'client_id' => null]);
        $smallOrg->users()->attach($smallUser->id, ['is_owner' => true]);

        $largeClient = Client::factory()->create(['organization_id' => $largeOrg->id]);
        $largeProject = Project::factory()->create([
            'organization_id' => $largeOrg->id,
            'client_id' => $largeClient->id,
        ]);
        $largeUser = User::factory()->create(['active_organization_id' => $largeOrg->id, 'client_id' => null]);
        $largeOrg->users()->attach($largeUser->id, ['is_owner' => true]);

        $ownerRole = Role::where('name', 'Owner')->whereNull('team_id')->first();
        app(PermissionRegistrar::class)->setPermissionsTeamId($smallOrg->id);
        $smallUser->assignRole($ownerRole);
        app(PermissionRegistrar::class)->setPermissionsTeamId($largeOrg->id);
        $largeUser->assignRole($ownerRole);

        DB::table('project_files')->insert([
            'project_id' => $smallProject->id,
            'user_id' => $smallUser->id,
            'filename' => 'existing.bin',
            'path' => 'project_files/' . $smallProject->id . '/existing.bin',
            'mime_type' => 'application/octet-stream',
            'size' => self::BYTES_PER_GB,
            'is_visible_to_client' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $overQuotaFile = UploadedFile::fake()->create('over.bin', 1024);

        $smallResponse = $this->actingAs($smallUser)->post(
            route('projects.files.store', ['organization' => $smallOrg->slug, 'project' => $smallProject->id]),
            ['file' => $overQuotaFile],
            ['Accept' => 'application/json']
        );

        $smallResponse->assertStatus(422);
        $smallResponse->assertJsonValidationErrors(['file']);

        $withinQuotaFile = UploadedFile::fake()->create('within.bin', 5120);

        $largeResponse = $this->actingAs($largeUser)->post(
            route('projects.files.store', ['organization' => $largeOrg->slug, 'project' => $largeProject->id]),
            ['file' => $withinQuotaFile],
            ['Accept' => 'application/json']
        );

        $largeResponse->assertRedirect();
        $largeResponse->assertSessionHas('success');
    }
}
