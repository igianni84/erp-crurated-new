<?php

namespace Database\Seeders;

use App\Enums\Allocation\AllocationStatus;
use App\Enums\Customer\CustomerStatus;
use App\Models\Allocation\Allocation;
use App\Models\Customer\Customer;
use App\Models\Pim\SellableSku;
use App\Services\Allocation\VoucherService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * VoucherSeeder - Issues vouchers via VoucherService for business-logic compliance.
 *
 * Uses VoucherService::issueVouchers() which:
 * - Increments sold_quantity via AllocationService::consumeAllocation()
 * - Auto-transitions allocation to Exhausted when remaining=0
 * - Creates audit trail
 * - Dispatches VoucherIssued event → auto-creates ProcurementIntents
 *
 * Special scenarios (suspended, locked, cancelled) are created via service lifecycle methods.
 * Redeemed vouchers are NOT created here — redemption happens only at ShipmentSeeder (invariant #4).
 */
class VoucherSeeder extends Seeder
{
    private int $saleCounter = 0;

    public function run(): void
    {
        $voucherService = app(VoucherService::class);

        $customers = Customer::where('status', CustomerStatus::Active)->get();

        if ($customers->isEmpty()) {
            $this->command->warn('No active customers found. Run CustomerSeeder first.');

            return;
        }

        // Get Active allocations with remaining capacity
        $allocations = Allocation::with(['wineVariant.wineMaster', 'format'])
            ->where('status', AllocationStatus::Active)
            ->get();

        if ($allocations->isEmpty()) {
            $this->command->warn('No active allocations found. Run AllocationSeeder first.');

            return;
        }

        // Get sellable SKUs for linking
        $sellableSkus = SellableSku::with(['wineVariant', 'format'])->get();

        $totalIssued = 0;
        $customerIndex = 0;
        $customerCount = $customers->count();

        foreach ($allocations as $allocation) {
            // Determine how many vouchers to sell from this allocation
            // Sell 30-70% of total quantity, capped at 12 per allocation to keep seeding fast
            $sellPercentage = fake()->numberBetween(30, 70);
            $vouchersToIssue = min(12, max(1, (int) round($allocation->total_quantity * $sellPercentage / 100)));

            // Find matching sellable SKU
            $matchingSku = $sellableSkus->first(function ($sku) use ($allocation) {
                return $sku->wine_variant_id === $allocation->wine_variant_id
                    && $sku->format_id === $allocation->format_id;
            });

            // Issue vouchers one per sale_reference due to unique constraint
            // (allocation_id, customer_id, sale_reference)
            $remaining = $vouchersToIssue;
            while ($remaining > 0) {
                $customer = $customers[$customerIndex % $customerCount];
                $batchSize = min($remaining, fake()->numberBetween(1, 4));

                try {
                    for ($v = 0; $v < $batchSize; $v++) {
                        $saleReference = $this->generateSaleReference();
                        $vouchers = $voucherService->issueVouchers(
                            $allocation,
                            $customer,
                            $matchingSku,
                            $saleReference,
                            1
                        );
                        $totalIssued += $vouchers->count();
                    }
                } catch (\InvalidArgumentException $e) {
                    // Allocation exhausted or cannot be consumed — move to next
                    break;
                }

                $remaining -= $batchSize;
                $customerIndex++;

                // Prevent memory bloat from Eloquent model cache
                if ($totalIssued % 100 === 0) {
                    gc_collect_cycles();
                }
            }
        }

        // Create special scenario vouchers using service lifecycle methods
        $this->createSpecialScenarios($voucherService, $totalIssued);

        $this->command->info("Issued {$totalIssued} vouchers via VoucherService.");
    }

    /**
     * Create vouchers in special lifecycle states using proper service transitions.
     */
    private function createSpecialScenarios(VoucherService $voucherService, int &$totalIssued): void
    {
        // Get some recently issued vouchers for transition to special states
        $issuedVouchers = \App\Models\Allocation\Voucher::where('lifecycle_state', \App\Enums\Allocation\VoucherLifecycleState::Issued)
            ->where('suspended', false)
            ->inRandomOrder()
            ->take(20)
            ->get();

        if ($issuedVouchers->count() < 15) {
            $this->command->warn('Not enough issued vouchers for special scenarios.');

            return;
        }

        $index = 0;

        // 1. Trading suspended (3 vouchers)
        for ($i = 0; $i < 3; $i++) {
            $voucher = $issuedVouchers[$index++];
            $tradingRef = 'LIVEX-'.fake()->regexify('[A-Z0-9]{10}');
            try {
                $voucherService->suspendForTrading($voucher, $tradingRef);
            } catch (\Throwable $e) {
                $this->command->warn("Trading suspend failed for voucher {$voucher->id}: {$e->getMessage()}");
            }
        }

        // 2. Compliance suspended (2 vouchers)
        for ($i = 0; $i < 2; $i++) {
            $voucher = $issuedVouchers[$index++];
            try {
                $voucherService->suspend($voucher, 'Compliance verification required');
            } catch (\Throwable $e) {
                $this->command->warn("Suspend failed for voucher {$voucher->id}: {$e->getMessage()}");
            }
        }

        // 3. Locked for fulfillment (6 vouchers)
        for ($i = 0; $i < 6; $i++) {
            $voucher = $issuedVouchers[$index++];
            try {
                $voucherService->lockForFulfillment($voucher);
            } catch (\Throwable $e) {
                $this->command->warn("Lock failed for voucher {$voucher->id}: {$e->getMessage()}");
            }
        }

        // 4. Cancelled (2 vouchers)
        for ($i = 0; $i < 2; $i++) {
            $voucher = $issuedVouchers[$index++];
            try {
                $voucherService->cancel($voucher);
            } catch (\Throwable $e) {
                $this->command->warn("Cancel failed for voucher {$voucher->id}: {$e->getMessage()}");
            }
        }

        // NOTE: Redeemed vouchers are NOT created here.
        // Redemption happens ONLY during shipment confirmation (ShipmentSeeder, invariant #4).
    }

    private function generateSaleReference(): string
    {
        $this->saleCounter++;

        return 'SALE-'.date('Y').'-'.str_pad((string) $this->saleCounter, 6, '0', STR_PAD_LEFT).'-'.Str::random(4);
    }
}
