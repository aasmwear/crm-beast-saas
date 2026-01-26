<?php

namespace Tests\Feature\Notifications;

use App\Models\Client;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NewActivityTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_client_emits_new_activity_notification(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->create([
            'active_organization_id' => $org->id,
        ]);

        $org->users()->attach($user->id);

        $this->actingAs($user);

        $client = Client::factory()->create([
            'organization_id' => $org->id,
            'company_name' => 'Acme Co',
            'fronter' => [$user->id],
        ]);

        // Org-level event log
        $this->assertDatabaseHas('notification_events', [
            'organization_id' => $org->id,
            'entity' => 'client',
            'entity_id' => $client->id,
            'type' => 'new_activity',
        ]);

        // Per-user database notification (Laravel notifications table)
        $this->assertTrue(
            $user->notifications()
                ->whereRaw("data->>'organization_id' = ?", [(string) $org->id])
                ->exists()
        );
    }
}
