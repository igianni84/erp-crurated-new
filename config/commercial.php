<?php

return [
    /*
    |--------------------------------------------------------------------------
    | EMP Alert Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for EMP (Estimated Market Price) alerts and thresholds.
    |
    */

    'emp' => [
        /*
        |--------------------------------------------------------------------------
        | Deviation Threshold (Percentage)
        |--------------------------------------------------------------------------
        |
        | The percentage deviation from EMP that triggers an alert.
        | SKUs with prices differing more than this threshold from EMP
        | will be flagged in the Commercial Overview.
        |
        | Default: 15 (meaning 15%)
        |
        */
        'deviation_threshold' => (float) env('EMP_DEVIATION_THRESHOLD', 15.0),

        /*
        |--------------------------------------------------------------------------
        | Stale Data Threshold (Days)
        |--------------------------------------------------------------------------
        |
        | The number of days after which EMP data is considered stale.
        |
        | Default: 7
        |
        */
        'stale_threshold_days' => (int) env('EMP_STALE_THRESHOLD_DAYS', 7),
    ],
];
