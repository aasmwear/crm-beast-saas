<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class NotificationCenterController extends Controller
{
    /**
     * Return last 10 unread notifications for the dropdown (JSON). Scoped to current org.
     */
    public function list(Request $request): JsonResponse
    {
        /** @var \App\Models\Organization $org */
        $org = $request->route('organization');

        /** @var \App\Models\User $user */
        $user = $request->user();

        $unreadCount = $user->unreadNotifications()
            ->whereRaw("data->>'organization_id' = ?", [(string) $org->id])
            ->count();

        $notifications = $user->unreadNotifications()
            ->whereRaw("data->>'organization_id' = ?", [(string) $org->id])
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn ($n) => [
                'id' => $n->id,
                'type' => $n->data['type'] ?? 'unknown',
                'message' => $n->data['message'] ?? '',
                'url' => $n->data['url'] ?? null,
                'data' => $n->data,
                'read_at' => $n->read_at?->toIso8601String(),
                'created_at' => $n->created_at->toIso8601String(),
            ]);

        return response()->json([
            'notifications' => $notifications,
            'unread_count' => $unreadCount,
        ]);
    }

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

    public function markAllRead(Request $request): RedirectResponse|JsonResponse
    {
        /** @var \App\Models\Organization $org */
        $org = $request->route('organization');

        /** @var \App\Models\User $user */
        $user = $request->user();

        $user->unreadNotifications()
            ->whereRaw("data->>'organization_id' = ?", [(string) $org->id])
            ->update(['read_at' => now()]);

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('success', 'All read');
    }

    /**
     * Mark a single notification as read. Returns JSON when requested via AJAX.
     */
    public function markRead(Request $request, string $notification): RedirectResponse|JsonResponse
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

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('success', 'Read');
    }
}
