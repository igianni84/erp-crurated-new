<?php

namespace App\Jobs\Allocation;

use App\Models\Allocation\TemporaryReservation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Job to expire temporary reservations past their expiration time.
 *
 * This job should be scheduled to run frequently (e.g., every minute)
 * to ensure timely expiration of reservations.
 */
class ExpireReservationsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    public int $timeout = 120;

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
    public function handle(): void
    {
        $expiredCount = 0;

        // Get all reservations that need to be expired
        $reservations = TemporaryReservation::needsExpiration()->get();

        foreach ($reservations as $reservation) {
            if ($reservation->expire()) {
                $expiredCount++;
            }
        }

        if ($expiredCount > 0) {
            Log::info("Expired {$expiredCount} temporary reservations");
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(?Throwable $exception): void
    {
        Log::error('ExpireReservationsJob failed permanently', [
            'error' => $exception?->getMessage(),
        ]);
    }
}
