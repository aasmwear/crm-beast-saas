<?php

namespace App\Http\Controllers\Platform\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Platform Authenticated Session Controller
 * 
 * Handles authentication for Platform Admins (Super Admins and Support staff).
 * Uses the 'platform' guard, completely separate from tenant authentication.
 */
class AuthenticatedSessionController extends Controller
{
    /**
     * Display the platform login view.
     */
    public function create(): Response
    {
        return Inertia::render('Platform/Auth/Login', [
            'canResetPassword' => true,
            'status' => session('status'),
        ]);
    }

    /**
     * Handle an incoming platform authentication request.
     * 
     * CRITICAL: Uses 'platform' guard, not 'web' guard.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        // Authenticate using the platform guard
        $request->authenticate();

        // Regenerate session to prevent fixation attacks
        $request->session()->regenerate();

        // Redirect to platform dashboard
        return redirect()->intended(route('platform.dashboard'));
    }

    /**
     * Log the platform admin out of the application.
     */
    public function destroy(Request $request): RedirectResponse
    {
        // Logout from platform guard
        Auth::guard('platform')->logout();

        // Invalidate the session
        $request->session()->invalidate();

        // Regenerate CSRF token
        $request->session()->regenerateToken();

        // Redirect to platform login
        return redirect()->route('platform.login');
    }
}
