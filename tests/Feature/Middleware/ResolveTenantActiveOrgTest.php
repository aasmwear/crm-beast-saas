<?php

namespace Tests\Feature\Middleware;

use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ResolveTenantActiveOrgTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_resolve_tenant_updates_active_organization_id_when_visiting_different_org(): void
    {
        $orgA = Organization::factory()->create(['slug' => 'org-a']);
        $orgB = Organization::factory()->create(['slug' => 'org-b']);

        $user = User::factory()->create([
            'active_organization_id' => $orgA->id,
            'client_id' => null,
        ]);
        $orgA->users()->attach($user->id);
        $orgB->users()->attach($user->id);

        $role = Role::create([
            'name' => 'resolver-test-role',
            'guard_name' => 'web',
            'team_id' => $orgB->id,
        ]);
        $role->givePermissionTo('clients.view');
        app(PermissionRegistrar::class)->setPermissionsTeamId($orgB->id);
        $user->assignRole($role);

        $this->assertSame((int) $user->active_organization_id, (int) $orgA->id);

        $this->actingAs($user)
            ->get(route('clients.index', ['organization' => $orgB->slug]))
            ->assertOk();

        $user->refresh();
        $this->assertSame((int) $user->active_organization_id, (int) $orgB->id);
    }

    public function test_resolve_tenant_does_not_update_when_already_on_same_org(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        $user = User::factory()->create([
            'active_organization_id' => $org->id,
            'client_id' => null,
        ]);
        $org->users()->attach($user->id);

        $role = Role::create([
            'name' => 'same-org-role',
            'guard_name' => 'web',
            'team_id' => $org->id,
        ]);
        $role->givePermissionTo('clients.view');
        app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);
        $user->assignRole($role);

        $this->actingAs($user)
            ->get(route('clients.index', ['organization' => $org->slug]))
            ->assertOk();

        $user->refresh();
        $this->assertSame((int) $user->active_organization_id, (int) $org->id);
    }
}
