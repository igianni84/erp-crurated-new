<?php

namespace App\Jobs\Commercial;

use App\Enums\Commercial\OfferStatus;
use App\Models\AuditLog;
use App\Models\Commercial\Offer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Job to automatically expire offers when their validity period ends.
 *
 * This job should be scheduled to run periodically (e.g., every minute or every 5 minutes)
 * to check for active offers where valid_to < now() and transition them to expired status.
 *
 * Status transition: active → expired (automatic)
 * - Only active offers can be expired
 * - expired is set when now > valid_to
 * - This transition is logged in audit logs with system user
 */
class ExpireOffersJob implements ShouldQueue
{
    use Queueable;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 120; // 2 minutes

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
        $errorCount = 0;

        // Find all active offers where valid_to has passed
        $offersToExpire = Offer::query()
            ->where('status', OfferStatus::Active)
            ->whereNotNull('valid_to')
            ->where('valid_to', '<', now())
            ->get();

        if ($offersToExpire->isEmpty()) {
            return;
        }

        Log::info("Found {$offersToExpire->count()} offer(s) to auto-expire");

        foreach ($offersToExpire as $offer) {
            try {
                $offer->update(['status' => OfferStatus::Expired]);

                // Log the status transition
                $offer->auditLogs()->create([
                    'event' => AuditLog::EVENT_STATUS_CHANGE,
                    'old_values' => ['status' => OfferStatus::Active->value],
                    'new_values' => ['status' => OfferStatus::Expired->value],
                    'user_id' => null, // System-initiated, no user
                ]);

                $expiredCount++;

                Log::info("Auto-expired offer: {$offer->name} (ID: {$offer->id}), valid_to: {$offer->valid_to}");
            } catch (\Exception $e) {
                $errorCount++;

                Log::error("Failed to auto-expire offer: {$offer->name} (ID: {$offer->id})", [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($expiredCount > 0 || $errorCount > 0) {
            Log::info("Auto-expire offers complete: {$expiredCount} expired, {$errorCount} errors");
        }
    }
}
