<?php

namespace Database\Factories;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AuditLog> */
final class AuditLogFactory extends Factory
{
    protected $model = AuditLog::class;

    public function definition(): array
    {
        return [
            'organization_id' => 1,
            'actor_id' => 1,
            'action' => 'create',
            'entity' => 'client',
            'entity_id' => 1,
            'changes' => [],
        ];
    }
}
