<?php

namespace Tests\Feature\Clients;

use App\Models\Client;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ListTest extends TestCase
{
    use RefreshDatabase;

    protected function makeTenant(): array
    {
        /** @var Organization $org */
        $org = Organization::factory()->create(['slug' => 'acme', 'settings' => []]);

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

    public function test_index_paginates_filters_and_search(): void
    {
        [$org, $user] = $this->makeTenant();

        // 12 matching rows
        Client::factory()->count(12)->create([
            'organization_id' => $org->id,
            'company_name' => 'Acme Co',
            'industry' => 'SaaS',
            'status' => 'active',
        ]);

        // 8 non-matching rows
        Client::factory()->count(8)->create([
            'organization_id' => $org->id,
            'company_name' => 'Other Co',
            'industry' => 'Services',
            'status' => 'inactive',
        ]);

        $this->actingAs($user)
            ->get(route('clients.index', [
                'organization' => $org->slug,
                'q' => 'Acme',
                'status' => 'active',
                'industry' => 'SaaS',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Clients/Index')
                ->where('filters.status', 'active')
                ->where('filters.industry', 'SaaS')
                ->where('filters.q', 'Acme')
                ->has('clients.data', 10) // 10 per page
                ->has('clients.links')
            );
    }

    public function test_delete_client_in_same_org(): void
    {
        [$org, $user] = $this->makeTenant();

        /** @var Client $client */
        $client = Client::factory()->create([
            'organization_id' => $org->id,
            'company_name' => 'Delete Me',
            'status' => 'active',
        ]);

        $this->actingAs($user)
            ->delete(route('clients.destroy', [
                'organization' => $org->slug,
                'client' => $client->id,
            ]))
            ->assertRedirect();

        // Support both hard- and soft-deletes
        if (Schema::hasColumn('clients', 'deleted_at')) {
            $this->assertSoftDeleted('clients', ['id' => $client->id]);
        } else {
            $this->assertDatabaseMissing('clients', ['id' => $client->id]);
        }
    }
}
