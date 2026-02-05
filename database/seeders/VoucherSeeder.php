<?php

namespace Database\Seeders;

use App\Enums\Allocation\AllocationStatus;
use App\Enums\Allocation\VoucherLifecycleState;
use App\Models\Allocation\Allocation;
use App\Models\Allocation\Voucher;
use App\Models\Customer\Customer;
use App\Models\Pim\SellableSku;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * VoucherSeeder - Creates comprehensive customer vouchers from allocations
 *
 * Vouchers are entitlements to bottles, created when allocations are sold.
 *
 * Lifecycle states:
 * - Issued: Available to the customer, can be traded/gifted/redeemed
 * - Locked: In fulfillment process, cannot be modified
 * - Redeemed: Used for delivery, consumed
 * - Cancelled: Invalidated (refund, error correction)
 *
 * Flags:
 * - tradable: Can be sold on secondary market
 * - giftable: Can be gifted to another customer
 * - suspended: Temporarily frozen (trading in progress, compliance hold)
 * - requires_attention: Flagged for manual review
 */
class VoucherSeeder extends Seeder
{
    private int $voucherCounter = 0;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get active customers
        $customers = Customer::where('status', Customer::STATUS_ACTIVE)->get();

        if ($customers->isEmpty()) {
            $this->command->warn('No active customers found. Run CustomerSeeder first.');

            return;
        }

        // Get allocations with sold quantities (meaning vouchers should exist)
        $allocations = Allocation::with(['wineVariant.wineMaster', 'format'])
            ->whereIn('status', [AllocationStatus::Active, AllocationStatus::Exhausted, AllocationStatus::Closed])
            ->where('sold_quantity', '>', 0)
            ->get();

        if ($allocations->isEmpty()) {
            $this->command->warn('No allocations with sales found. Run AllocationSeeder first.');

            return;
        }

        // Get sellable SKUs for linking
        $sellableSkus = SellableSku::with(['wineVariant', 'format'])->get();

        $totalCreated = 0;
        $customerIndex = 0;
        $customerCount = $customers->count();

        foreach ($allocations as $allocation) {
            // Create vouchers for the sold quantity
            $vouchersToCreate = $allocation->sold_quantity;

            // Find matching sellable SKU if it exists
            $matchingSku = $sellableSkus->first(function ($sku) use ($allocation) {
                return $sku->wine_variant_id === $allocation->wine_variant_id
                    && $sku->format_id === $allocation->format_id;
            });

            // Create vouchers distributed among customers
            for ($voucherIndex = 0; $voucherIndex < $vouchersToCreate; $voucherIndex++) {
                // Pick a customer (rotate through customers)
                $customer = $customers[$customerIndex % $customerCount];

                // Generate unique sale reference for each voucher
                $saleReference = $this->generateUniqueSaleReference();
                // Use Carbon with UTC to avoid DST timezone issues
                $saleDate = now()->subDays(fake()->numberBetween(7, 365))->startOfDay()->addHours(12);

                // Determine lifecycle state based on probability
                // Distribution:
                // 50% Issued (available)
                // 15% Locked (in fulfillment)
                // 25% Redeemed (delivered)
                // 10% Cancelled
                $stateRandom = fake()->numberBetween(1, 100);
                if ($stateRandom <= 50) {
                    $state = VoucherLifecycleState::Issued;
                    $tradable = fake()->boolean(70);
                    $giftable = fake()->boolean(60);
                    $suspended = false;
                    $requiresAttention = false;

                    // 15% of issued vouchers are suspended for trading
                    if (fake()->boolean(15)) {
                        $suspended = true;
                    }

                    // 5% require attention (compliance review, data issues)
                    if (fake()->boolean(5)) {
                        $requiresAttention = true;
                    }
                } elseif ($stateRandom <= 65) {
                    $state = VoucherLifecycleState::Locked;
                    $tradable = false;
                    $giftable = false;
                    $suspended = false;
                    $requiresAttention = fake()->boolean(10); // Some locked vouchers need attention (picking issues)
                } elseif ($stateRandom <= 90) {
                    $state = VoucherLifecycleState::Redeemed;
                    $tradable = false;
                    $giftable = false;
                    $suspended = false;
                    $requiresAttention = false;
                } else {
                    $state = VoucherLifecycleState::Cancelled;
                    $tradable = false;
                    $giftable = false;
                    $suspended = false;
                    $requiresAttention = false;
                }

                // External trading reference for suspended/traded vouchers
                $externalTradingRef = null;
                if ($state === VoucherLifecycleState::Issued && $suspended) {
                    $externalTradingRef = 'TRADE-'.fake()->regexify('[A-Z0-9]{8}');
                }

                // Attention reason for flagged vouchers
                $attentionReason = null;
                if ($requiresAttention) {
                    $attentionReason = fake()->randomElement([
                        'Compliance verification required',
                        'Customer address needs verification',
                        'Potential duplicate voucher',
                        'Customs documentation incomplete',
                        'Payment dispute under review',
                        'Allocation source verification pending',
                    ]);
                }

                Voucher::create([
                    'customer_id' => $customer->id,
                    'allocation_id' => $allocation->id,
                    'wine_variant_id' => $allocation->wine_variant_id,
                    'format_id' => $allocation->format_id,
                    'sellable_sku_id' => $matchingSku?->id,
                    'case_entitlement_id' => null,
                    'quantity' => 1, // Always 1 - one voucher = one bottle
                    'lifecycle_state' => $state,
                    'tradable' => $tradable,
                    'giftable' => $giftable,
                    'suspended' => $suspended,
                    'requires_attention' => $requiresAttention,
                    'attention_reason' => $attentionReason,
                    'external_trading_reference' => $externalTradingRef,
                    'sale_reference' => $saleReference,
                    'created_at' => $saleDate,
                ]);

                $totalCreated++;

                // Rotate to next customer every 2-4 vouchers
                if ($voucherIndex % fake()->numberBetween(2, 4) === 0) {
                    $customerIndex++;
                }
            }
        }

        // Create some additional vouchers with specific scenarios
        $this->createSpecialScenarioVouchers($customers, $allocations, $sellableSkus, $totalCreated);

        $this->command->info("Created {$totalCreated} vouchers.");
    }

    /**
     * Generate a unique sale reference.
     */
    private function generateUniqueSaleReference(): string
    {
        $this->voucherCounter++;

        return 'SALE-'.date('Y').'-'.str_pad((string) $this->voucherCounter, 6, '0', STR_PAD_LEFT).'-'.Str::random(4);
    }

    /**
     * Create vouchers for special scenarios (edge cases, specific states).
     */
    private function createSpecialScenarioVouchers($customers, $allocations, $sellableSkus, &$totalCreated): void
    {
        // Get allocations that still have remaining quantity
        $activeAllocations = $allocations->filter(function ($allocation) {
            return $allocation->status === AllocationStatus::Active
                && $allocation->sold_quantity < $allocation->total_quantity;
        });

        if ($activeAllocations->isEmpty()) {
            return;
        }

        // 1. Create some vouchers in "trading suspended" state (active secondary market)
        $tradingCustomer = $customers->random();
        $tradingAllocation = $activeAllocations->random();

        for ($i = 0; $i < 3; $i++) {
            $matchingSku = $sellableSkus->first(fn ($sku) => $sku->wine_variant_id === $tradingAllocation->wine_variant_id
                    && $sku->format_id === $tradingAllocation->format_id);

            Voucher::create([
                'customer_id' => $tradingCustomer->id,
                'allocation_id' => $tradingAllocation->id,
                'wine_variant_id' => $tradingAllocation->wine_variant_id,
                'format_id' => $tradingAllocation->format_id,
                'sellable_sku_id' => $matchingSku?->id,
                'quantity' => 1,
                'lifecycle_state' => VoucherLifecycleState::Issued,
                'tradable' => true,
                'giftable' => false,
                'suspended' => true,
                'requires_attention' => false,
                'external_trading_reference' => 'LIVEX-'.fake()->regexify('[A-Z0-9]{10}'),
                'sale_reference' => $this->generateUniqueSaleReference(),
            ]);
            $totalCreated++;
        }

        // 2. Create vouchers requiring compliance attention
        $complianceCustomer = $customers->random();
        $complianceAllocation = $activeAllocations->random();

        for ($i = 0; $i < 2; $i++) {
            $matchingSku = $sellableSkus->first(fn ($sku) => $sku->wine_variant_id === $complianceAllocation->wine_variant_id
                    && $sku->format_id === $complianceAllocation->format_id);

            Voucher::create([
                'customer_id' => $complianceCustomer->id,
                'allocation_id' => $complianceAllocation->id,
                'wine_variant_id' => $complianceAllocation->wine_variant_id,
                'format_id' => $complianceAllocation->format_id,
                'sellable_sku_id' => $matchingSku?->id,
                'quantity' => 1,
                'lifecycle_state' => VoucherLifecycleState::Issued,
                'tradable' => false,
                'giftable' => false,
                'suspended' => true,
                'requires_attention' => true,
                'attention_reason' => fake()->randomElement([
                    'AML/KYC verification required for high-value wine',
                    'Export documentation pending',
                    'Destination country import restrictions',
                ]),
                'sale_reference' => $this->generateUniqueSaleReference(),
            ]);
            $totalCreated++;
        }

        // 3. Create locked vouchers (in fulfillment process)
        $fulfillmentCustomer = $customers->random();
        $fulfillmentAllocation = $activeAllocations->random();

        for ($i = 0; $i < 6; $i++) {
            $matchingSku = $sellableSkus->first(fn ($sku) => $sku->wine_variant_id === $fulfillmentAllocation->wine_variant_id
                    && $sku->format_id === $fulfillmentAllocation->format_id);

            Voucher::create([
                'customer_id' => $fulfillmentCustomer->id,
                'allocation_id' => $fulfillmentAllocation->id,
                'wine_variant_id' => $fulfillmentAllocation->wine_variant_id,
                'format_id' => $fulfillmentAllocation->format_id,
                'sellable_sku_id' => $matchingSku?->id,
                'quantity' => 1,
                'lifecycle_state' => VoucherLifecycleState::Locked,
                'tradable' => false,
                'giftable' => false,
                'suspended' => false,
                'requires_attention' => $i === 0, // First one has picking issue
                'attention_reason' => $i === 0 ? 'Bottle damaged during picking, replacement needed' : null,
                'sale_reference' => $this->generateUniqueSaleReference(),
            ]);
            $totalCreated++;
        }

        // 4. Create recently cancelled vouchers (refunds)
        $refundCustomer = $customers->random();
        $refundAllocation = $activeAllocations->random();

        for ($i = 0; $i < 2; $i++) {
            $matchingSku = $sellableSkus->first(fn ($sku) => $sku->wine_variant_id === $refundAllocation->wine_variant_id
                    && $sku->format_id === $refundAllocation->format_id);

            Voucher::create([
                'customer_id' => $refundCustomer->id,
                'allocation_id' => $refundAllocation->id,
                'wine_variant_id' => $refundAllocation->wine_variant_id,
                'format_id' => $refundAllocation->format_id,
                'sellable_sku_id' => $matchingSku?->id,
                'quantity' => 1,
                'lifecycle_state' => VoucherLifecycleState::Cancelled,
                'tradable' => false,
                'giftable' => false,
                'suspended' => false,
                'requires_attention' => false,
                'sale_reference' => $this->generateUniqueSaleReference(),
            ]);
            $totalCreated++;
        }
    }
}
