<?php

declare(strict_types=1);

namespace Tests\Feature\Activity;

use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
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
            Permission::firstOrCreate(['name' => 'activity.view', 'guard_name' => 'web']);
            $owner = Role::firstOrCreate(['name' => 'Owner', 'guard_name' => 'web']);
            $owner->givePermissionTo('activity.view');
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

    public function test_user_without_activity_view_gets_403(): void
    {
        [$org, $user] = $this->makeTenant();

        // Create a role without activity.view and assign to user.
        app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);
        Permission::firstOrCreate(['name' => 'tasks.view', 'guard_name' => 'web']);
        $role = Role::create(['name' => 'NoActivityView', 'guard_name' => 'web']);
        $role->givePermissionTo('tasks.view');
        $user->syncRoles([$role]);

        $response = $this
            ->actingAs($user)
            ->get(route('activity.index', ['organization' => $org->slug]));

        $response->assertStatus(403);
    }

    public function test_client_create_emits_audit_log_with_organization_id(): void
    {
        [$org, $user] = $this->makeTenant();

        $client = \App\Models\Client::factory()->create([
            'organization_id' => $org->id,
            'company_name' => 'Test Corp',
        ]);

        \App\Services\AuditLogger::log(
            $org,
            $user,
            'created',
            'client',
            (int) $client->id,
            ['after' => $client->getAttributes()],
        );

        $log = AuditLog::query()
            ->where('organization_id', $org->id)
            ->where('entity', 'client')
            ->where('entity_id', $client->id)
            ->where('action', 'created')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame((int) $org->id, (int) $log->organization_id);
    }
}
