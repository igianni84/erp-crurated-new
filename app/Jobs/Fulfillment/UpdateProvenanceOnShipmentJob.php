<?php

namespace App\Jobs\Fulfillment;

use App\Models\Fulfillment\Shipment;
use App\Models\Inventory\SerializedBottle;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Job to update on-chain provenance after a shipment is confirmed.
 *
 * This job is dispatched asynchronously after a shipment is confirmed
 * to update the blockchain provenance records for all bottles shipped.
 *
 * Each bottle's on-chain record is updated to reflect:
 * - Shipment event (goods leaving warehouse)
 * - New owner (customer who received the shipment)
 * - Shipment timestamp
 */
class UpdateProvenanceOnShipmentJob implements ShouldQueue
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
        public Shipment $shipment
    ) {}

    /**
     * Execute the job.
     *
     * For each SerializedBottle in the shipment, calls the
     * blockchain service to update its provenance record.
     */
    public function handle(): void
    {
        $shipment = $this->shipment;
        $bottlesUpdated = 0;
        $bottlesFailed = 0;

        Log::info("Updating provenance for shipment {$shipment->id}");

        // Load shipment with shipping order and customer
        $shipment->load('shippingOrder.customer');

        $customer = $shipment->shippingOrder?->customer;
        if ($customer === null) {
            Log::warning("Cannot update provenance for shipment {$shipment->id}: no customer found");

            return;
        }

        $bottleSerials = $shipment->shipped_bottle_serials ?? [];

        if ($bottleSerials === []) {
            Log::info("No bottles to update provenance for shipment {$shipment->id}");

            return;
        }

        // Process each bottle serial
        foreach ($bottleSerials as $serial) {
            $bottle = SerializedBottle::where('serial_number', $serial)->first();

            if ($bottle === null) {
                Log::warning("Bottle with serial {$serial} not found for shipment {$shipment->id}");
                $bottlesFailed++;

                continue;
            }

            try {
                $this->updateBottleProvenance($bottle, $customer->id);
                $bottlesUpdated++;
            } catch (Exception $e) {
                Log::warning("Failed to update provenance for bottle {$serial}: {$e->getMessage()}");
                $bottlesFailed++;
            }
        }

        Log::info(
            "Provenance updated for shipment {$shipment->id}: {$bottlesUpdated} bottles updated, {$bottlesFailed} failed"
        );
    }

    /**
     * Update the blockchain provenance record for a single bottle.
     *
     * @param  SerializedBottle  $bottle  The bottle to update
     * @param  string  $newOwnerId  The ID of the new owner (customer)
     */
    protected function updateBottleProvenance(SerializedBottle $bottle, string $newOwnerId): void
    {
        // Skip bottles that don't have NFT yet
        if (! $bottle->hasNft()) {
            Log::debug("Skipping provenance update for bottle {$bottle->serial_number}: no NFT minted yet");

            return;
        }

        // Call blockchain service to update provenance
        $this->callBlockchainService($bottle, $newOwnerId);

        Log::debug("Provenance updated for bottle {$bottle->serial_number}");
    }

    /**
     * Call the blockchain service to update a bottle's provenance.
     *
     * This is a placeholder that should be replaced with actual blockchain integration.
     *
     * @param  SerializedBottle  $bottle  The bottle to update
     * @param  string  $newOwnerId  The ID of the new owner
     *
     * @throws Exception If the update fails
     */
    protected function callBlockchainService(SerializedBottle $bottle, string $newOwnerId): void
    {
        // TODO: Implement actual blockchain provenance update
        // This should call a blockchain service to update the NFT metadata
        // or append a new provenance event to the on-chain record.
        //
        // The update should include:
        // - Event type: shipment
        // - New owner: customer_id
        // - Timestamp: shipped_at
        //
        // In production: $this->blockchainService->recordShipment($bottle, $this->shipment);

        $shipment = $this->shipment;

        // Build provenance update data
        $provenanceData = [
            'nft_reference' => $bottle->nft_reference,
            'event_type' => 'shipment',
            'shipment_id' => $shipment->id,
            'carrier' => $shipment->carrier,
            'tracking_number' => $shipment->tracking_number,
            'new_owner_id' => $newOwnerId,
            'new_owner_type' => 'customer',
            'shipped_at' => $shipment->shipped_at?->toIso8601String(),
            'timestamp' => now()->toIso8601String(),
        ];

        // Simulate network delay
        // usleep(100000); // 100ms delay - commented out for tests

        Log::debug('Blockchain provenance shipment update simulated', $provenanceData);
    }

    /**
     * Handle a job failure.
     */
    public function failed(?Throwable $exception): void
    {
        Log::error(
            "UpdateProvenanceOnShipmentJob failed permanently for shipment {$this->shipment->id}",
            [
                'shipment_id' => $this->shipment->id,
                'carrier' => $this->shipment->carrier,
                'tracking_number' => $this->shipment->tracking_number,
                'bottles_count' => count($this->shipment->shipped_bottle_serials ?? []),
                'error' => $exception?->getMessage(),
            ]
        );
    }
}
