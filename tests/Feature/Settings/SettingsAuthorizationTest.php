<?php

namespace Tests\Feature\Settings;

use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class SettingsAuthorizationTest extends TestCase
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

    public function test_user_with_settings_view_can_access_index(): void
    {
        $response = $this->actingAs($this->userWithSettings)
            ->get(route('settings.index', ['organization' => $this->org->slug]));

        $response->assertOk();
    }

    public function test_user_without_settings_view_gets_403_on_index(): void
    {
        $response = $this->actingAs($this->userWithoutSettings)
            ->get(route('settings.index', ['organization' => $this->org->slug]));

        $response->assertForbidden();
    }

    public function test_user_without_settings_update_gets_403_on_update(): void
    {
        $response = $this->actingAs($this->userWithoutSettings)
            ->post(route('settings.update', ['organization' => $this->org->slug]), [
                'name' => 'Acme Corp',
                'timezone' => 'UTC',
                'week_start' => 'Monday',
            ]);

        $response->assertForbidden();
    }

    public function test_settings_saved_are_org_scoped(): void
    {
        $orgB = Organization::factory()->create(['slug' => 'other', 'name' => 'Other Corp']);

        $this->actingAs($this->userWithSettings)
            ->post(route('settings.update', ['organization' => $this->org->slug]), [
                'name' => 'Acme Corp',
                'timezone' => 'UTC',
                'week_start' => 'Monday',
                'work_hours' => [
                    'work_week' => 'Sun-Thu',
                    'start_time' => '08:00',
                    'end_time' => '16:00',
                ],
            ])
            ->assertRedirect();

        $orgAWorkHours = Setting::get((int) $this->org->id, 'work_hours');
        $this->assertIsArray($orgAWorkHours);
        $this->assertSame('Sun-Thu', $orgAWorkHours['work_week'] ?? null);

        $orgBWorkHours = Setting::get((int) $orgB->id, 'work_hours');
        $this->assertNull($orgBWorkHours);
    }

    public function test_audit_log_created_on_settings_update(): void
    {
        $this->actingAs($this->userWithSettings)
            ->post(route('settings.update', ['organization' => $this->org->slug]), [
                'name' => 'Acme Corp',
                'timezone' => 'America/New_York',
                'week_start' => 'Monday',
                'locale' => 'fr',
            ])
            ->assertRedirect();

        $log = AuditLog::where('organization_id', $this->org->id)
            ->where('entity', 'settings')
            ->where('action', 'updated')
            ->latest()
            ->first();

        $this->assertNotNull($log);
        $this->assertSame($this->userWithSettings->id, $log->actor_id);
        $this->assertArrayHasKey('keys', $log->changes ?? []);
        $this->assertContains('timezone', $log->changes['keys']);
        $this->assertContains('locale', $log->changes['keys']);
        $this->assertNotContains('smtp_pass', $log->changes['keys'] ?? []);
    }

    public function test_secrets_not_leaked_in_index_props(): void
    {
        Setting::putEncrypted((int) $this->org->id, 'slack_webhook_url', 'https://hooks.slack.com/secret');
        Setting::putEncrypted((int) $this->org->id, 'smtp_pass', 'secret-password');
        Setting::put((int) $this->org->id, 'smtp_host', 'smtp.example.com');

        $response = $this->actingAs($this->userWithSettings)
            ->get(route('settings.index', ['organization' => $this->org->slug]));

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Settings/Index')
                ->has('settings')
                ->where('settings.slack_webhook_connected', true)
                ->where('settings.slack_webhook_url', '••••••••')
                ->where('settings.smtp_pass_set', true)
                ->where('settings.smtp_host', 'smtp.example.com')
                ->missing('settings.smtp_pass')
            );
    }

    public function test_test_slack_requires_settings_update_permission(): void
    {
        $response = $this->actingAs($this->userWithoutSettings)
            ->postJson(route('settings.testSlack', ['organization' => $this->org->slug]));

        $response->assertForbidden();
    }

    public function test_test_smtp_requires_settings_update_permission(): void
    {
        $response = $this->actingAs($this->userWithoutSettings)
            ->postJson(route('settings.testSmtp', ['organization' => $this->org->slug]));

        $response->assertForbidden();
    }
}
