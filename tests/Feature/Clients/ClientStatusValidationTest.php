<?php

namespace Tests\Feature\Clients;

use App\Models\Client;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

final class ClientStatusValidationTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->org = Organization::factory()->create(['slug' => 'acme']);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->org->id);

        $this->user = User::factory()->create(['active_organization_id' => $this->org->id]);
        $this->user->organizations()->attach([$this->org->id]);
        $this->user->assignRole(Role::where('name', 'Owner')->whereNull('team_id')->first());
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'company_name' => 'Test Client Inc',
            'primary_contact_name' => 'John Doe',
            'primary_contact_email' => 'john@test.com',
        ], $overrides);
    }

    public function test_posting_client_with_status_active_succeeds_and_stores_active(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('clients.store', ['organization' => $this->org->slug]), $this->validPayload([
                'status' => 'active',
            ]));

        $response->assertRedirect();
        $this->assertDatabaseHas('clients', [
            'organization_id' => $this->org->id,
            'company_name' => 'Test Client Inc',
            'status' => 'active',
        ]);
    }

    public function test_posting_client_with_legacy_titlecase_status_succeeds_and_stores_active(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('clients.store', ['organization' => $this->org->slug]), $this->validPayload([
                'status' => 'Active',
            ]));

        $response->assertRedirect();
        $this->assertDatabaseHas('clients', [
            'organization_id' => $this->org->id,
            'company_name' => 'Test Client Inc',
            'status' => 'active',
        ]);
    }

    public function test_posting_client_with_invalid_status_returns_422(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson(route('clients.store', ['organization' => $this->org->slug]), $this->validPayload([
                'status' => 'invalid_value',
            ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['status']);
    }

    public function test_updating_client_with_status_active_stores_active(): void
    {
        $client = Client::factory()->create([
            'organization_id' => $this->org->id,
            'company_name' => 'Update Test',
            'status' => 'lead',
        ]);

        $response = $this->actingAs($this->user)
            ->put(route('clients.update', [
                'organization' => $this->org->slug,
                'client' => $client->id,
            ]), $this->validPayload([
                'company_name' => 'Update Test',
                'status' => 'active',
            ]));

        $response->assertRedirect();
        $this->assertDatabaseHas('clients', [
            'id' => $client->id,
            'status' => 'active',
        ]);
    }

    public function test_updating_client_with_legacy_titlecase_status_normalizes_to_active(): void
    {
        $client = Client::factory()->create([
            'organization_id' => $this->org->id,
            'company_name' => 'Update Test',
            'status' => 'lead',
        ]);

        $response = $this->actingAs($this->user)
            ->put(route('clients.update', [
                'organization' => $this->org->slug,
                'client' => $client->id,
            ]), $this->validPayload([
                'company_name' => 'Update Test',
                'status' => 'Active',
            ]));

        $response->assertRedirect();
        $this->assertDatabaseHas('clients', [
            'id' => $client->id,
            'status' => 'active',
        ]);
    }
}
