<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Catalog Search Toggle
    |--------------------------------------------------------------------------
    |
    | Controls whether the catalog search API endpoint is enabled.
    | Set to true in .env when Meilisearch is configured and indexed.
    |
    */

    'enabled' => env('CATALOG_SEARCH_ENABLED', false),

];
