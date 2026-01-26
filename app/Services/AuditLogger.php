<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\User;

final class AuditLogger
{
    /**
     * Persist a new audit log entry.
     *
     * @param  string  $action  Short verb, e.g. "created", "updated", "deleted", "status_changed".
     * @param  string  $entity  Entity name, e.g. "client", "project", "task", "attendance".
     * @param  int  $entityId  Primary key of the entity.
     * @param  array<string, mixed>|null  $changes  Optional structured changes payload.
     */
    public static function log(
        Organization|int $organization,
        User|int|null $actor,
        string $action,
        string $entity,
        int $entityId,
        ?array $changes = null,
    ): AuditLog {
        $organizationId = $organization instanceof Organization ? $organization->id : (int) $organization;

        $actorId = null;
        if ($actor instanceof User) {
            $actorId = $actor->id;
        } elseif ($actor !== null) {
            $actorId = (int) $actor;
        }

        return AuditLog::query()->create([
            'organization_id' => $organizationId,
            'actor_id' => $actorId,
            'action' => $action,
            'entity' => $entity,
            'entity_id' => $entityId,
            'changes' => $changes,
        ]);
    }
}
