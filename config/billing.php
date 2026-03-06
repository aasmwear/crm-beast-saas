<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Stripe price mapping by internal plan key
    |--------------------------------------------------------------------------
    | Internal plan keys remain canonical in-app. Stripe price IDs are used
    | only for external billing initiation. Null means manual/non-self-serve.
    */
    'stripe_prices' => [
        'starter' => env('STRIPE_PRICE_STARTER_MONTHLY'),
        'pro' => env('STRIPE_PRICE_PRO_MONTHLY'),
        'enterprise' => env('STRIPE_PRICE_ENTERPRISE_MONTHLY'),
    ],

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
