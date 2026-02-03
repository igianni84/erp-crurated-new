<?php

namespace App\Jobs\Allocation;

use App\Services\Allocation\VoucherTransferService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Job to expire pending voucher transfers past their expiration time.
 *
 * This job should be scheduled to run frequently (e.g., every minute)
 * to ensure timely expiration of transfers.
 */
class ExpireTransfersJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(VoucherTransferService $transferService): void
    {
        $expiredCount = $transferService->expireTransfers();

        if ($expiredCount > 0) {
            Log::info("Expired {$expiredCount} pending voucher transfers");
        }
    }
}
