<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

final class PortalAccessController extends Controller
{
    /**
     * Enable portal access for a contact: create a User (or use existing) and send set-password link.
     */
    public function store(Request $request, Organization $organization, Client $client, ClientContact $contact): RedirectResponse
    {
        $this->authorize('update', $client);

        if ((int) $contact->client_id !== (int) $client->id) {
            abort(404);
        }

        $email = $contact->email;
        if (empty($email) || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return back()->with('error', 'Contact must have a valid email to enable portal access.');
        }

        $existingUser = User::where('email', $email)->first();
        if ($existingUser) {
            if ((int) $existingUser->client_id === (int) $client->id) {
                return back()->with('info', 'This contact already has portal access.');
            }
            return back()->with('error', 'A user with this email already exists for another client.');
        }

        $password = Str::random(32);
        $user = User::create([
            'name' => $contact->name,
            'email' => $email,
            'password' => Hash::make($password),
            'client_id' => $client->id,
            'email_verified_at' => now(),
        ]);

        $user->organizations()->syncWithoutDetaching([$organization->id]);
        $user->forceFill(['active_organization_id' => $organization->id])->save();

        app(PermissionRegistrar::class)->setPermissionsTeamId($organization->id);
        $user->assignRole('Client');

        Password::sendResetLink(['email' => $user->email]);

        return back()->with('success', 'Portal access enabled. A password-set link was sent to ' . $user->email);
    }
}
