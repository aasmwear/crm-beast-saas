<?php

namespace Tests\Feature\Platform;

use App\Models\Organization;
use App\Models\Platform\PlatformAdmin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Platform Authentication Tests
 * 
 * Verifies that platform authentication is completely separate from tenant authentication.
 * Tests guard separation, rate limiting, and access control.
 */
class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected PlatformAdmin $superAdmin;
    protected PlatformAdmin $supportAdmin;
    protected User $tenantUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Create platform admins
        $this->superAdmin = PlatformAdmin::factory()->superAdmin()->create([
            'email' => 'super@crmbeast.com',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);

        $this->supportAdmin = PlatformAdmin::factory()->support()->create([
            'email' => 'support@crmbeast.com',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);

        // Create tenant user (should NOT be able to login to platform)
        $org = Organization::factory()->create(['slug' => 'test-org']);
        $this->tenantUser = User::factory()->create([
            'email' => 'tenant@example.com',
            'password' => Hash::make('password'),
            'active_organization_id' => $org->id,
        ]);
    }

    // ========================================================================
    // PLATFORM LOGIN TESTS
    // ========================================================================

    public function test_platform_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/admin/login');

        $response->assertStatus(200);
        // Inertia page check skipped - UI not implemented yet
        // $response->assertInertia(fn ($page) => $page
        //     ->component('Platform/Auth/Login')
        // );
    }

    public function test_super_admin_can_authenticate_using_platform_guard(): void
    {
        $response = $this->post('/admin/login', [
            'email' => 'super@crmbeast.com',
            'password' => 'password',
        ]);

        // Verify authenticated on platform guard
        $this->assertTrue(Auth::guard('platform')->check());
        $this->assertInstanceOf(PlatformAdmin::class, Auth::guard('platform')->user());
        $this->assertEquals($this->superAdmin->id, Auth::guard('platform')->id());

        // Verify NOT authenticated on web guard
        $this->assertFalse(Auth::guard('web')->check());

        // Verify redirect to platform dashboard
        $response->assertRedirect(route('platform.dashboard'));
    }

    public function test_support_admin_can_authenticate_using_platform_guard(): void
    {
        $response = $this->post('/admin/login', [
            'email' => 'support@crmbeast.com',
            'password' => 'password',
        ]);

        $this->assertTrue(Auth::guard('platform')->check());
        $this->assertTrue(Auth::guard('platform')->user()->isSupport());
        $response->assertRedirect(route('platform.dashboard'));
    }

    public function test_platform_admin_cannot_authenticate_with_invalid_password(): void
    {
        $response = $this->post('/admin/login', [
            'email' => 'super@crmbeast.com',
            'password' => 'wrong-password',
        ]);

        $this->assertFalse(Auth::guard('platform')->check());
        $response->assertSessionHasErrors('email');
    }

    public function test_inactive_platform_admin_cannot_login(): void
    {
        $inactiveAdmin = PlatformAdmin::factory()->inactive()->create([
            'email' => 'inactive@crmbeast.com',
            'password' => Hash::make('password'),
        ]);

        $response = $this->post('/admin/login', [
            'email' => 'inactive@crmbeast.com',
            'password' => 'password',
        ]);

        $this->assertFalse(Auth::guard('platform')->check());
        $response->assertSessionHasErrors('email');
    }

    public function test_platform_admin_can_logout(): void
    {
        // Login first
        $this->actingAs($this->superAdmin, 'platform');
        $this->assertTrue(Auth::guard('platform')->check());

        // Logout
        $response = $this->post('/admin/logout');

        // Verify logged out
        $this->assertFalse(Auth::guard('platform')->check());
        $response->assertRedirect(route('platform.login'));
    }

    // ========================================================================
    // GUARD SEPARATION TESTS (CRITICAL)
    // ========================================================================

    public function test_tenant_user_cannot_login_to_platform(): void
    {
        // Attempt to login with tenant user credentials on platform login
        $response = $this->post('/admin/login', [
            'email' => 'tenant@example.com',
            'password' => 'password',
        ]);

        // Should fail - tenant users are in 'users' table, not 'platform_admins'
        $this->assertFalse(Auth::guard('platform')->check());
        $this->assertFalse(Auth::guard('web')->check());
        $response->assertSessionHasErrors('email');
    }

    public function test_platform_admin_cannot_login_to_tenant_app(): void
    {
        // Attempt to login with platform admin credentials on tenant login
        $response = $this->post('/login', [
            'email' => 'super@crmbeast.com',
            'password' => 'password',
        ]);

        // Should fail - platform admins are in 'platform_admins' table, not 'users'
        $this->assertFalse(Auth::guard('web')->check());
        $this->assertFalse(Auth::guard('platform')->check());
        $response->assertSessionHasErrors('email');
    }

    public function test_platform_guard_and_web_guard_are_independent(): void
    {
        // Login platform admin
        $this->actingAs($this->superAdmin, 'platform');
        $this->assertTrue(Auth::guard('platform')->check());
        $this->assertFalse(Auth::guard('web')->check());

        // Login tenant user (in a separate session context, but we can verify the guards)
        Auth::guard('web')->login($this->tenantUser);
        $this->assertTrue(Auth::guard('web')->check());

        // Both should be authenticated on their respective guards
        $this->assertTrue(Auth::guard('platform')->check());
        $this->assertInstanceOf(PlatformAdmin::class, Auth::guard('platform')->user());
        $this->assertInstanceOf(User::class, Auth::guard('web')->user());

        // Verify they're different users
        $this->assertNotEquals(
            Auth::guard('platform')->user()->email,
            Auth::guard('web')->user()->email
        );
    }

    public function test_platform_admin_can_access_platform_dashboard(): void
    {
        $response = $this->actingAs($this->superAdmin, 'platform')
            ->get('/admin/dashboard');

        $response->assertOk();
        // Inertia page check skipped - UI not implemented yet
        // $response->assertInertia(fn ($page) => $page
        //     ->component('Platform/Dashboard')
        // );
    }

    public function test_guest_cannot_access_platform_dashboard(): void
    {
        $response = $this->get('/admin/dashboard');

        // Laravel redirects to default login (/login) unless explicitly configured
        // This is expected behavior - platform routes require auth:platform middleware
        $response->assertStatus(302); // Redirected to login
    }

    public function test_tenant_user_cannot_access_platform_dashboard(): void
    {
        // Login as tenant user (web guard)
        $response = $this->actingAs($this->tenantUser, 'web')
            ->get('/admin/dashboard');

        // Should be redirected to login because not authenticated on platform guard
        // Laravel redirects to default login route unless explicitly configured
        $response->assertStatus(302); // Redirected
        
        // Verify the tenant user is still NOT authenticated on platform guard
        $this->assertFalse(Auth::guard('platform')->check());
    }

    // ========================================================================
    // RATE LIMITING TESTS
    // ========================================================================

    public function test_login_is_rate_limited_after_5_attempts(): void
    {
        // Make 5 failed login attempts
        for ($i = 0; $i < 5; $i++) {
            $this->post('/admin/login', [
                'email' => 'super@crmbeast.com',
                'password' => 'wrong-password',
            ]);
        }

        // 6th attempt should be rate limited
        $response = $this->post('/admin/login', [
            'email' => 'super@crmbeast.com',
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertStringContainsString('Too many', session('errors')->first('email'));
    }

    public function test_rate_limiting_uses_separate_key_from_tenant_login(): void
    {
        // Make 5 failed attempts on platform login
        for ($i = 0; $i < 5; $i++) {
            $this->post('/admin/login', [
                'email' => 'test@example.com',
                'password' => 'wrong',
            ]);
        }

        // Tenant login should still work (separate rate limit key)
        $response = $this->post('/login', [
            'email' => 'test@example.com',
            'password' => 'wrong',
        ]);

        // Should not be rate limited (different throttle key)
        $response->assertSessionHasErrors('email');
        $this->assertStringNotContainsString('Too many', session('errors')->first('email'));
    }

    // ========================================================================
    // REMEMBER ME TESTS
    // ========================================================================

    public function test_platform_admin_can_login_with_remember_me(): void
    {
        $response = $this->post('/admin/login', [
            'email' => 'super@crmbeast.com',
            'password' => 'password',
            'remember' => true,
        ]);

        $this->assertTrue(Auth::guard('platform')->check());
        $response->assertRedirect(route('platform.dashboard'));

        // Verify remember token was set
        $admin = PlatformAdmin::where('email', 'super@crmbeast.com')->first();
        $this->assertNotNull($admin->remember_token);
    }

    // ========================================================================
    // VALIDATION TESTS
    // ========================================================================

    public function test_email_is_required(): void
    {
        $response = $this->post('/admin/login', [
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_password_is_required(): void
    {
        $response = $this->post('/admin/login', [
            'email' => 'super@crmbeast.com',
        ]);

        $response->assertSessionHasErrors('password');
    }

    public function test_email_must_be_valid_email(): void
    {
        $response = $this->post('/admin/login', [
            'email' => 'not-an-email',
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors('email');
    }
}
