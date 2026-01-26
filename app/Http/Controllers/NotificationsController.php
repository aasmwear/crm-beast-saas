<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class NotificationsController extends Controller
{
    public function settings(Request $request): Response
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $defaults = [
            'channels' => [
                'inapp' => true,
                'email' => false,
            ],
            'types' => [],
        ];

        $prefs = array_replace_recursive($defaults, $user->notification_prefs ?? []);

        return Inertia::render('Notifications/Settings', [
            'prefs' => $prefs,
        ]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $data = $request->validate([
            'prefs' => 'required|array',
            'prefs.channels' => 'required|array',
            'prefs.channels.inapp' => 'required|boolean',
            'prefs.channels.email' => 'required|boolean',
            'prefs.types' => 'nullable|array',
        ]);

        $user->forceFill([
            'notification_prefs' => $data['prefs'],
        ])->save();

        return back()->with('success', 'Notification settings saved');
    }
}
