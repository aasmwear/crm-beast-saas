<?php

namespace Tests\Feature\Clients;

use App\Models\Client;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

final class SoftDeleteExportTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0:Organization,1:User}
     */
    private function tenant(): array
    {
        /** @var Organization $org */
        $org = Organization::factory()->create([
            'slug' => 'acme',
            'plan' => 'trial',
            'settings' => [],
        ]);

        /** @var User $user */
        $user = User::factory()->create([
            'active_organization_id' => $org->id,
        ]);

        $user->organizations()->attach($org->id);

        $guard = (string) config('auth.defaults.guard', 'web');

        $registrar = app(PermissionRegistrar::class);

        // If Spatie teams are enabled, the role must be created+assigned under the org's team_id.
        if (Schema::hasColumn('roles', 'team_id') && method_exists($registrar, 'setPermissionsTeamId')) {
            $registrar->setPermissionsTeamId($org->id);
        }

        $registrar->forgetCachedPermissions();

        Role::findOrCreate('Owner', $guard);
        $user->assignRole('Owner');

        return [$org, $user];
    }

    public function test_destroy_soft_deletes_and_export_includes_when_requested(): void
    {
        [$org, $user] = $this->tenant();

        /** @var Client $keep */
        $keep = Client::factory()->create([
            'organization_id' => $org->id,
            'company_name' => 'Keep Me',
            'status' => 'active',
        ]);

        /** @var Client $trash */
        $trash = Client::factory()->create([
            'organization_id' => $org->id,
            'company_name' => 'Trash Me',
            'status' => 'inactive',
        ]);

        // Delete one (soft delete)
        $this->actingAs($user)
            ->delete(route('clients.destroy', [
                'organization' => $org->slug,
                'client' => $trash->id,
            ]))
            ->assertRedirect();

        // Export with include_deleted=1
        $resp = $this->actingAs($user)->get(route('export.csv', [
            'organization' => $org->slug,
            'entity' => 'clients',
            'include_deleted' => 1,
        ]));

        $resp->assertOk();
        $resp->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $csv = $resp->streamedContent();
        $this->assertStringContainsString('Keep Me', $csv);
        $this->assertStringContainsString('Trash Me', $csv);
    }
}
