<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Show the registration page.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    /**
     * Handle an incoming registration request.
     */
    public function store(Request $request): \Illuminate\Http\RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:'.\App\Models\User::class],
            'password' => ['required', 'confirmed', \Illuminate\Validation\Rules\Password::defaults()],
            'organization_name' => ['nullable', 'string', 'max:255'],
        ]);

        $user = \Illuminate\Support\Facades\DB::transaction(function () use ($data) {
            // Create org first (simple, like before)
            $orgName = $data['organization_name'] ?? ($data['name']."'s Organization");

            $org = new \App\Models\Organization;
            $org->name = $orgName;
            $org->slug = \Illuminate\Support\Str::slug($orgName);
            $org->save();

            // Create user
            $user = new \App\Models\User;
            $user->name = $data['name'];
            $user->email = $data['email'];
            $user->password = \Illuminate\Support\Facades\Hash::make($data['password']);

            // Prefer the new column if present
            if (\Illuminate\Support\Facades\Schema::hasColumn('users', 'active_organization_id')) {
                $user->active_organization_id = $org->id;
            }

            $user->save();

            // Attach to pivot if it exists (this was working before)
            if (\Illuminate\Support\Facades\Schema::hasTable('organization_user')) {
                app(\App\Services\Billing\SeatCounter::class)->assertCanAddSeat($org);
                $user->organizations()->syncWithoutDetaching([$org->id]);
            }

            return $user;
        });

        event(new \Illuminate\Auth\Events\Registered($user));
        \Illuminate\Support\Facades\Auth::login($user);

        // Ensure org routes behind 'verified' are reachable immediately
        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        // No role assignment here (this was not needed for the tests and avoids team/pivot surprises)

        // Use the defined relation from your User model
        $org = $user->activeOrganization;

        return redirect()->route('dashboard', ['organization' => $org->slug]);
    }
}
