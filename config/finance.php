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
    | Immediate Invoice Alert Hours
    |--------------------------------------------------------------------------
    |
    | The number of hours after which an immediate payment invoice (INV1, INV2, INV4)
    | should trigger an alert if still unpaid. These invoice types expect payment
    | immediately at the time of transaction.
    |
    | INV1 (Voucher Sale) is the primary concern as it expects payment via Stripe
    | at checkout. An unpaid INV1 after 24h typically indicates a payment failure
    | or abandoned checkout that needs follow-up.
    |
    | Default: 24 hours
    |
    */
    'immediate_invoice_alert_hours' => env('FINANCE_IMMEDIATE_INVOICE_ALERT_HOURS', 24),

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

    /*
    |--------------------------------------------------------------------------
    | Pricing Settings (Module S Integration)
    |--------------------------------------------------------------------------
    |
    | Settings for pricing resolution and tax calculation.
    | These are used by PricingService for INV1 (voucher sales) invoices.
    |
    */
    'pricing' => [
        // Base currency for the business
        'base_currency' => env('FINANCE_BASE_CURRENCY', 'EUR'),

        // Default seller country for tax jurisdiction
        'seller_country' => env('FINANCE_SELLER_COUNTRY', 'IT'),

        // Whether to validate pricing from Module S before invoice creation
        'validate_pricing' => env('FINANCE_VALIDATE_PRICING', false),

        // Default VAT rate if country is unknown
        'default_vat_rate' => env('FINANCE_DEFAULT_VAT_RATE', '22.00'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Tax Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for tax calculation based on customer geography.
    |
    */
    'tax' => [
        // Enable reverse charge for B2B EU sales
        'enable_reverse_charge' => env('FINANCE_ENABLE_REVERSE_CHARGE', false),

        // Require valid VAT number for reverse charge
        'require_vat_number_for_reverse_charge' => env('FINANCE_REQUIRE_VAT_NUMBER', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Shipping Tax Configuration (INV2)
    |--------------------------------------------------------------------------
    |
    | Settings for shipping invoice VAT/duty calculation.
    | Tax rates are determined by destination country.
    |
    */
    'shipping' => [
        // Default shipping service VAT rate when destination is unknown
        'default_shipping_vat_rate' => env('FINANCE_DEFAULT_SHIPPING_VAT', '22.00'),

        // Enable duty calculation for cross-border shipments
        'enable_duty_calculation' => env('FINANCE_ENABLE_DUTY_CALCULATION', true),

        // De minimis threshold for duty exemption (in EUR)
        'de_minimis_threshold_eur' => env('FINANCE_DE_MINIMIS_EUR', '150.00'),

        // EU countries list (for intra-community vs export determination)
        'eu_countries' => [
            'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR',
            'DE', 'GR', 'HU', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL',
            'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE',
        ],
    ],
];
