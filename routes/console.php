<?php

use App\Jobs\Allocation\ExpireReservationsJob;
use App\Jobs\Allocation\ExpireTransfersJob;
use App\Jobs\Finance\AlertUnpaidImmediateInvoicesJob;
use App\Jobs\Finance\BlockOverdueStorageBillingJob;
use App\Jobs\Finance\GenerateStorageBillingJob;
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

// Schedule the job to generate storage billing (INV3) on the first day of each month
// This runs at 5:00 AM on the 1st to process the previous month's storage billing
// Uses forPreviousMonth() factory method to calculate the billing period automatically
Schedule::job(GenerateStorageBillingJob::forPreviousMonth(
    autoGenerateInvoices: config('finance.storage.auto_issue_invoices', true),
    autoIssue: config('finance.storage.auto_issue_invoices', true)
))->monthlyOn(1, '05:00');

// Schedule the job to block storage billing periods with overdue INV3 daily at 10:00 AM
// This runs after subscription suspension (9:00 AM) to check for overdue storage invoices
// and block custody operations for customers with unpaid storage fees
Schedule::job(new BlockOverdueStorageBillingJob)->dailyAt('10:00');
