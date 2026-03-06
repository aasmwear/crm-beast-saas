<?php

namespace Tests\Feature\Settings;

use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\OrganizationApiKey;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

final class SettingsApiKeysTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    private User $userWithView;

    private User $userWithCreate;

    private User $userWithDelete;

    private User $userWithoutPerms;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->org = Organization::factory()->create(['slug' => 'acme', 'name' => 'Acme Corp']);

        $ownerRole = Role::where('name', 'Owner')->whereNull('team_id')->first();
        $employeeRole = Role::where('name', 'Employee')->whereNull('team_id')->first();
        $clientRole = Role::where('name', 'Client')->whereNull('team_id')->first();

        app(PermissionRegistrar::class)->setPermissionsTeamId($this->org->id);

        $this->userWithView = User::factory()->create(['active_organization_id' => $this->org->id]);
        $this->userWithView->organizations()->attach($this->org);
        $this->userWithView->assignRole($ownerRole);

        $this->userWithCreate = User::factory()->create(['active_organization_id' => $this->org->id]);
        $this->userWithCreate->organizations()->attach($this->org);
        $this->userWithCreate->assignRole($ownerRole);

        $this->userWithDelete = User::factory()->create(['active_organization_id' => $this->org->id]);
        $this->userWithDelete->organizations()->attach($this->org);
        $this->userWithDelete->assignRole($ownerRole);

        $this->userWithoutPerms = User::factory()->create(['active_organization_id' => $this->org->id]);
        $this->userWithoutPerms->organizations()->attach($this->org);
        $this->userWithoutPerms->assignRole($employeeRole);
    }

    public function test_user_without_api_keys_view_does_not_see_api_keys_tab(): void
    {
        $response = $this->actingAs($this->userWithoutPerms)
            ->get(route('settings.index', ['organization' => $this->org->slug]));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Settings/Index')
            ->has('canViewApiKeys')
            ->where('canViewApiKeys', false)
            ->where('apiKeys', [])
        );
    }

    public function test_user_without_api_keys_create_gets_403_on_create(): void
    {
        $response = $this->actingAs($this->userWithoutPerms)
            ->postJson(route('settings.api-keys.store', ['organization' => $this->org->slug]), [
                'name' => 'Test key',
            ]);

        $response->assertForbidden();
    }

    public function test_user_without_api_keys_delete_gets_403_on_revoke(): void
    {
        $key = OrganizationApiKey::create([
            'organization_id' => $this->org->id,
            'name' => 'Test',
            'prefix' => 'crmb_abc',
            'hashed_key' => hash('sha256', 'dummy'),
            'created_by_user_id' => $this->userWithDelete->id,
        ]);

        $response = $this->actingAs($this->userWithoutPerms)
            ->deleteJson(route('settings.api-keys.destroy', [
                'organization' => $this->org->slug,
                'apiKey' => $key->id,
            ]));

        $response->assertForbidden();
    }

    public function test_org_scoping_org_a_cannot_revoke_org_b_key(): void
    {
        $orgB = Organization::factory()->create(['slug' => 'other']);
        $keyB = OrganizationApiKey::create([
            'organization_id' => $orgB->id,
            'name' => 'Org B Key',
            'prefix' => 'crmb_xyz',
            'hashed_key' => hash('sha256', 'dummy'),
            'created_by_user_id' => $this->userWithDelete->id,
        ]);

        $response = $this->actingAs($this->userWithDelete)
            ->deleteJson(route('settings.api-keys.destroy', [
                'organization' => $this->org->slug,
                'apiKey' => $keyB->id,
            ]));

        $response->assertNotFound();
        $keyB->refresh();
        $this->assertNull($keyB->revoked_at);
    }

    public function test_create_returns_token_once_and_db_stores_only_hashed(): void
    {
        $response = $this->actingAs($this->userWithCreate)
            ->postJson(route('settings.api-keys.store', ['organization' => $this->org->slug]), [
                'name' => 'Production integration',
            ]);

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'api_key' => ['id', 'name', 'prefix', 'created_at'],
                'plaintext_token',
            ])
            ->assertJson(['success' => true]);

        $plaintext = $response->json('plaintext_token');
        $this->assertStringStartsWith('crmb_', $plaintext);

        $apiKey = OrganizationApiKey::query()->where('name', 'Production integration')->first();
        $this->assertNotNull($apiKey);
        $this->assertSame(substr($plaintext, 0, 8), $apiKey->prefix);
        $this->assertSame(hash('sha256', $plaintext), $apiKey->hashed_key);
    }

    public function test_audit_logs_created_on_create_and_revoke(): void
    {
        $response = $this->actingAs($this->userWithCreate)
            ->postJson(route('settings.api-keys.store', ['organization' => $this->org->slug]), [
                'name' => 'Audit test',
            ]);

        $response->assertOk();
        $apiKeyId = $response->json('api_key.id');

        $createdLog = AuditLog::where('organization_id', $this->org->id)
            ->where('entity', 'api_key')
            ->where('action', 'created')
            ->where('entity_id', $apiKeyId)
            ->first();

        $this->assertNotNull($createdLog);
        $this->assertArrayHasKey('prefix', $createdLog->changes ?? []);
        $this->assertArrayHasKey('name', $createdLog->changes ?? []);
        $this->assertArrayNotHasKey('plaintext_token', $createdLog->changes ?? []);
        $this->assertArrayNotHasKey('hashed_key', $createdLog->changes ?? []);

        $this->actingAs($this->userWithDelete)
            ->deleteJson(route('settings.api-keys.destroy', [
                'organization' => $this->org->slug,
                'apiKey' => $apiKeyId,
            ]))
            ->assertOk();

        $revokedLog = AuditLog::where('organization_id', $this->org->id)
            ->where('entity', 'api_key')
            ->where('action', 'revoked')
            ->where('entity_id', $apiKeyId)
            ->first();

        $this->assertNotNull($revokedLog);
    }

    public function test_index_includes_api_keys_when_user_has_permission(): void
    {
        OrganizationApiKey::create([
            'organization_id' => $this->org->id,
            'name' => 'Existing key',
            'prefix' => 'crmb_abc',
            'hashed_key' => hash('sha256', 'secret'),
            'created_by_user_id' => $this->userWithView->id,
        ]);

        $response = $this->actingAs($this->userWithView)
            ->get(route('settings.index', ['organization' => $this->org->slug]));

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Settings/Index')
                ->where('canViewApiKeys', true)
                ->has('apiKeys')
                ->where('apiKeys.0.name', 'Existing key')
                ->where('apiKeys.0.prefix', 'crmb_abc')
            );
    }
}
