<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Enterprise plan overrides
    |--------------------------------------------------------------------------
    | Optional config for enterprise seats and entitlements (PlanCatalog uses these).
    */
    'enterprise_seats' => (int) env('BILLING_ENTERPRISE_SEATS', 500),

    'enterprise_entitlements' => [
        'attendance' => true,
        'sms' => true,
        'api_access' => true,
        'storage_gb' => (int) env('BILLING_ENTERPRISE_STORAGE_GB', 500),
        'api_rpm' => (int) env('BILLING_ENTERPRISE_API_RPM', 2000),
    ],
];
