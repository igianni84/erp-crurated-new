<?php

namespace App\Jobs\Inventory;

use App\Models\Inventory\SerializedBottle;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Job to mint provenance NFT for a serialized bottle.
 *
 * This job is dispatched asynchronously after bottle serialization
 * to mint an NFT on the blockchain representing the bottle's provenance.
 *
 * NFT minting is separate from serialization and can occur at a different time.
 */
class MintProvenanceNftJob implements ShouldQueue
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
        public SerializedBottle $bottle
    ) {}

    /**
     * Execute the job.
     *
     * Calls the blockchain service to mint an NFT for the bottle.
     * On success, updates the bottle's nft_reference and nft_minted_at fields.
     */
    public function handle(): void
    {
        // Skip if NFT already minted
        if ($this->bottle->hasNft()) {
            Log::info("NFT already minted for bottle {$this->bottle->serial_number}");

            return;
        }

        try {
            // Mint NFT via blockchain service
            // TODO: Replace with actual blockchain service call
            $nftReference = $this->mintNft();

            // Update bottle with NFT reference
            $this->bottle->update([
                'nft_reference' => $nftReference,
                'nft_minted_at' => now(),
            ]);

            Log::info("NFT minted for bottle {$this->bottle->serial_number}: {$nftReference}");

        } catch (Exception $e) {
            Log::error("Failed to mint NFT for bottle {$this->bottle->serial_number}: {$e->getMessage()}");

            throw $e; // Re-throw to trigger retry
        }
    }

    /**
     * Mint the NFT on the blockchain.
     *
     * This is a placeholder that should be replaced with actual blockchain integration.
     *
     * @return string The NFT reference/token ID
     *
     * @throws Exception If minting fails
     */
    protected function mintNft(): string
    {
        // TODO: Implement actual blockchain NFT minting
        // This should call a blockchain service (e.g., Ethereum, Polygon)
        // to mint an NFT representing this bottle's provenance.
        //
        // The NFT should contain:
        // - Bottle serial number
        // - Wine variant information
        // - Allocation lineage
        // - Serialization timestamp
        // - Location of serialization
        //
        // For now, we generate a placeholder reference
        // In production, this would be the actual blockchain transaction hash or token ID

        // Simulate blockchain service call
        // In production: return $this->blockchainService->mintProvenanceNft($this->bottle);

        $tokenId = 'NFT-'.strtoupper(bin2hex(random_bytes(16)));

        return $tokenId;
    }

    /**
     * Handle a job failure.
     */
    public function failed(?Throwable $exception): void
    {
        Log::error(
            "MintProvenanceNftJob failed permanently for bottle {$this->bottle->serial_number}",
            [
                'bottle_id' => $this->bottle->id,
                'serial_number' => $this->bottle->serial_number,
                'error' => $exception?->getMessage(),
            ]
        );
    }
}
