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
    | Integration Logs Retention
    |--------------------------------------------------------------------------
    |
    | Retention settings for integration logs (Stripe webhooks, Xero sync logs).
    | Note: Audit logs are retained indefinitely per statutory requirements.
    | Integration logs can be cleaned up after the retention period.
    |
    */
    'logs' => [
        // Days to retain Stripe webhook logs (default: 90 days)
        'stripe_webhook_retention_days' => env('FINANCE_STRIPE_LOG_RETENTION_DAYS', 90),

        // Days to retain Xero sync logs (default: 90 days)
        'xero_sync_retention_days' => env('FINANCE_XERO_LOG_RETENTION_DAYS', 90),

        // Time to run the cleanup job
        'cleanup_job_time' => env('FINANCE_LOG_CLEANUP_TIME', '03:00'),

        // Sensitive fields to redact from log payloads
        'sensitive_fields' => [
            // Stripe-specific sensitive fields
            'card',
            'card_number',
            'cvc',
            'cvv',
            'exp_month',
            'exp_year',
            'number',
            'security_code',
            'bank_account',
            'account_number',
            'routing_number',
            'iban',
            'bic',
            'swift',
            // API keys and secrets
            'api_key',
            'api_secret',
            'secret_key',
            'private_key',
            'access_token',
            'refresh_token',
            'bearer_token',
            'authorization',
            'password',
            'secret',
            // Personal data that may be in payloads
            'ssn',
            'social_security_number',
            'tax_id',
            'national_id',
            'id_number',
            'passport_number',
            'drivers_license',
        ],
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
    | Storage Billing Configuration (INV3)
    |--------------------------------------------------------------------------
    |
    | Settings for storage billing calculation and invoicing.
    |
    */
    'storage' => [
        // Billing cycle for storage (monthly or quarterly)
        'billing_cycle' => env('FINANCE_STORAGE_BILLING_CYCLE', 'monthly'),

        // Default rate per bottle-day in EUR (used if no tier applies)
        'default_rate_per_bottle_day' => env('FINANCE_STORAGE_DEFAULT_RATE', '0.0050'),

        // Rate tiers based on bottle volume
        // Higher volume = lower rate (volume discount)
        'rate_tiers' => [
            ['min_bottles' => 0, 'max_bottles' => 100, 'rate' => '0.0060'],
            ['min_bottles' => 101, 'max_bottles' => 500, 'rate' => '0.0050'],
            ['min_bottles' => 501, 'max_bottles' => 1000, 'rate' => '0.0045'],
            ['min_bottles' => 1001, 'max_bottles' => null, 'rate' => '0.0040'],
        ],

        // Minimum charge per billing period (in base currency)
        'minimum_charge' => env('FINANCE_STORAGE_MINIMUM_CHARGE', '5.00'),

        // Time to run the billing job (first day of period)
        'billing_job_time' => env('FINANCE_STORAGE_BILLING_TIME', '05:00'),

        // Whether to auto-issue invoices when generating
        'auto_issue_invoices' => env('FINANCE_STORAGE_AUTO_ISSUE', true),
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
