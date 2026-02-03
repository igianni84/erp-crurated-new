<?php

use App\Jobs\Allocation\ExpireReservationsJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule the job to expire temporary reservations every minute
Schedule::job(new ExpireReservationsJob)->everyMinute();
