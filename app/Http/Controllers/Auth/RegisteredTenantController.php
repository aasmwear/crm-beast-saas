<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use App\Services\Billing\SeatCounter;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\PermissionRegistrar;

final class RegisteredTenantController extends Controller
{
    /**
     * Show the tenant registration form.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/RegisterTenant');
    }

    /**
     * Handle an incoming tenant registration request.
     * Creates User, Organization, attaches as owner, assigns Owner role, seeds onboarding data.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'company_name' => ['required', 'string', 'max:255'],
            'subdomain' => [
                'required',
                'string',
                'max:63',
                'alpha_dash',
                'unique:organizations,slug',
            ],
        ]);

        $user = DB::transaction(function () use ($data) {
            // 1. Create User
            $user = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => bcrypt($data['password']),
                'active_organization_id' => null, // Set after org creation
            ]);

            // 2. Create Organization (slug = subdomain) with 14-day trial
            $org = Organization::query()->create([
                'name' => $data['company_name'],
                'slug' => $data['subdomain'],
                'plan' => 'trial',
                'settings' => [],
                'trial_ends_at' => now()->addDays(14),
            ]);

            // 3. Seat limit check (staff user)
            app(SeatCounter::class)->assertCanAddSeat($org);

            // 4. Attach User to Organization with is_owner = true
            $user->organizations()->attach($org->id, ['is_owner' => true]);

            // 5. Set active organization
            $user->forceFill(['active_organization_id' => $org->id])->save();

            // 6. Assign Owner role (team-scoped via Spatie)
            $registrar = app(PermissionRegistrar::class);
            $registrar->setPermissionsTeamId($org->id);

            $ownerRole = \Spatie\Permission\Models\Role::findOrCreate('Owner', 'web');
            $user->syncRoles([$ownerRole]);

            // 7. Seed onboarding data: 1 default Project + 1 Sample Client
            $this->seedOnboardingData($org, $user);

            return $user;
        });

        event(new Registered($user));

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        Auth::login($user);

        $org = $user->activeOrganization;

        return redirect()->route('dashboard', ['organization' => $org->slug]);
    }

    /**
     * Create minimal onboarding data so the dashboard isn't empty.
     */
    private function seedOnboardingData(Organization $org, User $owner): void
    {
        $client = Client::query()->create([
            'organization_id' => $org->id,
            'company_name' => 'Sample Client',
            'primary_contact_name' => 'Contact Person',
            'primary_contact_email' => 'sample@example.com',
            'status' => 'Active',
            'assigned_account_manager_id' => $owner->id,
        ]);

        Project::query()->create([
            'organization_id' => $org->id,
            'client_id' => $client->id,
            'title' => 'Onboarding Project',
            'project_code' => strtoupper(Str::limit($org->slug, 3, '')).'-001',
            'description' => 'Your first project. Customize or remove as needed.',
            'project_manager_id' => $owner->id,
            'status' => 'Active',
            'billable' => true,
        ]);
    }
}
