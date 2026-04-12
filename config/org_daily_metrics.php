<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Snapshot-first read mode for all tenants (operator override)
    |--------------------------------------------------------------------------
    |
    | When true, every organization is treated like a large/enterprise tenant for
    | snapshot-first read paths (e.g. Org Health seats_active): prefer the latest
    | org_daily_metrics row before falling back to live queries. Intended for staging
    | or emergency read offloading; default false.
    |
    */
    'snapshot_only_all_tenants' => (bool) env('ORG_SNAPSHOT_READ_ALL_TENANTS', false),
];
