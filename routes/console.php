<?php

use App\Jobs\Allocation\ExpireReservationsJob;
use App\Jobs\Allocation\ExpireTransfersJob;
use App\Jobs\Finance\IdentifyOverdueInvoicesJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule the job to expire temporary reservations every minute
Schedule::job(new ExpireReservationsJob)->everyMinute();

// Schedule the job to expire pending voucher transfers every minute
Schedule::job(new ExpireTransfersJob)->everyMinute();

// Schedule the job to identify overdue invoices daily at 8:00 AM
Schedule::job(new IdentifyOverdueInvoicesJob)->dailyAt('08:00');
