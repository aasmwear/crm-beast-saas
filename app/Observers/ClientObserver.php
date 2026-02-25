<?php

namespace App\Observers;

use App\Models\Client;
use App\Models\Organization;
use App\Services\Notify;
use Illuminate\Support\Facades\Auth;

final class ClientObserver
{
    public function created(Client $client): void
    {
        self::activity($client, 'client_created');
    }

    public function updated(Client $client): void
    {
        // FIX: Correct the event type to 'client_updated'
        self::activity($client, 'client_updated');
    }

    protected static function activity(Client $client, string $type): void
    {
        /** @var Organization|null $org */
        $org = $client->organization;
        if (! $org) {
            return;
        }

        $recipients = [];
        if ($client->fronter_id) {
            $recipients[] = (int) $client->fronter_id;
        }
        if ($client->closer_id) {
            $recipients[] = (int) $client->closer_id;
        }
        if ($client->assigned_account_manager_id) {
            $recipients[] = (int) $client->assigned_account_manager_id;
        }

        Notify::push(
            $org,
            'new_activity',
            Auth::id(),
            array_values(array_unique($recipients)),
            'client',
            (int) $client->id,
            [
                // FIX: Use $type in the summary to reflect the action
                'summary' => 'Client '.$type.': '.(string) $client->company_name,
                'type' => $type,
            ]
        );
    }
}
