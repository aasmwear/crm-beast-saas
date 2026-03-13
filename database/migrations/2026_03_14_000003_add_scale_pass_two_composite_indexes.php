<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds high-value composite indexes from Scale & Index Audit (PR-2).
     */
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver !== 'pgsql') {
            // SQLite/MySQL: use schema builder where possible
            return;
        }

        DB::statement('CREATE INDEX IF NOT EXISTS idx_projects_org_status ON projects (organization_id, status)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_projects_org_pm ON projects (organization_id, project_manager_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_project_files_org_project ON project_files (organization_id, project_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_stripe_webhook_events_org ON stripe_webhook_events (organization_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_clients_org_status ON clients (organization_id, status)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_activities_subject ON activities (subject_type, subject_id)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver !== 'pgsql') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS idx_projects_org_status');
        DB::statement('DROP INDEX IF EXISTS idx_projects_org_pm');
        DB::statement('DROP INDEX IF EXISTS idx_project_files_org_project');
        DB::statement('DROP INDEX IF EXISTS idx_stripe_webhook_events_org');
        DB::statement('DROP INDEX IF EXISTS idx_clients_org_status');
        DB::statement('DROP INDEX IF EXISTS idx_activities_subject');
    }
};
