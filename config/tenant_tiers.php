<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Tenant tier thresholds (read-only classification)
    |--------------------------------------------------------------------------
    |
    | Each metric uses three ascending cut points [medium, large, enterprise].
    | Below the first value => small; between first (inclusive) and second => medium;
    | between second (inclusive) and third => large; at or above third => enterprise.
    |
    | Final tier is the maximum tier implied by any single dimension (highest wins).
    |
    */
    'thresholds' => [
        'clients' => [25, 100, 500],
        'projects' => [10, 50, 200],
        'tasks' => [100, 500, 2000],
        'webhook_events_total' => [200, 2000, 10000],
    ],

];
