<?php

/**
 * Bootstrap for coverage runs.
 * Disables execution time limit before anything else loads.
 */
set_time_limit(0);
ini_set('max_execution_time', '0');

require __DIR__.'/../vendor/autoload.php';
