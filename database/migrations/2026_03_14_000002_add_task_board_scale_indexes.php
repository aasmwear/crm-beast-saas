<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('CREATE INDEX IF NOT EXISTS tasks_assignees_gin_idx ON tasks USING GIN (assignees jsonb_path_ops)');
        DB::statement('CREATE INDEX IF NOT EXISTS tasks_organization_id_status_idx ON tasks (organization_id, status)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS tasks_assignees_gin_idx');
        DB::statement('DROP INDEX IF EXISTS tasks_organization_id_status_idx');
    }
};
