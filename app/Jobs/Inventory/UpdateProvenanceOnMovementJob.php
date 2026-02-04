<?php

namespace App\Jobs\Inventory;

use App\Models\Inventory\InventoryMovement;
use App\Models\Inventory\SerializedBottle;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Job to update on-chain provenance after an inventory movement.
 *
 * This job is dispatched asynchronously after a movement is created
 * to update the blockchain provenance records for all bottles involved
 * in the movement.
 *
 * Each bottle's on-chain record is updated to reflect:
 * - New location
 * - Custody changes
 * - Movement timestamp
 */
class UpdateProvenanceOnMovementJob implements ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * Uses exponential backoff: 10s, 60s, 300s
     *
     * @var array<int, int>
     */
    public array $backoff = [10, 60, 300];

    /**
     * Create a new job instance.
     */
    public function __construct(
        public InventoryMovement $movement
    ) {}

    /**
     * Execute the job.
     *
     * For each SerializedBottle involved in the movement, calls the
     * blockchain service to update its provenance record.
     */
    public function handle(): void
    {
        $movement = $this->movement;
        $bottlesUpdated = 0;
        $casesProcessed = 0;

        Log::info("Updating provenance for movement {$movement->id}");

        // Load movement items with their relationships
        $movement->load(['movementItems.serializedBottle', 'movementItems.case.serializedBottles']);

        // Process each movement item
        foreach ($movement->movementItems as $item) {
            // Update provenance for individual bottles
            if ($item->serialized_bottle_id !== null) {
                $bottle = $item->serializedBottle;
                if ($bottle !== null) {
                    $this->updateBottleProvenance($bottle);
                    $bottlesUpdated++;
                }
            }

            // Update provenance for all bottles in cases
            if ($item->case_id !== null) {
                $case = $item->case;
                if ($case !== null) {
                    $casesProcessed++;
                    foreach ($case->serializedBottles as $bottle) {
                        $this->updateBottleProvenance($bottle);
                        $bottlesUpdated++;
                    }
                }
            }
        }

        Log::info("Provenance updated for movement {$movement->id}: {$bottlesUpdated} bottles, {$casesProcessed} cases");
    }

    /**
     * Update the blockchain provenance record for a single bottle.
     *
     * @param  SerializedBottle  $bottle  The bottle to update
     */
    protected function updateBottleProvenance(SerializedBottle $bottle): void
    {
        // Skip bottles that don't have NFT yet
        if (! $bottle->hasNft()) {
            Log::debug("Skipping provenance update for bottle {$bottle->serial_number}: no NFT minted yet");

            return;
        }

        try {
            // Call blockchain service to update provenance
            // TODO: Replace with actual blockchain service call
            $this->callBlockchainService($bottle);

            Log::debug("Provenance updated for bottle {$bottle->serial_number}");

        } catch (\Exception $e) {
            Log::warning("Failed to update provenance for bottle {$bottle->serial_number}: {$e->getMessage()}");
            // Don't re-throw - we want to continue processing other bottles
            // Individual bottle failures are logged but don't fail the whole job
        }
    }

    /**
     * Call the blockchain service to update a bottle's provenance.
     *
     * This is a placeholder that should be replaced with actual blockchain integration.
     *
     * @param  SerializedBottle  $bottle  The bottle to update
     *
     * @throws \Exception If the update fails
     */
    protected function callBlockchainService(SerializedBottle $bottle): void
    {
        // TODO: Implement actual blockchain provenance update
        // This should call a blockchain service to update the NFT metadata
        // or append a new provenance event to the on-chain record.
        //
        // The update should include:
        // - Movement type (transfer, consignment, etc.)
        // - New location
        // - Custody changes
        // - Timestamp
        //
        // In production: $this->blockchainService->updateProvenance($bottle, $this->movement);

        // Placeholder: simulate blockchain call
        // In a real implementation, this would interact with the blockchain

        $movement = $this->movement;

        // Build provenance update data
        $provenanceData = [
            'nft_reference' => $bottle->nft_reference,
            'movement_id' => $movement->id,
            'movement_type' => $movement->movement_type->value,
            'from_location' => $movement->source_location_id,
            'to_location' => $movement->destination_location_id,
            'custody_changed' => $movement->custody_changed,
            'timestamp' => $movement->executed_at->toIso8601String(),
        ];

        // Simulate network delay
        // usleep(100000); // 100ms delay - commented out for tests

        Log::debug('Blockchain provenance update simulated', $provenanceData);
    }

    /**
     * Handle a job failure.
     */
    public function failed(?\Throwable $exception): void
    {
        Log::error(
            "UpdateProvenanceOnMovementJob failed permanently for movement {$this->movement->id}",
            [
                'movement_id' => $this->movement->id,
                'movement_type' => $this->movement->movement_type->value,
                'items_count' => $this->movement->movementItems()->count(),
                'error' => $exception?->getMessage(),
            ]
        );
    }
}
