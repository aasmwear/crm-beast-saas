<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Activity;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Log activity events for Projects, Tasks, Invoices.
 *
 * @param  Model  $subject  Project, Task, or Invoice
 * @param  array<string, mixed>|null  $properties  Extra data (e.g. ["old_status" => "Planned", "new_status" => "In Progress"])
 */
final class ActivityLogger
{
    public static function log(
        User|int|null $user,
        Model $subject,
        string $description,
        ?array $properties = null,
    ): Activity {
        $userId = $user instanceof User ? $user->id : ($user ? (int) $user : null);

        return Activity::query()->create([
            'user_id' => $userId,
            'description' => $description,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'properties' => $properties,
        ]);
    }
}
