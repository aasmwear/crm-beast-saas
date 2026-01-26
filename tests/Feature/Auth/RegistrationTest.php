<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

// NOTE: InertiaAssert trait is needed here or in TestCase.php
// to make assertInertia() work, as identified in the previous step.

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // **CRITICAL FIX: Explicitly disable Spatie teams for testing.**
        // The multitenant CRM uses its own organization logic,
        // so Spatie's internal 'teams' logic needs to be disabled to prevent database errors/rollbacks.
        $this->app['config']->set('permission.teams', false);

        // 1. Ensure permissions cache is cleared.
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();

        // 2. Create the 'Owner' role to ensure it exists for the HTTP call.
        Role::findOrCreate('Owner', 'web');
    }

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    // In tests/Feature/Auth/RegistrationTest.php

    public function test_new_users_can_register(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        // 1. Assert that the user exists in the database.
        $this->assertDatabaseHas('users', ['email' => 'test@example.com']);

        // 2. Retrieve the user to assert authentication and determine the redirect target.
        $user = User::where('email', 'test@example.com')->first();
        $this->assertNotNull($user, 'The user was not created in the database.');

        // 3. Explicitly assert the retrieved user is authenticated.
        $this->assertAuthenticatedAs($user);

        // ✅ Verify and refresh the user in the guard so 'verified' middleware passes.
        $user->markEmailAsVerified();
        $this->actingAs($user->fresh());

        // Retrieve the organization for the correct redirect path assertion
        $org = $user->fresh()->activeOrganization;

        // 4. Assert redirect to the correct, org-scoped dashboard route.
        $response->assertRedirectToRoute('dashboard', ['organization' => $org->slug]);

        // 5. Hit the dashboard and assert the final Inertia component.
        // (followingRedirects() returns the TestCase, not a TestResponse)
        $dashboard = $this->get(route('dashboard', ['organization' => $org->slug], false));
        $dashboard->assertInertia(fn (Assert $page) => $page->component('Dashboard/Index'));

    }
}
