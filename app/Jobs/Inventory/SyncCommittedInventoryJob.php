<?php

namespace App\Jobs\Inventory;

use App\Enums\Allocation\AllocationStatus;
use App\Enums\Allocation\VoucherLifecycleState;
use App\Models\Allocation\Allocation;
use App\Models\Allocation\Voucher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Job to sync committed inventory quantities from Module A (Allocations/Vouchers).
 *
 * This job runs periodically (configurable, default every hour) to:
 * - Count unredeemed vouchers per allocation
 * - Cache the committed_quantity for each allocation
 *
 * The cached values are used by InventoryService for performance optimization
 * when checking if bottles can be consumed or are committed.
 */
class SyncCommittedInventoryJob implements ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * Uses exponential backoff: 30s, 120s, 300s
     *
     * @var array<int, int>
     */
    public array $backoff = [30, 120, 300];

    /**
     * Cache TTL in seconds (2 hours to allow for job failures).
     * The job runs hourly by default, so 2 hours provides buffer.
     */
    public const CACHE_TTL = 7200;

    /**
     * Cache key prefix for committed quantities.
     */
    public const CACHE_KEY_PREFIX = 'inventory:committed_quantity:allocation:';

    /**
     * Cache key for the last sync timestamp.
     */
    public const LAST_SYNC_KEY = 'inventory:committed_quantity:last_sync';

    /**
     * Execute the job.
     *
     * Iterates through all relevant allocations and caches their committed quantities.
     */
    public function handle(): void
    {
        Log::info('SyncCommittedInventoryJob started');

        $startTime = microtime(true);
        $processedCount = 0;
        $errorCount = 0;

        // Get all allocations that could have committed inventory
        // Include Active, Exhausted (still have vouchers outstanding)
        // Exclude Draft (not yet sold), Closed (fully resolved)
        $allocations = Allocation::query()
            ->whereIn('status', [
                AllocationStatus::Active,
                AllocationStatus::Exhausted,
            ])
            ->cursor(); // Use cursor for memory efficiency

        foreach ($allocations as $allocation) {
            try {
                $this->syncAllocationCommittedQuantity($allocation);
                $processedCount++;
            } catch (\Exception $e) {
                $errorCount++;
                Log::error(
                    "Failed to sync committed quantity for allocation {$allocation->id}",
                    ['error' => $e->getMessage()]
                );
            }
        }

        // Record last sync timestamp
        Cache::put(self::LAST_SYNC_KEY, now()->toIso8601String(), self::CACHE_TTL);

        $duration = round(microtime(true) - $startTime, 2);
        Log::info('SyncCommittedInventoryJob completed', [
            'processed' => $processedCount,
            'errors' => $errorCount,
            'duration_seconds' => $duration,
        ]);
    }

    /**
     * Sync committed quantity for a single allocation.
     *
     * Counts unredeemed vouchers (Issued + Locked states) and caches the result.
     */
    protected function syncAllocationCommittedQuantity(Allocation $allocation): void
    {
        $committedQuantity = Voucher::where('allocation_id', $allocation->id)
            ->whereIn('lifecycle_state', [
                VoucherLifecycleState::Issued,
                VoucherLifecycleState::Locked,
            ])
            ->count();

        $cacheKey = self::CACHE_KEY_PREFIX.$allocation->id;
        Cache::put($cacheKey, $committedQuantity, self::CACHE_TTL);

        Log::debug("Synced committed quantity for allocation {$allocation->id}: {$committedQuantity}");
    }

    /**
     * Get the cached committed quantity for an allocation.
     *
     * Returns null if not cached (fallback to live query in InventoryService).
     */
    public static function getCachedCommittedQuantity(Allocation $allocation): ?int
    {
        $cacheKey = self::CACHE_KEY_PREFIX.$allocation->id;
        $cached = Cache::get($cacheKey);

        return is_int($cached) ? $cached : null;
    }

    /**
     * Invalidate the cached committed quantity for an allocation.
     *
     * Call this when vouchers are created, redeemed, or cancelled
     * to ensure the cache is refreshed on next sync.
     */
    public static function invalidateCache(Allocation $allocation): void
    {
        $cacheKey = self::CACHE_KEY_PREFIX.$allocation->id;
        Cache::forget($cacheKey);
    }

    /**
     * Get the timestamp of the last successful sync.
     */
    public static function getLastSyncTimestamp(): ?string
    {
        $timestamp = Cache::get(self::LAST_SYNC_KEY);

        return is_string($timestamp) ? $timestamp : null;
    }

    /**
     * Check if the cache is considered fresh (synced within the last 2 hours).
     */
    public static function isCacheFresh(): bool
    {
        $lastSync = self::getLastSyncTimestamp();

        if ($lastSync === null) {
            return false;
        }

        try {
            $lastSyncTime = \Carbon\Carbon::parse($lastSync);

            return $lastSyncTime->diffInSeconds(now()) < self::CACHE_TTL;
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(?\Throwable $exception): void
    {
        Log::error(
            'SyncCommittedInventoryJob failed permanently',
            ['error' => $exception?->getMessage()]
        );
    }
}
