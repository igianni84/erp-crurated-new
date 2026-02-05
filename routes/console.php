<?php

use App\Jobs\Allocation\ExpireReservationsJob;
use App\Jobs\Allocation\ExpireTransfersJob;
use App\Jobs\Finance\AlertUnpaidImmediateInvoicesJob;
use App\Jobs\Finance\IdentifyOverdueInvoicesJob;
use App\Jobs\Finance\ProcessSubscriptionBillingJob;
use App\Jobs\Finance\SuspendOverdueSubscriptionsJob;
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

// Schedule the job to process subscription billing daily at 6:00 AM (before overdue check)
Schedule::job(new ProcessSubscriptionBillingJob(autoIssue: true))->dailyAt('06:00');

// Schedule the job to suspend subscriptions with overdue INV0 daily at 9:00 AM (after overdue check)
Schedule::job(new SuspendOverdueSubscriptionsJob)->dailyAt('09:00');

// Schedule the job to alert on unpaid immediate invoices (INV1, INV2, INV4) hourly
// This runs hourly to provide timely alerts for INV1 invoices that should have been paid immediately
Schedule::job(new AlertUnpaidImmediateInvoicesJob)->hourly();
