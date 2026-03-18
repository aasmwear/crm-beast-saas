<?php

declare(strict_types=1);

/**
 * Data lifecycle retention windows.
 *
 * These define how long rows should be considered "hot" before becoming
 * candidates for archival or pruning. Values are in days.
 *
 * No destructive job reads these yet — they are used by:
 * - lifecycle:report (read-only reporting command)
 * - RetentionPolicy value object
 * - Future lifecycle/prune commands (Phase 2+)
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Retention Windows (days)
    |--------------------------------------------------------------------------
    |
    | category: hot | warm | cold
    |   hot  = keep indefinitely in primary table
    |   warm = archive after window (move to archive table or cold storage)
    |   cold = safe to prune/delete after window
    |
    | created_at_column: the timestamp column used to measure age
    |
    */

    'tables' => [

        'audit_logs' => [
            'category' => 'warm',
            'retention_days' => 90,
            'created_at_column' => 'created_at',
            'org_scoped' => true,
            'description' => 'Business audit trail; archive after 90 days',
        ],

        'activities' => [
            'category' => 'warm',
            'retention_days' => 90,
            'created_at_column' => 'created_at',
            'org_scoped' => true,
            'description' => 'Project/task activity feed; archive after 90 days',
        ],

        'notifications' => [
            'category' => 'warm',
            'retention_days' => 60,
            'created_at_column' => 'created_at',
            'org_scoped' => true,
            'description' => 'User-facing notifications; prune read after 180 days',
        ],

        'notification_events' => [
            'category' => 'warm',
            'retention_days' => 60,
            'created_at_column' => 'created_at',
            'org_scoped' => true,
            'description' => 'Org-level notification events',
        ],

        'stripe_webhook_events' => [
            'category' => 'cold',
            'retention_days' => 90,
            'created_at_column' => 'created_at',
            'org_scoped' => true,
            'description' => 'Webhook debug log; Stripe Dashboard is source of truth',
        ],

        'failed_jobs' => [
            'category' => 'cold',
            'retention_days' => 30,
            'created_at_column' => 'failed_at',
            'org_scoped' => false,
            'description' => 'Failed queue jobs; safe to prune after investigation window',
        ],

        'job_batches' => [
            'category' => 'cold',
            'retention_days' => 30,
            'created_at_column' => 'finished_at',
            'age_column_type' => 'integer', // Laravel stores unix timestamps in job_batches
            'org_scoped' => false,
            'description' => 'Queue batch metadata; safe to prune when batches are finished',
        ],

        'comments' => [
            'category' => 'warm',
            'retention_days' => 180,
            'created_at_column' => 'created_at',
            'org_scoped' => true,
            'description' => 'Project/task comments; archive with parent entity',
        ],

    ],

];
