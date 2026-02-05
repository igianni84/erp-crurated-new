<?php

namespace Database\Seeders;

use App\Enums\Fulfillment\ShippingOrderLineStatus;
use App\Enums\Fulfillment\ShippingOrderStatus;
use App\Enums\Inventory\BottleState;
use App\Models\Allocation\Voucher;
use App\Models\Fulfillment\ShippingOrder;
use App\Models\Fulfillment\ShippingOrderLine;
use App\Models\Inventory\SerializedBottle;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * ShippingOrderLineSeeder - Creates shipping order lines linking vouchers to shipments
 *
 * Each shipping order line represents one voucher being fulfilled.
 * Late binding occurs here - vouchers are linked to serialized bottles during picking.
 */
class ShippingOrderLineSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get shipping orders that need lines
        $shippingOrders = ShippingOrder::whereIn('status', [
            ShippingOrderStatus::Planned,
            ShippingOrderStatus::Picking,
            ShippingOrderStatus::Shipped,
            ShippingOrderStatus::Completed,
        ])->with('customer')->get();

        if ($shippingOrders->isEmpty()) {
            $this->command->warn('No shipping orders found. Run ShippingOrderSeeder first.');

            return;
        }

        // Get vouchers that can be fulfilled
        $issuedVouchers = Voucher::where('lifecycle_state', 'issued')
            ->with(['allocation', 'wineVariant.wineMaster', 'format'])
            ->get();

        $lockedVouchers = Voucher::where('lifecycle_state', 'locked')
            ->with(['allocation', 'wineVariant.wineMaster', 'format'])
            ->get();

        $redeemedVouchers = Voucher::where('lifecycle_state', 'redeemed')
            ->with(['allocation', 'wineVariant.wineMaster', 'format'])
            ->get();

        // Get available serialized bottles for picking
        $availableBottles = SerializedBottle::whereIn('state', [
            BottleState::Stored,
            BottleState::ReservedForPicking,
        ])->with(['allocation', 'wineVariant', 'format'])->get();

        // Get admin user for binding confirmation
        $admin = User::first();

        foreach ($shippingOrders as $order) {
            // Determine number of lines based on status (1-6 lines per order)
            $lineCount = fake()->numberBetween(1, 6);

            $this->createOrderLines($order, $lineCount, $issuedVouchers, $lockedVouchers, $redeemedVouchers, $availableBottles, $admin);
        }
    }

    /**
     * Create shipping order lines for an order.
     */
    private function createOrderLines(
        ShippingOrder $order,
        int $lineCount,
        $issuedVouchers,
        $lockedVouchers,
        $redeemedVouchers,
        $availableBottles,
        $admin
    ): void {
        // Get vouchers for this customer
        $customerIssuedVouchers = $issuedVouchers->where('customer_id', $order->customer_id);
        $customerLockedVouchers = $lockedVouchers->where('customer_id', $order->customer_id);
        $customerRedeemedVouchers = $redeemedVouchers->where('customer_id', $order->customer_id);

        for ($i = 0; $i < $lineCount; $i++) {
            // Determine line status based on order status
            $lineStatus = $this->determineLineStatus($order->status);

            // Select appropriate voucher based on line status
            $voucher = $this->selectVoucher(
                $lineStatus,
                $customerIssuedVouchers,
                $customerLockedVouchers,
                $customerRedeemedVouchers,
                $order->customer_id
            );

            if (! $voucher) {
                continue;
            }

            // Try to find a matching serialized bottle for picked/shipped lines
            $serializedBottle = null;
            if (in_array($lineStatus, [
                ShippingOrderLineStatus::Picked,
                ShippingOrderLineStatus::Shipped,
            ])) {
                $serializedBottle = $this->findMatchingBottle($voucher, $availableBottles);
            }

            // Determine if this line has early binding (10% chance for planned orders)
            $hasEarlyBinding = $order->status === ShippingOrderStatus::Planned && fake()->boolean(10);

            // Create the shipping order line with correct model fields
            ShippingOrderLine::create([
                'shipping_order_id' => $order->id,
                'voucher_id' => $voucher->id,
                'allocation_id' => $voucher->allocation_id, // Required, immutable lineage
                'status' => $lineStatus,
                'bound_bottle_serial' => $serializedBottle?->serial_number,
                'bound_case_id' => $serializedBottle?->case_id,
                'early_binding_serial' => $hasEarlyBinding
                    ? 'EARLY-'.fake()->regexify('[A-Z0-9]{12}')
                    : null,
                'binding_confirmed_at' => $serializedBottle
                    ? now()->subDays(fake()->numberBetween(1, 7))
                    : null,
                'binding_confirmed_by' => $serializedBottle ? $admin?->id : null,
            ]);

            // Remove used voucher from available pool
            if ($voucher) {
                $customerIssuedVouchers = $customerIssuedVouchers->reject(fn ($v) => $v->id === $voucher->id);
                $customerLockedVouchers = $customerLockedVouchers->reject(fn ($v) => $v->id === $voucher->id);
                $customerRedeemedVouchers = $customerRedeemedVouchers->reject(fn ($v) => $v->id === $voucher->id);
            }

            // Remove used bottle from available pool
            if ($serializedBottle) {
                $availableBottles = $availableBottles->reject(fn ($b) => $b->id === $serializedBottle->id);
            }
        }
    }

    /**
     * Determine line status based on order status.
     */
    private function determineLineStatus(ShippingOrderStatus $orderStatus): ShippingOrderLineStatus
    {
        return match ($orderStatus) {
            ShippingOrderStatus::Planned => ShippingOrderLineStatus::Pending,
            ShippingOrderStatus::Picking => fake()->randomElement([
                ShippingOrderLineStatus::Pending,
                ShippingOrderLineStatus::Validated,
                ShippingOrderLineStatus::Picked,
            ]),
            ShippingOrderStatus::Shipped => ShippingOrderLineStatus::Shipped,
            ShippingOrderStatus::Completed => ShippingOrderLineStatus::Shipped,
            default => ShippingOrderLineStatus::Pending,
        };
    }

    /**
     * Select an appropriate voucher based on line status.
     */
    private function selectVoucher(
        ShippingOrderLineStatus $lineStatus,
        $issuedVouchers,
        $lockedVouchers,
        $redeemedVouchers,
        $customerId
    ): ?Voucher {
        // For shipped lines, use redeemed vouchers first, then locked
        if ($lineStatus === ShippingOrderLineStatus::Shipped) {
            if ($redeemedVouchers->isNotEmpty()) {
                return $redeemedVouchers->first();
            }
            if ($lockedVouchers->isNotEmpty()) {
                return $lockedVouchers->first();
            }
        }

        // For picked lines, use locked vouchers
        if ($lineStatus === ShippingOrderLineStatus::Picked) {
            if ($lockedVouchers->isNotEmpty()) {
                return $lockedVouchers->first();
            }
        }

        // For pending/validated lines, use issued vouchers
        if ($issuedVouchers->isNotEmpty()) {
            return $issuedVouchers->first();
        }

        // Fallback: find any voucher for this customer
        return Voucher::where('customer_id', $customerId)->inRandomOrder()->first();
    }

    /**
     * Find a matching serialized bottle for a voucher.
     */
    private function findMatchingBottle(Voucher $voucher, $availableBottles): ?SerializedBottle
    {
        // Find bottles matching the voucher's allocation, wine variant, and format
        $matchingBottles = $availableBottles->filter(function ($bottle) use ($voucher) {
            return $bottle->allocation_id === $voucher->allocation_id
                || ($bottle->wine_variant_id === $voucher->wine_variant_id
                    && $bottle->format_id === $voucher->format_id);
        });

        return $matchingBottles->first();
    }
}
