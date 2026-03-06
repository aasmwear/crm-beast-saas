<?php

namespace Tests\Feature\Settings;

use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\Platform\OrganizationFeature;
use App\Models\Setting;
use App\Models\User;
use App\Support\Features;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class SettingsFeaturesTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $org;

    protected User $userWithSettings;

    protected User $userWithoutSettings;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->org = Organization::factory()->create(['slug' => 'acme', 'name' => 'Acme Corp']);

        $ownerRole = Role::where('name', 'Owner')->whereNull('team_id')->first();
        $clientRole = Role::where('name', 'Client')->whereNull('team_id')->first();

        $this->userWithSettings = User::factory()->create([
            'active_organization_id' => $this->org->id,
        ]);
        $this->userWithSettings->organizations()->attach($this->org);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->org->id);
        $this->userWithSettings->assignRole($ownerRole);

        $this->userWithoutSettings = User::factory()->create([
            'active_organization_id' => $this->org->id,
        ]);
        $this->userWithoutSettings->organizations()->attach($this->org);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->org->id);
        $this->userWithoutSettings->assignRole($clientRole);
    }

    public function test_user_without_settings_update_gets_403_on_features_update(): void
    {
        $response = $this->actingAs($this->userWithoutSettings)
            ->postJson(route('settings.features', ['organization' => $this->org->slug]), [
                'features' => ['attendance' => true],
            ]);

        $response->assertForbidden();
    }

    public function test_features_update_is_org_scoped(): void
    {
        $orgB = Organization::factory()->create(['slug' => 'other', 'name' => 'Other Corp']);

        $this->actingAs($this->userWithSettings)
            ->postJson(route('settings.features', ['organization' => $this->org->slug]), [
                'features' => [
                    'attendance' => true,
                    'sms' => true,
                    'api_access' => false,
                    'storage_gb' => 10,
                ],
            ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $ofA = OrganizationFeature::query()->where('organization_id', $this->org->id)->first();
        $this->assertNotNull($ofA);
        $this->assertTrue($ofA->features['attendance'] ?? false);
        $this->assertTrue($ofA->features['sms'] ?? false);
        $this->assertSame(10, $ofA->features['storage_gb'] ?? 0);

        $ofB = OrganizationFeature::query()->where('organization_id', $orgB->id)->first();
        $this->assertNull($ofB);
    }

    public function test_audit_log_created_on_features_update(): void
    {
        $this->actingAs($this->userWithSettings)
            ->postJson(route('settings.features', ['organization' => $this->org->slug]), [
                'features' => [
                    'attendance' => true,
                    'sms' => true,
                    'storage_gb' => 20,
                ],
            ])
            ->assertOk();

        $log = AuditLog::where('organization_id', $this->org->id)
            ->where('entity', 'settings')
            ->where('action', 'updated')
            ->latest()
            ->first();

        $this->assertNotNull($log);
        $this->assertArrayHasKey('keys', $log->changes ?? []);
        $keys = $log->changes['keys'];
        $this->assertContains('features.sms', $keys);
        $this->assertContains('features.storage_gb', $keys);
    }

    public function test_index_passes_features_and_feature_catalog(): void
    {
        OrganizationFeature::factory()->create([
            'organization_id' => $this->org->id,
            'features' => ['attendance' => true, 'sms' => false, 'api_access' => false, 'storage_gb' => 5],
        ]);

        $response = $this->actingAs($this->userWithSettings)
            ->get(route('settings.index', ['organization' => $this->org->slug]));

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Settings/Index')
                ->has('features')
                ->has('featureCatalog')
                ->where('features.attendance', true)
                ->where('features.storage_gb', 5)
            );
    }

    public function test_features_enabled_uses_db_when_org_provided(): void
    {
        OrganizationFeature::factory()->create([
            'organization_id' => $this->org->id,
            'features' => ['attendance' => true, 'sms' => false, 'api_access' => true, 'storage_gb' => 10],
        ]);

        $this->assertTrue(Features::enabled('attendance', $this->org));
        $this->assertFalse(Features::enabled('sms', $this->org));
        $this->assertTrue(Features::enabled('api_access', $this->org));
        $this->assertSame(10, Features::value('storage_gb', $this->org));
    }

    public function test_settings_update_with_blank_secrets_succeeds_and_preserves_existing_secrets(): void
    {
        $orgId = (int) $this->org->id;
        Setting::putEncrypted($orgId, 'slack_webhook_url', 'https://hooks.slack.com/services/xxx');
        Setting::putEncrypted($orgId, 'smtp_pass', 'secret123');
        $this->assertTrue(Setting::isSecretSet($orgId, 'slack_webhook_url'));
        $this->assertTrue(Setting::isSecretSet($orgId, 'smtp_pass'));

        $response = $this->actingAs($this->userWithSettings)
            ->post(route('settings.update', ['organization' => $this->org->slug]), [
                '_token' => csrf_token(),
                'name' => $this->org->name,
                'timezone' => 'UTC',
                'week_start' => 'Monday',
                'locale' => 'en',
                'currency' => 'USD',
                'slack_webhook_url' => '',
                'smtp_host' => 'smtp.example.com',
                'smtp_port' => 587,
                'smtp_user' => '',
                'smtp_pass' => '',
                'smtp_from' => '',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertTrue(Setting::isSecretSet($orgId, 'slack_webhook_url'));
        $this->assertTrue(Setting::isSecretSet($orgId, 'smtp_pass'));
    }

    public function test_settings_index_does_not_leak_secrets_in_props(): void
    {
        $orgId = (int) $this->org->id;
        Setting::putEncrypted($orgId, 'slack_webhook_url', 'https://secret.url');
        Setting::putEncrypted($orgId, 'smtp_pass', 'mysecret');

        $response = $this->actingAs($this->userWithSettings)
            ->get(route('settings.index', ['organization' => $this->org->slug]));

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Settings/Index')
                ->where('settings.slack_webhook_url', '••••••••')
                ->where('settings.slack_webhook_connected', true)
                ->where('settings.smtp_pass_set', true)
            );
    }
}
