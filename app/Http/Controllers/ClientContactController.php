<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class ClientContactController extends Controller
{
    /**
     * Store a new contact for the client.
     */
    public function store(Request $request, Organization $organization, Client $client): RedirectResponse
    {
        $this->authorize('update', $client);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'position' => ['nullable', 'string', 'max:255'],
            'is_primary' => ['boolean'],
        ]);

        $validated['client_id'] = $client->id;
        $validated['is_primary'] = (bool) ($validated['is_primary'] ?? false);

        if ($validated['is_primary']) {
            $client->contacts()->where('is_primary', true)->update(['is_primary' => false]);
        }

        $client->contacts()->create($validated);

        return back()->with('success', 'Contact added.');
    }

    /**
     * Update an existing contact.
     */
    public function update(Request $request, Organization $organization, Client $client, ClientContact $contact): RedirectResponse
    {
        $this->authorize('update', $client);

        if ((int) $contact->client_id !== (int) $client->id) {
            abort(404);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'position' => ['nullable', 'string', 'max:255'],
            'is_primary' => ['boolean'],
        ]);

        $validated['is_primary'] = (bool) ($validated['is_primary'] ?? false);

        if ($validated['is_primary']) {
            $client->contacts()->where('id', '!=', $contact->id)->where('is_primary', true)->update(['is_primary' => false]);
        }

        $contact->update($validated);

        return back()->with('success', 'Contact updated.');
    }

    /**
     * Remove a contact.
     */
    public function destroy(Organization $organization, Client $client, ClientContact $contact): RedirectResponse
    {
        $this->authorize('update', $client);

        if ((int) $contact->client_id !== (int) $client->id) {
            abort(404);
        }

        $contact->delete();

        return back()->with('success', 'Contact removed.');
    }
}
