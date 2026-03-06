<?php

namespace Tests\Feature\Clients;

use App\Models\Client;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

final class ClientPersistenceTest extends TestCase
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

    public function test_creating_client_with_fronter_id_closer_id_persists_and_edit_includes_them(): void
    {
        $fronter = User::factory()->create();
        $closer = User::factory()->create();
        $this->org->users()->attach([$fronter->id, $closer->id]);

        $response = $this->actingAs($this->user)
            ->post(route('clients.store', ['organization' => $this->org->slug]), $this->validPayload([
                'fronter_id' => $fronter->id,
                'closer_id' => $closer->id,
            ]));

        $response->assertRedirect();
        $client = Client::query()->where('company_name', 'Test Client Inc')->first();
        $this->assertNotNull($client);
        $this->assertSame((int) $fronter->id, (int) $client->fronter_id);
        $this->assertSame((int) $closer->id, (int) $client->closer_id);

        $editResponse = $this->actingAs($this->user)
            ->get(route('clients.edit', ['organization' => $this->org->slug, 'client' => $client->id]));

        $editResponse->assertOk();
        $editResponse->assertInertia(fn (Assert $page) => $page
            ->component('Clients/Edit')
            ->where('client.id', $client->id)
            ->where('client.fronter_id', $fronter->id)
            ->where('client.closer_id', $closer->id));
    }

    public function test_creating_client_with_gbp_status_gbp_access_persists_and_edit_sees_values(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('clients.store', ['organization' => $this->org->slug]), $this->validPayload([
                'gbp_status' => 'verified',
                'gbp_access' => 'access_granted',
            ]));

        $response->assertRedirect();
        $client = Client::query()->where('company_name', 'Test Client Inc')->first();
        $this->assertNotNull($client);
        $this->assertSame('verified', $client->gbp_status);
        $this->assertSame('access_granted', $client->gbp_access);

        $editResponse = $this->actingAs($this->user)
            ->get(route('clients.edit', ['organization' => $this->org->slug, 'client' => $client->id]));

        $editResponse->assertOk();
        $editResponse->assertInertia(fn (Assert $page) => $page
            ->component('Clients/Edit')
            ->where('client.gbp_status', 'verified')
            ->where('client.gbp_access', 'access_granted'));
    }

    public function test_creating_client_with_notes_sales_persists_and_edit_includes_it(): void
    {
        $notes = 'Initial sales note for this client.';

        $response = $this->actingAs($this->user)
            ->post(route('clients.store', ['organization' => $this->org->slug]), $this->validPayload([
                'notes_sales' => $notes,
            ]));

        $response->assertRedirect();
        $client = Client::query()->where('company_name', 'Test Client Inc')->first();
        $this->assertNotNull($client);
        $this->assertSame($notes, $client->notes_sales);

        $editResponse = $this->actingAs($this->user)
            ->get(route('clients.edit', ['organization' => $this->org->slug, 'client' => $client->id]));

        $editResponse->assertOk();
        $editResponse->assertInertia(fn (Assert $page) => $page
            ->component('Clients/Edit')
            ->where('client.notes_sales', $notes));
    }

    public function test_updating_with_new_note_sales_appends_timestamp_and_author(): void
    {
        $client = Client::factory()->create([
            'organization_id' => $this->org->id,
            'company_name' => 'Append Test',
            'notes_sales' => 'Existing sales note.',
        ]);

        $newNote = 'Appended note from test.';

        $response = $this->actingAs($this->user)
            ->put(route('clients.update', [
                'organization' => $this->org->slug,
                'client' => $client->id,
            ]), $this->validPayload([
                'company_name' => 'Append Test',
                'notes_sales' => 'Existing sales note.',
                'new_note_sales' => $newNote,
            ]));

        $response->assertRedirect();

        $client->refresh();
        $this->assertStringContainsString('Existing sales note.', $client->notes_sales);
        $this->assertStringContainsString($newNote, $client->notes_sales);
        $this->assertMatchesRegularExpression(
            '/\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}\].*\(#' . $this->user->id . '\):/',
            $client->notes_sales,
        );
    }
}
