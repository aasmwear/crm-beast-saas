<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class NotificationCenterController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var \App\Models\Organization $org */
        $org = $request->route('organization');

        /** @var \App\Models\User $user */
        $user = $request->user();

        $list = $user->notifications()
            ->whereRaw("data->>'organization_id' = ?", [(string) $org->id])
            ->latest()
            ->paginate(30)
            ->withQueryString();

        $unreadCount = $user->unreadNotifications()
            ->whereRaw("data->>'organization_id' = ?", [(string) $org->id])
            ->count();

        return Inertia::render('Notifications/Index', [
            'notifications' => $list,
            'unreadCount' => $unreadCount,
        ]);
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        /** @var \App\Models\Organization $org */
        $org = $request->route('organization');

        /** @var \App\Models\User $user */
        $user = $request->user();

        $user->unreadNotifications()
            ->whereRaw("data->>'organization_id' = ?", [(string) $org->id])
            ->update(['read_at' => now()]);

        return back()->with('success', 'All read');
    }

    public function markRead(Request $request, string $notification): RedirectResponse
    {
        /** @var \App\Models\Organization $org */
        $org = $request->route('organization');

        /** @var \App\Models\User $user */
        $user = $request->user();

        $n = $user->notifications()
            ->whereRaw("data->>'organization_id' = ?", [(string) $org->id])
            ->whereKey($notification)
            ->first();

        if ($n) {
            $n->markAsRead();
        }

        return back()->with('success', 'Read');
    }
}
