<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Customer API Toggle
    |--------------------------------------------------------------------------
    |
    | Controls whether the customer-facing REST API is enabled.
    | Set to true in .env when the customer platform is ready.
    |
    */

    'enabled' => env('CUSTOMER_API_ENABLED', false),

];
