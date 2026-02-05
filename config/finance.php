<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Subscription Overdue Suspension Days
    |--------------------------------------------------------------------------
    |
    | The number of days an INV0 (membership service) invoice must be overdue
    | before the associated subscription is automatically suspended.
    |
    | Default: 14 days
    |
    */
    'subscription_overdue_suspension_days' => env('FINANCE_SUBSCRIPTION_OVERDUE_DAYS', 14),

    /*
    |--------------------------------------------------------------------------
    | Storage Overdue Block Days
    |--------------------------------------------------------------------------
    |
    | The number of days an INV3 (storage fee) invoice must be overdue
    | before customer custody operations are blocked.
    |
    | Default: 30 days
    |
    */
    'storage_overdue_block_days' => env('FINANCE_STORAGE_OVERDUE_DAYS', 30),

    /*
    |--------------------------------------------------------------------------
    | Default Due Date Days by Invoice Type
    |--------------------------------------------------------------------------
    |
    | Default due date settings per invoice type.
    | null means immediate payment expected (no due date).
    |
    */
    'default_due_date_days' => [
        'membership_service' => 30, // INV0
        'voucher_sale' => null,     // INV1 - immediate payment
        'shipping_redemption' => null, // INV2 - immediate payment
        'storage_fee' => 30,        // INV3
        'service_events' => null,   // INV4 - immediate payment
    ],

    /*
    |--------------------------------------------------------------------------
    | Xero Integration Settings
    |--------------------------------------------------------------------------
    |
    | Settings for Xero accounting integration.
    |
    */
    'xero' => [
        'max_retry_count' => env('XERO_MAX_RETRY_COUNT', 3),
        'sync_enabled' => env('XERO_SYNC_ENABLED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Stripe Integration Settings
    |--------------------------------------------------------------------------
    |
    | Settings for Stripe payment integration.
    |
    */
    'stripe' => [
        'webhook_tolerance' => env('STRIPE_WEBHOOK_TOLERANCE', 300),
    ],
];
