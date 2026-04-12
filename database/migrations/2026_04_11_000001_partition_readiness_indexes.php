<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Read-path indexes for high-churn tables ahead of possible future LIST/RANGE
 * partitioning by organization_id. PostgreSQL only (matches scale-pass migrations).
 *
 * Replaces single-column stripe_webhook_events org index with composites that match
 * common filter shapes. Replaces activities (subject_type, subject_id) with an
 * organization-first composite for tenant-safe morph lookups.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS idx_stripe_webhook_events_org');
        DB::statement('DROP INDEX IF EXISTS idx_activities_subject');

        DB::statement(
            'CREATE INDEX IF NOT EXISTS idx_stripe_webhook_events_org_failed_created '
            .'ON stripe_webhook_events (organization_id, created_at) WHERE status = \'failed\'',
        );
        DB::statement(
            'CREATE INDEX IF NOT EXISTS idx_stripe_webhook_events_org_type '
            .'ON stripe_webhook_events (organization_id, type)',
        );
        DB::statement(
            'CREATE INDEX IF NOT EXISTS idx_stripe_webhook_events_org_processed '
            .'ON stripe_webhook_events (organization_id, processed_at DESC NULLS LAST)',
        );

        DB::statement(
            'CREATE INDEX IF NOT EXISTS idx_activities_org_subject '
            .'ON activities (organization_id, subject_type, subject_id)',
        );

        DB::statement(
            'CREATE INDEX IF NOT EXISTS tasks_organization_id_created_at_idx '
            .'ON tasks (organization_id, created_at) WHERE deleted_at IS NULL',
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS tasks_organization_id_created_at_idx');
        DB::statement('DROP INDEX IF EXISTS idx_activities_org_subject');
        DB::statement('DROP INDEX IF EXISTS idx_stripe_webhook_events_org_processed');
        DB::statement('DROP INDEX IF EXISTS idx_stripe_webhook_events_org_type');
        DB::statement('DROP INDEX IF EXISTS idx_stripe_webhook_events_org_failed_created');

        DB::statement('CREATE INDEX IF NOT EXISTS idx_stripe_webhook_events_org ON stripe_webhook_events (organization_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_activities_subject ON activities (subject_type, subject_id)');
    }
};
