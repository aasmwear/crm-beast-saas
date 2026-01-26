<?php

declare(strict_types=1);

namespace Tests\Feature\Activity;

use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

final class ActivityIndexTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Create a tenant organization and attach an owner-level user.
     *
     * @return array{0: Organization, 1: User}
     */
    protected function makeTenant(): array
    {
        /** @var Organization $org */
        $org = Organization::factory()->create([
            'slug' => 'acme',
            'settings' => [],
        ]);

        /** @var User $user */
        $user = User::factory()->create();
        $user->organizations()->attach($org->id);

        if (method_exists($user, 'assignRole')) {
            app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);
            Role::findOrCreate('Owner', config('auth.defaults.guard', 'web'));
            $user->assignRole('Owner');
        }

        return [$org, $user];
    }

    public function test_activity_index_lists_audit_logs(): void
    {
        [$org, $user] = $this->makeTenant();

        // Seed a couple of audit logs.
        AuditLog::query()->create([
            'organization_id' => $org->id,
            'actor_id' => $user->id,
            'action' => 'created',
            'entity' => 'client',
            'entity_id' => 1,
            'changes' => null,
        ]);

        AuditLog::query()->create([
            'organization_id' => $org->id,
            'actor_id' => $user->id,
            'action' => 'updated',
            'entity' => 'project',
            'entity_id' => 2,
            'changes' => null,
        ]);

        $response = $this
            ->actingAs($user)
            ->withHeader('X-Inertia', 'true')
            ->get(route('activity.index', ['organization' => $org->slug]));

        $response->assertStatus(200);

        // Inertia JSON response has component + props.
        $response->assertJsonFragment([
            'action' => 'created',
            'entity' => 'client',
        ]);
    }

    public function test_activity_index_can_filter_by_actor(): void
    {
        [$org, $user] = $this->makeTenant();

        /** @var User $otherUser */
        $otherUser = User::factory()->create();
        $otherUser->organizations()->attach($org->id);

        if (method_exists($otherUser, 'assignRole')) {
            app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);
            Role::findOrCreate('Owner', config('auth.defaults.guard', 'web'));
            $otherUser->assignRole('Owner');
        }

        // Log for first user.
        AuditLog::query()->create([
            'organization_id' => $org->id,
            'actor_id' => $user->id,
            'action' => 'created',
            'entity' => 'client',
            'entity_id' => 1,
            'changes' => null,
        ]);

        // Log for second user.
        AuditLog::query()->create([
            'organization_id' => $org->id,
            'actor_id' => $otherUser->id,
            'action' => 'updated',
            'entity' => 'project',
            'entity_id' => 2,
            'changes' => null,
        ]);

        $response = $this
            ->actingAs($user)
            ->withHeader('X-Inertia', 'true')
            ->get(route('activity.index', [
                'organization' => $org->slug,
                'actor_id' => $otherUser->id,
            ]));

        $response->assertStatus(200);

        $payload = $response->json('props.items') ?? [];
        $this->assertIsArray($payload);

        // Should only contain items for the filtered actor.
        $this->assertNotCount(0, $payload);

        foreach ($payload as $item) {
            $this->assertSame($otherUser->id, $item['actor_id']);
        }
    }
}
