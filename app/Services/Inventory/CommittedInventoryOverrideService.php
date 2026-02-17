<?php

namespace App\Services\Inventory;

use App\Enums\Inventory\ConsumptionReason;
use App\Models\Inventory\InventoryException;
use App\Models\Inventory\InventoryMovement;
use App\Models\Inventory\Location;
use App\Models\Inventory\SerializedBottle;
use App\Models\User;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Service for handling exceptional committed inventory consumption.
 *
 * This service handles the override flow for consuming inventory that is
 * committed to voucher fulfillment. This is an EXCEPTIONAL operation that:
 *
 * 1. Requires special permission (Admin+)
 * 2. Requires explicit justification (mandatory text)
 * 3. Creates InventoryException records for finance & ops review
 * 4. Is intentionally painful (multiple confirmations required)
 * 5. Maintains full audit trail
 *
 * This is NOT a normal operation and should only be used in exceptional
 * circumstances where committed inventory must be consumed.
 */
class CommittedInventoryOverrideService
{
    /**
     * Exception type for committed inventory consumption override.
     */
    public const EXCEPTION_TYPE = 'committed_consumption_override';

    public function __construct(
        private readonly InventoryService $inventoryService,
        private readonly MovementService $movementService
    ) {}

    /**
     * Check if a user can perform committed inventory override.
     *
     * @param  User  $user  The user attempting the override
     * @return bool True if user has permission
     */
    public function canPerformOverride(User $user): bool
    {
        return $user->canConsumeCommittedInventory();
    }

    /**
     * Get committed bottles at a location that could be overridden.
     *
     * @param  Location  $location  The location to check
     * @return Collection<int, SerializedBottle> Committed bottles at the location
     */
    public function getCommittedBottlesForOverride(Location $location): Collection
    {
        return $this->inventoryService->getCommittedBottlesAtLocation($location);
    }

    /**
     * Validate the override request.
     *
     * @param  User  $user  The user attempting the override
     * @param  string  $justification  The justification text
     * @param  array<int, SerializedBottle>  $bottles  The bottles to consume
     * @return array{valid: bool, errors: array<int, string>}
     */
    public function validateOverrideRequest(
        User $user,
        string $justification,
        array $bottles
    ): array {
        $errors = [];

        // Check user permission
        if (! $this->canPerformOverride($user)) {
            $errors[] = 'You do not have permission to consume committed inventory.';
        }

        // Check justification is not empty
        if (trim($justification) === '') {
            $errors[] = 'Justification is required for committed inventory override.';
        }

        // Check justification is detailed enough (minimum 20 characters)
        if (strlen(trim($justification)) < 20) {
            $errors[] = 'Justification must be at least 20 characters. Please provide a detailed explanation.';
        }

        // Check at least one bottle selected
        if (count($bottles) === 0) {
            $errors[] = 'No bottles selected for override.';
        }

        // Verify all bottles are actually committed
        foreach ($bottles as $bottle) {
            if (! $this->inventoryService->isCommittedForFulfillment($bottle)) {
                $errors[] = "Bottle {$bottle->serial_number} is not committed and should use normal consumption flow.";
            }
        }

        return [
            'valid' => count($errors) === 0,
            'errors' => $errors,
        ];
    }

    /**
     * Execute the committed inventory consumption override.
     *
     * This method performs the exceptional consumption of committed inventory.
     * It creates InventoryException records for each bottle consumed to
     * flag them for finance & ops review.
     *
     * @param  User  $user  The user performing the override
     * @param  string  $justification  The mandatory justification text
     * @param  array<int, SerializedBottle>  $bottles  The committed bottles to consume
     * @param  ConsumptionReason  $reason  The consumption reason
     * @param  string|null  $notes  Additional notes
     * @return array{
     *     success: bool,
     *     consumed_count: int,
     *     exceptions: Collection<int, InventoryException>,
     *     movements: Collection<int, InventoryMovement>,
     *     errors: array<int, string>
     * }
     *
     * @throws InvalidArgumentException If validation fails
     */
    public function executeOverride(
        User $user,
        string $justification,
        array $bottles,
        ConsumptionReason $reason,
        ?string $notes = null
    ): array {
        // Validate the request first
        $validation = $this->validateOverrideRequest($user, $justification, $bottles);
        if (! $validation['valid']) {
            throw new InvalidArgumentException(
                'Override validation failed: '.implode(' ', $validation['errors'])
            );
        }

        $consumedCount = 0;
        $exceptions = new Collection;
        $movements = new Collection;
        $errors = [];

        // Build notes string with override prefix
        $fullNotes = "[COMMITTED OVERRIDE] {$reason->label()}";
        if ($notes !== null && $notes !== '') {
            $fullNotes .= ": {$notes}";
        }

        try {
            DB::transaction(function () use (
                $user,
                $justification,
                $bottles,
                $reason,
                $fullNotes,
                &$consumedCount,
                &$exceptions,
                &$movements,
                &$errors
            ): void {
                foreach ($bottles as $bottle) {
                    try {
                        // Create InventoryException record FIRST (for audit trail)
                        $exception = $this->createOverrideException(
                            $bottle,
                            $user,
                            $justification,
                            $reason
                        );
                        $exceptions->push($exception);

                        // Now consume the bottle using MovementService
                        $movement = $this->movementService->recordConsumption(
                            $bottle,
                            $reason,
                            $user,
                            "{$fullNotes} | Exception ID: {$exception->id}"
                        );
                        $movements->push($movement);

                        $consumedCount++;
                    } catch (Exception $e) {
                        $errors[] = "Bottle {$bottle->serial_number}: {$e->getMessage()}";
                    }
                }

                // If no bottles were consumed, throw exception to rollback
                if ($consumedCount === 0) {
                    throw new Exception('No bottles were consumed successfully.');
                }
            });
        } catch (Exception $e) {
            return [
                'success' => false,
                'consumed_count' => 0,
                'exceptions' => new Collection,
                'movements' => new Collection,
                'errors' => [$e->getMessage()],
            ];
        }

        return [
            'success' => $consumedCount > 0,
            'consumed_count' => $consumedCount,
            'exceptions' => $exceptions,
            'movements' => $movements,
            'errors' => $errors,
        ];
    }

    /**
     * Create an InventoryException record for the override.
     *
     * This record flags the consumption for finance & ops review.
     *
     * @param  SerializedBottle  $bottle  The bottle being consumed
     * @param  User  $user  The user performing the override
     * @param  string  $justification  The justification text
     * @param  ConsumptionReason  $reason  The consumption reason
     */
    protected function createOverrideException(
        SerializedBottle $bottle,
        User $user,
        string $justification,
        ConsumptionReason $reason
    ): InventoryException {
        return InventoryException::create([
            'exception_type' => self::EXCEPTION_TYPE,
            'serialized_bottle_id' => $bottle->id,
            'reason' => $this->buildExceptionReason($bottle, $justification, $reason),
            'created_by' => $user->id,
            // These remain null - the exception is pending finance & ops review
            'resolution' => null,
            'resolved_at' => null,
            'resolved_by' => null,
        ]);
    }

    /**
     * Build the exception reason text with full context.
     *
     * @param  SerializedBottle  $bottle  The bottle being consumed
     * @param  string  $justification  The user's justification
     * @param  ConsumptionReason  $reason  The consumption reason
     */
    protected function buildExceptionReason(
        SerializedBottle $bottle,
        string $justification,
        ConsumptionReason $reason
    ): string {
        $allocation = $bottle->allocation;
        $allocationRef = $allocation ? substr($allocation->id, 0, 8).'...' : 'N/A';

        $freeQty = $allocation ? $this->inventoryService->getFreeQuantity($allocation) : 'N/A';
        $committedQty = $allocation ? $this->inventoryService->getCommittedQuantity($allocation) : 'N/A';

        return "COMMITTED INVENTORY CONSUMPTION OVERRIDE\n\n"
            ."Bottle: {$bottle->serial_number}\n"
            ."Allocation: {$allocationRef}\n"
            ."Free Quantity at time of override: {$freeQty}\n"
            ."Committed Quantity at time of override: {$committedQty}\n"
            ."Consumption Reason: {$reason->label()}\n\n"
            ."OPERATOR JUSTIFICATION:\n{$justification}\n\n"
            .'This exception requires review by Finance & Operations.';
    }

    /**
     * Get pending override exceptions for review.
     *
     * @return Collection<int, InventoryException>
     */
    public function getPendingOverrideExceptions(): Collection
    {
        return InventoryException::where('exception_type', self::EXCEPTION_TYPE)
            ->whereNull('resolved_at')
            ->with(['serializedBottle', 'creator'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get the count of pending override exceptions.
     */
    public function getPendingOverrideExceptionCount(): int
    {
        return InventoryException::where('exception_type', self::EXCEPTION_TYPE)
            ->whereNull('resolved_at')
            ->count();
    }

    /**
     * Resolve an override exception after finance & ops review.
     *
     * @param  InventoryException  $exception  The exception to resolve
     * @param  User  $resolver  The user resolving the exception
     * @param  string  $resolution  The resolution notes
     */
    public function resolveException(
        InventoryException $exception,
        User $resolver,
        string $resolution
    ): InventoryException {
        $exception->update([
            'resolution' => $resolution,
            'resolved_at' => now(),
            'resolved_by' => $resolver->id,
        ]);

        return $exception->fresh();
    }
}
