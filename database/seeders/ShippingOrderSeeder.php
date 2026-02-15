<?php

namespace Database\Seeders;

use App\Enums\Customer\CustomerStatus;
use App\Enums\Fulfillment\ShippingOrderStatus;
use App\Enums\Inventory\BottleState;
use App\Enums\Inventory\LocationStatus;
use App\Enums\Inventory\LocationType;
use App\Models\Allocation\Voucher;
use App\Models\Customer\Customer;
use App\Models\Inventory\Location;
use App\Models\Inventory\SerializedBottle;
use App\Models\User;
use App\Services\Fulfillment\LateBindingService;
use App\Services\Fulfillment\ShipmentService;
use App\Services\Fulfillment\ShippingOrderService;
use Illuminate\Database\Seeder;

/**
 * ShippingOrderSeeder - Creates shipping orders via full service lifecycle.
 *
 * Lifecycle: create(Draft) → transitionTo(Planned) → transitionTo(Picking) →
 *            bindVoucherToBottle() → createFromOrder(Preparing) → confirmShipment(Shipped) →
 *            markDelivered(Delivered)
 *
 * confirmShipment() triggers: voucher redemption, ownership transfer, case breaking.
 * This seeder replaces both ShippingOrderSeeder and ShippingOrderLineSeeder.
 * ShipmentSeeder now only handles delivery progression for already-created shipments.
 */
class ShippingOrderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $shippingOrderService = app(ShippingOrderService::class);
        $lateBindingService = app(LateBindingService::class);
        $shipmentService = app(ShipmentService::class);

        $customers = Customer::where('status', CustomerStatus::Active)->get();

        if ($customers->isEmpty()) {
            $this->command->warn('No active customers found. Run CustomerSeeder first.');

            return;
        }

        $warehouses = Location::whereIn('location_type', [LocationType::MainWarehouse, LocationType::SatelliteWarehouse])
            ->where('status', LocationStatus::Active)
            ->get();

        if ($warehouses->isEmpty()) {
            $this->command->warn('No warehouse locations found. Run LocationSeeder first.');

            return;
        }

        $admin = User::first();

        // Get vouchers in Issued state (eligible for fulfillment)
        $issuedVouchers = Voucher::where('lifecycle_state', 'issued')
            ->with(['allocation', 'customer'])
            ->get()
            ->groupBy('customer_id');

        if ($issuedVouchers->isEmpty()) {
            $this->command->warn('No issued vouchers found. Run VoucherSeeder first.');

            return;
        }

        // Get stored bottles for late binding, grouped by allocation_id
        $storedBottles = SerializedBottle::where('state', BottleState::Stored)
            ->get()
            ->groupBy('allocation_id');

        $shippingMethods = ['express', 'standard', 'economy', 'premium'];
        $totalCreated = 0;

        foreach ($customers->take(8) as $customer) {
            $customerVouchers = $issuedVouchers->get($customer->id);
            if (! $customerVouchers || $customerVouchers->isEmpty()) {
                continue;
            }

            $numOrders = fake()->numberBetween(1, min(3, (int) ceil($customerVouchers->count() / 2)));

            for ($i = 0; $i < $numOrders; $i++) {
                $voucherCount = fake()->numberBetween(1, min(4, $customerVouchers->count()));
                $orderVouchers = $customerVouchers->take($voucherCount);
                $customerVouchers = $customerVouchers->skip($voucherCount);

                if ($orderVouchers->isEmpty()) {
                    break;
                }

                try {
                    // 1. Create SO via service (Draft + lines)
                    $so = $shippingOrderService->create(
                        $customer,
                        $orderVouchers,
                        null,
                        fake()->randomElement($shippingMethods)
                    );

                    // Set warehouse and extra fields
                    $warehouse = $warehouses->random();
                    $so->update([
                        'source_warehouse_id' => $warehouse->id,
                        'carrier' => fake()->randomElement(['DHL Express', 'UPS', 'FedEx', 'TNT', 'SEUR']),
                        'incoterms' => fake()->randomElement(['DAP', 'DDP', 'EXW', 'CIF']),
                        'requested_ship_date' => fake()->dateTimeBetween('-1 week', '+2 weeks'),
                        'special_instructions' => fake()->boolean(30)
                            ? fake()->randomElement([
                                'Please call before delivery',
                                'Leave at reception',
                                'Signature required',
                                'Fragile - handle with care',
                                'Deliver to side entrance',
                            ])
                            : null,
                        'created_by' => $admin?->id,
                    ]);

                    $targetStatus = $this->pickTargetStatus();

                    $this->progressToStatus(
                        $shippingOrderService,
                        $lateBindingService,
                        $shipmentService,
                        $so,
                        $targetStatus,
                        $storedBottles,
                        $admin
                    );

                    $totalCreated++;
                } catch (\Throwable $e) {
                    $this->command->warn("Shipping order failed for customer {$customer->id}: {$e->getMessage()}");
                }
            }
        }

        $this->command->info("Created {$totalCreated} shipping orders via full service lifecycle.");
    }

    /**
     * Pick a target status with realistic distribution.
     */
    private function pickTargetStatus(): string
    {
        $random = fake()->numberBetween(1, 100);

        return match (true) {
            $random <= 10 => 'draft',
            $random <= 25 => 'planned',
            $random <= 35 => 'picking',
            $random <= 50 => 'shipped',     // confirmShipment handles this
            $random <= 85 => 'completed',   // confirmShipment + markDelivered
            $random <= 95 => 'cancelled',
            default => 'on_hold',
        };
    }

    /**
     * Progress a shipping order through its lifecycle.
     *
     * Lifecycle: Draft → Planned → Picking → [bind] → createFromOrder → confirmShipment → markDelivered
     */
    private function progressToStatus(
        ShippingOrderService $soService,
        LateBindingService $lateBindingService,
        ShipmentService $shipmentService,
        $so,
        string $targetStatus,
        &$storedBottles,
        ?User $admin
    ): void {
        if ($targetStatus === 'draft') {
            return;
        }

        if ($targetStatus === 'cancelled') {
            try {
                $soService->cancel($so, fake()->randomElement([
                    'Customer requested cancellation',
                    'Order placed in error',
                    'Vouchers no longer valid',
                ]));
            } catch (\Throwable $e) {
                $this->command->warn("Cancel failed for SO {$so->id}: {$e->getMessage()}");
            }

            return;
        }

        // → Planned (locks vouchers)
        try {
            $soService->transitionTo($so, ShippingOrderStatus::Planned);
            $so->refresh();

            if ($admin) {
                $so->update([
                    'approved_by' => $admin->id,
                    'approved_at' => now()->subDays(fake()->numberBetween(1, 7)),
                ]);
            }
        } catch (\Throwable $e) {
            $this->command->warn("Planned transition failed for SO {$so->id}: {$e->getMessage()}");

            return;
        }

        if ($targetStatus === 'planned') {
            return;
        }

        if ($targetStatus === 'on_hold') {
            try {
                $soService->transitionTo($so, ShippingOrderStatus::OnHold);
            } catch (\Throwable $e) {
                $this->command->warn("OnHold transition failed for SO {$so->id}: {$e->getMessage()}");
            }

            return;
        }

        // → Picking (validates lines)
        try {
            $soService->transitionTo($so, ShippingOrderStatus::Picking);
            $so->refresh();
        } catch (\Throwable $e) {
            $this->command->warn("Picking transition failed for SO {$so->id}: {$e->getMessage()}");

            return;
        }

        // Perform late binding for each line
        $so->load('lines');
        $allBound = true;
        foreach ($so->lines as $line) {
            $bottle = $this->findMatchingBottle($line, $storedBottles);
            if ($bottle) {
                try {
                    $lateBindingService->bindVoucherToBottle($line, $bottle->serial_number);

                    // Remove bottle from available pool
                    $allocId = $bottle->allocation_id;
                    if (isset($storedBottles[$allocId])) {
                        $storedBottles[$allocId] = $storedBottles[$allocId]->reject(fn ($b) => $b->id === $bottle->id);
                    }
                } catch (\Throwable $e) {
                    $allBound = false;
                }
            } else {
                $allBound = false;
            }
        }

        if ($targetStatus === 'picking') {
            return;
        }

        // For shipped/completed we need all lines bound and a shipment
        if (! $allBound) {
            return; // Leave in Picking — realistic for unbound orders
        }

        // → Create Shipment (Preparing) via ShipmentService
        try {
            $so->refresh();
            $shipment = $shipmentService->createFromOrder($so);

            // → confirmShipment (Shipped) — redeems vouchers, transfers ownership, updates SO to Shipped
            $trackingNumber = fake()->randomElement(['DHL', '1Z', 'FX', 'GD', 'SR'])
                .fake()->numerify('##########');

            $shipmentService->confirmShipment($shipment, $trackingNumber, true);
            $shipment->refresh();
        } catch (\Throwable $e) {
            $this->command->warn("Shipment creation/confirmation failed for SO {$so->id}: {$e->getMessage()}");

            return;
        }

        if ($targetStatus === 'shipped') {
            return;
        }

        // → markDelivered (Completed)
        if ($targetStatus === 'completed') {
            try {
                $shipmentService->markDelivered($shipment);
            } catch (\Throwable $e) {
                $this->command->warn("Mark delivered failed for SO {$so->id}: {$e->getMessage()}");
            }
        }
    }

    /**
     * Find a matching stored bottle for a shipping order line.
     */
    private function findMatchingBottle($line, $storedBottles): ?SerializedBottle
    {
        $allocId = $line->allocation_id;
        if (! $allocId || ! isset($storedBottles[$allocId])) {
            return null;
        }

        return $storedBottles[$allocId]->where('state', BottleState::Stored)->first();
    }
}
