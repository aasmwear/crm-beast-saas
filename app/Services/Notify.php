<?php

namespace App\Services;

use App\Models\NotificationEvent;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\InAppEventNotification;
use Illuminate\Support\Facades\Log;

final class Notify
{
    /**
     * Store an org-level notification event AND emit per-user Laravel database notifications.
     *
     * @param  array<int,int>  $recipientIds
     * @param  array<string,mixed>  $payload
     */
    public static function push(
        int|Organization $organization,
        string $type,
        ?int $actorId,
        array $recipientIds,
        string $entity,
        int $entityId,
        array $payload = []
    ): void {
        try {
            $orgId = $organization instanceof Organization ? (int) $organization->id : (int) $organization;

            $cleanRecipients = array_values(array_unique(array_filter(array_map('intval', $recipientIds))));

            $data = array_merge([
                'organization_id' => $orgId,
                'type' => $type,
                'entity' => $entity,
                'entity_id' => $entityId,
                'actor_id' => $actorId,
                'recipients' => $cleanRecipients,
            ], $payload);

            // Back-compat for your current Notifications/Index.vue
            if (! isset($data['message']) && isset($data['summary'])) {
                $data['message'] = $data['summary'];
            }

            // 1) Org-level event log (safe even if user has no prefs)
            NotificationEvent::create([
                'organization_id' => $orgId,
                'type' => $type,
                'entity' => $entity,
                'entity_id' => $entityId,
                'data' => $data,
            ]);

            // 2) Per-user notifications (only users that belong to the org)
            if ($cleanRecipients === []) {
                return;
            }

            $users = User::query()
                ->whereIn('id', $cleanRecipients)
                ->whereHas('organizations', static function ($q) use ($orgId): void {
                    $q->whereKey($orgId);
                })
                ->get();

            foreach ($users as $u) {
                $prefs = $u->notification_prefs ?? [];

                $inAppEnabled = (bool) data_get($prefs, 'channels.inapp', true);
                $typeEnabled = (bool) data_get($prefs, "types.$type", true);

                if (! $inAppEnabled || ! $typeEnabled) {
                    continue;
                }

                $u->notify(new InAppEventNotification($data));
            }
        } catch (\Throwable $e) {
            // Never break the request flow because of notifications.
            Log::warning('Notify::push failed', [
                'error' => $e->getMessage(),
                'type' => $type,
                'entity' => $entity,
                'entity_id' => $entityId,
            ]);
        }
    }
}
