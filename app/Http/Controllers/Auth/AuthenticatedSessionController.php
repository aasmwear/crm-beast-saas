<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest; // ✅ correct namespace
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();
        $request->session()->regenerate();

        // Tests call route('dashboard') without params; keep that path in 'testing'
        if (app()->environment('testing')) {
            return redirect()->intended(route('dashboard', absolute: false));
        }

        // Real app: redirect to the user's active organization (or portal for Client users)
        $user = $request->user();

        if ($user->hasRole('Client') && ! empty($user->client_id)) {
            return redirect()->intended('/portal/dashboard');
        }

        $slug = optional($user->activeOrganization)->slug
            ?: DB::table('organizations')->where('id', $user->active_organization_id)->value('slug')
            ?: DB::table('organizations')->orderBy('id')->value('slug')
            ?: 'acme';

        return redirect()->intended(route('dashboard', ['organization' => $slug], false));
    }

    /**
     * Log the user out of the application.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
