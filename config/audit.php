<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Audit Log Archival Configuration
    |--------------------------------------------------------------------------
    |
    | Controls the automatic cleanup of old audit_logs and ai_audit_logs records.
    | Both tables are append-only and grow indefinitely; this job prunes records
    | older than the configured retention period.
    |
    */

    'archival' => [

        // Number of days to retain audit log records before archival/deletion
        'retention_days' => env('AUDIT_RETENTION_DAYS', 365),

        // Number of days to retain AI audit log records before archival/deletion
        'ai_retention_days' => env('AUDIT_AI_RETENTION_DAYS', 180),

        // Time of day to run the archival job (24h format)
        'job_time' => env('AUDIT_ARCHIVAL_TIME', '03:30'),

        // Number of records to delete per batch (to avoid lock contention)
        'batch_size' => env('AUDIT_ARCHIVAL_BATCH_SIZE', 5000),
    ],
];
