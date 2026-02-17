<?php

namespace Tests\Feature\Auth;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class RegistrationTenantTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_register_company_screen_can_be_rendered(): void
    {
        $response = $this->get('/register-company');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page->component('Auth/RegisterTenant'));
    }

    public function test_new_tenant_can_register(): void
    {
        $response = $this->post('/register-company', [
            'name' => 'Jane Owner',
            'email' => 'jane@acme.test',
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
            'company_name' => 'Acme Co',
            'subdomain' => 'acme-co',
        ]);

        $response->assertRedirectToRoute('dashboard', ['organization' => 'acme-co']);

        $this->assertDatabaseHas('users', ['email' => 'jane@acme.test']);
        $this->assertDatabaseHas('organizations', ['slug' => 'acme-co', 'name' => 'Acme Co']);

        $user = User::where('email', 'jane@acme.test')->first();
        $this->assertNotNull($user);
        $this->assertAuthenticatedAs($user);

        $org = Organization::where('slug', 'acme-co')->first();
        $this->assertNotNull($org);
        $this->assertEquals($org->id, $user->active_organization_id);

        $this->assertDatabaseHas('organization_user', [
            'user_id' => $user->id,
            'organization_id' => $org->id,
            'is_owner' => true,
        ]);

        $this->assertTrue($user->hasRole('Owner'), 'User should have Owner role');

        $this->assertDatabaseHas('clients', ['organization_id' => $org->id, 'company_name' => 'Sample Client']);
        $this->assertDatabaseHas('projects', ['organization_id' => $org->id, 'title' => 'Onboarding Project']);
    }

    public function test_subdomain_must_be_unique(): void
    {
        Organization::factory()->create(['slug' => 'taken', 'name' => 'Taken']);

        $response = $this->post('/register-company', [
            'name' => 'New User',
            'email' => 'new@test.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'company_name' => 'New Co',
            'subdomain' => 'taken',
        ]);

        $response->assertSessionHasErrors('subdomain');
        $this->assertDatabaseMissing('users', ['email' => 'new@test.com']);
    }

    public function test_subdomain_must_be_alpha_dash(): void
    {
        $response = $this->post('/register-company', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'company_name' => 'Test Co',
            'subdomain' => 'invalid!subdomain',
        ]);

        $response->assertSessionHasErrors('subdomain');
    }
}
