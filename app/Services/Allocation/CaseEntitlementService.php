<?php

namespace App\Services\Allocation;

use App\Enums\Allocation\CaseEntitlementStatus;
use App\Models\Allocation\CaseEntitlement;
use App\Models\Allocation\Voucher;
use App\Models\AuditLog;
use App\Models\Customer\Customer;
use App\Models\Pim\SellableSku;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Service for managing CaseEntitlement lifecycle and operations.
 *
 * Centralizes all case entitlement business logic including creation,
 * integrity checking, and breaking operations.
 */
class CaseEntitlementService
{
    /**
     * Break reasons for case entitlements.
     */
    public const REASON_TRANSFER = 'transfer';

    public const REASON_TRADE = 'trade';

    public const REASON_PARTIAL_REDEMPTION = 'partial_redemption';

    /**
     * Create a case entitlement from existing vouchers.
     *
     * Associates the provided vouchers with a new case entitlement.
     * All vouchers must belong to the same customer.
     *
     * @param  array<Voucher>|Collection<int, Voucher>  $vouchers  The vouchers to group into a case
     * @param  Customer  $customer  The customer who owns the vouchers
     * @param  SellableSku  $sellableSku  The sellable SKU (case) that was sold
     * @return CaseEntitlement The created case entitlement
     *
     * @throws \InvalidArgumentException If vouchers are invalid
     */
    public function createFromVouchers(
        array|Collection $vouchers,
        Customer $customer,
        SellableSku $sellableSku
    ): CaseEntitlement {
        $voucherCollection = $vouchers instanceof Collection ? $vouchers : collect($vouchers);

        if ($voucherCollection->isEmpty()) {
            throw new \InvalidArgumentException(
                'Cannot create case entitlement: no vouchers provided.'
            );
        }

        // Validate all vouchers belong to the same customer
        foreach ($voucherCollection as $voucher) {
            if ($voucher->customer_id !== $customer->id) {
                throw new \InvalidArgumentException(
                    "Cannot create case entitlement: voucher {$voucher->id} does not belong to customer {$customer->id}."
                );
            }

            if ($voucher->isPartOfCase()) {
                throw new \InvalidArgumentException(
                    "Cannot create case entitlement: voucher {$voucher->id} is already part of another case entitlement."
                );
            }

            if ($voucher->isTerminal()) {
                throw new \InvalidArgumentException(
                    "Cannot create case entitlement: voucher {$voucher->id} is in terminal state '{$voucher->lifecycle_state->label()}'."
                );
            }
        }

        return DB::transaction(function () use ($voucherCollection, $customer, $sellableSku): CaseEntitlement {
            // Create the case entitlement
            $caseEntitlement = CaseEntitlement::create([
                'customer_id' => $customer->id,
                'sellable_sku_id' => $sellableSku->id,
                'status' => CaseEntitlementStatus::Intact,
            ]);

            // Associate vouchers with the case entitlement
            foreach ($voucherCollection as $voucher) {
                $voucher->case_entitlement_id = $caseEntitlement->id;
                $voucher->save();
            }

            // Log the creation
            $this->logCaseEvent(
                $caseEntitlement,
                AuditLog::EVENT_CREATED,
                [],
                [
                    'customer_id' => $customer->id,
                    'sellable_sku_id' => $sellableSku->id,
                    'voucher_count' => $voucherCollection->count(),
                    'voucher_ids' => $voucherCollection->pluck('id')->toArray(),
                ]
            );

            return $caseEntitlement;
        });
    }

    /**
     * Break a case entitlement.
     *
     * This is an irreversible operation. The vouchers remain valid
     * but behave as loose bottles.
     *
     * @param  CaseEntitlement  $caseEntitlement  The case entitlement to break
     * @param  string  $reason  The reason for breaking (transfer, trade, partial_redemption)
     * @return CaseEntitlement The updated case entitlement
     *
     * @throws \InvalidArgumentException If case cannot be broken
     */
    public function breakEntitlement(CaseEntitlement $caseEntitlement, string $reason): CaseEntitlement
    {
        if (! $caseEntitlement->canBeBroken()) {
            throw new \InvalidArgumentException(
                'Cannot break case entitlement: case is already broken.'
            );
        }

        $validReasons = [self::REASON_TRANSFER, self::REASON_TRADE, self::REASON_PARTIAL_REDEMPTION];
        if (! in_array($reason, $validReasons, true)) {
            throw new \InvalidArgumentException(
                "Cannot break case entitlement: invalid reason '{$reason}'. Valid reasons are: "
                .implode(', ', $validReasons)
            );
        }

        $oldStatus = $caseEntitlement->status;

        $caseEntitlement->status = CaseEntitlementStatus::Broken;
        $caseEntitlement->broken_at = now();
        $caseEntitlement->broken_reason = $reason;
        $caseEntitlement->save();

        $this->logCaseEvent(
            $caseEntitlement,
            AuditLog::EVENT_STATUS_CHANGE,
            [
                'status' => $oldStatus->value,
                'status_label' => $oldStatus->label(),
            ],
            [
                'status' => CaseEntitlementStatus::Broken->value,
                'status_label' => CaseEntitlementStatus::Broken->label(),
                'broken_reason' => $reason,
            ]
        );

        return $caseEntitlement;
    }

    /**
     * Check if a case entitlement is intact.
     *
     * Verifies that:
     * - The case status is still intact
     * - All vouchers are still held by the same customer
     * - No vouchers have been redeemed
     *
     * This does NOT modify the case status. Use this for validation
     * before operations that depend on case integrity.
     *
     * @param  CaseEntitlement  $caseEntitlement  The case entitlement to check
     * @return bool True if the case is intact, false otherwise
     */
    public function isIntact(CaseEntitlement $caseEntitlement): bool
    {
        // First check the status
        if (! $caseEntitlement->isIntact()) {
            return false;
        }

        // Then verify integrity by checking voucher states
        return $caseEntitlement->checkIntegrity();
    }

    /**
     * Automatically break a case entitlement if a voucher operation occurs.
     *
     * This method should be called by other services (VoucherService, VoucherTransferService)
     * when a voucher operation would break the case integrity.
     *
     * @param  Voucher  $voucher  The voucher being operated on
     * @param  string  $reason  The reason for breaking
     * @return CaseEntitlement|null The broken case entitlement, or null if voucher is not part of a case
     */
    public function breakIfVoucherInCase(Voucher $voucher, string $reason): ?CaseEntitlement
    {
        if (! $voucher->isPartOfCase()) {
            return null;
        }

        $caseEntitlement = $voucher->caseEntitlement;

        if ($caseEntitlement === null || ! $caseEntitlement->canBeBroken()) {
            return null;
        }

        return $this->breakEntitlement($caseEntitlement, $reason);
    }

    /**
     * Get the count of intact cases for a customer.
     */
    public function getIntactCaseCountForCustomer(Customer $customer): int
    {
        return CaseEntitlement::where('customer_id', $customer->id)
            ->where('status', CaseEntitlementStatus::Intact)
            ->count();
    }

    /**
     * Get all case entitlements for a customer.
     *
     * @return Collection<int, CaseEntitlement>
     */
    public function getCaseEntitlementsForCustomer(Customer $customer): Collection
    {
        return CaseEntitlement::where('customer_id', $customer->id)
            ->with(['sellableSku', 'vouchers'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Log a case entitlement event to the audit log.
     *
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     */
    protected function logCaseEvent(
        CaseEntitlement $caseEntitlement,
        string $event,
        array $oldValues,
        array $newValues
    ): void {
        $caseEntitlement->auditLogs()->create([
            'event' => $event,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'user_id' => Auth::id(),
        ]);
    }
}
