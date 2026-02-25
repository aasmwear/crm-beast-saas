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
        abort_unless($request->user()?->can('notifications.view'), 403);

        /** @var \App\Models\Organization $org */
        $org = $request->route('organization');

        /** @var \App\Models\User $user */
        $user = $request->user();

        $unreadCount = $this->scopeNotificationsByOrg($user->unreadNotifications(), $org)
            ->count();

        $notifications = $this->scopeNotificationsByOrg($user->unreadNotifications(), $org)
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
        abort_unless($request->user()?->can('notifications.view'), 403);

        /** @var \App\Models\Organization $org */
        $org = $request->route('organization');

        /** @var \App\Models\User $user */
        $user = $request->user();

        $list = $this->scopeNotificationsByOrg($user->notifications(), $org)
            ->latest()
            ->paginate(30)
            ->withQueryString();

        $unreadCount = $this->scopeNotificationsByOrg($user->unreadNotifications(), $org)
            ->count();

        return Inertia::render('Notifications/Index', [
            'notifications' => $list,
            'unreadCount' => $unreadCount,
        ]);
    }

    public function markAllRead(Request $request): RedirectResponse|JsonResponse
    {
        abort_unless($request->user()?->can('notifications.update'), 403);

        /** @var \App\Models\Organization $org */
        $org = $request->route('organization');

        /** @var \App\Models\User $user */
        $user = $request->user();

        $this->scopeNotificationsByOrg($user->unreadNotifications(), $org)
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
        abort_unless($request->user()?->can('notifications.update'), 403);

        /** @var \App\Models\Organization $org */
        $org = $request->route('organization');

        /** @var \App\Models\User $user */
        $user = $request->user();

        $n = $this->scopeNotificationsByOrg($user->notifications(), $org)
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

    /**
     * Scope notifications by organization: use organization_id column when set, else fallback to JSON.
     */
    private function scopeNotificationsByOrg($query, $org)
    {
        $id = (string) $org->id;

        return $query->where(function ($q) use ($id) {
            $q->where('organization_id', $id)
                ->orWhereRaw("(organization_id IS NULL AND data->>'organization_id' = ?)", [$id]);
        });
    }
}
