<?php

namespace App\DataTransferObjects\Commercial;

/**
 * Data Transfer Object representing the result of a price simulation.
 *
 * Encapsulates the complete breakdown of price resolution steps:
 * 1. Allocation Check
 * 2. EMP Reference
 * 3. Price Book Resolution
 * 4. Offer Resolution
 * 5. Final Price Calculation
 */
class SimulationResult
{
    /**
     * @param  SimulationContext  $context  The simulation input context
     * @param  AllocationCheckResult  $allocationCheck  Step 1: Allocation verification
     * @param  EmpReferenceResult  $empReference  Step 2: EMP reference data
     * @param  PriceBookResolutionResult  $priceBookResolution  Step 3: Price Book lookup
     * @param  OfferResolutionResult  $offerResolution  Step 4: Offer application
     * @param  FinalPriceResult  $finalPrice  Step 5: Final price computation
     * @param  array<int, string>  $errors  Blocking errors that prevented simulation
     * @param  array<int, string>  $warnings  Non-blocking warnings
     */
    public function __construct(
        public readonly SimulationContext $context,
        public readonly AllocationCheckResult $allocationCheck,
        public readonly EmpReferenceResult $empReference,
        public readonly PriceBookResolutionResult $priceBookResolution,
        public readonly OfferResolutionResult $offerResolution,
        public readonly FinalPriceResult $finalPrice,
        public readonly array $errors = [],
        public readonly array $warnings = [],
    ) {}

    /**
     * Check if the simulation completed successfully.
     */
    public function isSuccess(): bool
    {
        return empty($this->errors) && $this->finalPrice->hasPrice();
    }

    /**
     * Check if there were blocking errors.
     */
    public function hasErrors(): bool
    {
        return ! empty($this->errors);
    }

    /**
     * Check if there were warnings.
     */
    public function hasWarnings(): bool
    {
        return ! empty($this->warnings);
    }

    /**
     * Get a summary status of the simulation.
     */
    public function getStatus(): string
    {
        if ($this->hasErrors()) {
            return 'error';
        }

        if ($this->hasWarnings()) {
            return 'warning';
        }

        return 'success';
    }

    /**
     * Convert to array for serialization.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'context' => $this->context->toArray(),
            'steps' => [
                'allocation' => $this->allocationCheck->toArray(),
                'emp' => $this->empReference->toArray(),
                'price_book' => $this->priceBookResolution->toArray(),
                'offer' => $this->offerResolution->toArray(),
                'final' => $this->finalPrice->toArray(),
            ],
            'errors' => $this->errors,
            'warnings' => $this->warnings,
            'status' => $this->getStatus(),
            'final_price' => $this->finalPrice->finalPrice,
        ];
    }
}
