<?php

namespace Database\Seeders;

use App\Enums\Allocation\AllocationStatus;
use App\Enums\Allocation\VoucherLifecycleState;
use App\Models\Allocation\Allocation;
use App\Models\Allocation\Voucher;
use App\Models\Customer\Customer;
use Illuminate\Database\Seeder;

/**
 * VoucherSeeder - Creates customer vouchers from allocations
 */
class VoucherSeeder extends Seeder
{
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
            ->whereIn('status', [AllocationStatus::Active, AllocationStatus::Exhausted])
            ->where('sold_quantity', '>', 0)
            ->get();

        if ($allocations->isEmpty()) {
            $this->command->warn('No allocations with sales found. Run AllocationSeeder first.');

            return;
        }

        foreach ($allocations as $allocation) {
            // Create vouchers for the sold quantity
            $vouchersToCreate = $allocation->sold_quantity;

            // Distribute among customers
            $customerIndex = 0;
            $customerCount = $customers->count();

            for ($i = 0; $i < $vouchersToCreate; $i++) {
                $customer = $customers[$customerIndex % $customerCount];

                // Determine lifecycle state based on probability
                $stateRandom = fake()->numberBetween(1, 100);
                if ($stateRandom <= 60) {
                    // 60% issued (available)
                    $state = VoucherLifecycleState::Issued;
                    $tradable = fake()->boolean(70);
                    $giftable = fake()->boolean(50);
                    $suspended = false;
                } elseif ($stateRandom <= 75) {
                    // 15% locked (in fulfillment process)
                    $state = VoucherLifecycleState::Locked;
                    $tradable = false;
                    $giftable = false;
                    $suspended = false;
                } elseif ($stateRandom <= 95) {
                    // 20% redeemed
                    $state = VoucherLifecycleState::Redeemed;
                    $tradable = false;
                    $giftable = false;
                    $suspended = false;
                } else {
                    // 5% cancelled
                    $state = VoucherLifecycleState::Cancelled;
                    $tradable = false;
                    $giftable = false;
                    $suspended = false;
                }

                // Some issued vouchers might be suspended for trading
                $externalTradingRef = null;
                if ($state === VoucherLifecycleState::Issued && fake()->boolean(10)) {
                    $suspended = true;
                    $externalTradingRef = 'TRADE-'.fake()->regexify('[A-Z0-9]{8}');
                }

                // Check if voucher already exists
                $existingCount = Voucher::where('allocation_id', $allocation->id)
                    ->where('customer_id', $customer->id)
                    ->count();

                // Create a sale reference for this batch
                $saleReference = 'SALE-'.date('Ymd').'-'.fake()->regexify('[A-Z0-9]{6}');

                Voucher::create([
                    'customer_id' => $customer->id,
                    'allocation_id' => $allocation->id,
                    'wine_variant_id' => $allocation->wine_variant_id,
                    'format_id' => $allocation->format_id,
                    'sellable_sku_id' => null,
                    'case_entitlement_id' => null,
                    'quantity' => 1, // Always 1 - one voucher = one bottle
                    'lifecycle_state' => $state,
                    'tradable' => $tradable,
                    'giftable' => $giftable,
                    'suspended' => $suspended,
                    'requires_attention' => false,
                    'attention_reason' => null,
                    'external_trading_reference' => $externalTradingRef,
                    'sale_reference' => $saleReference,
                ]);

                // Rotate to next customer for variety
                if ($i % 3 === 0) {
                    $customerIndex++;
                }
            }
        }
    }
}
