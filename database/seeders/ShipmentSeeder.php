<?php

namespace Database\Seeders;

use App\Enums\Fulfillment\ShipmentStatus;
use App\Models\Fulfillment\Shipment;
use App\Services\Fulfillment\ShipmentService;
use Illuminate\Database\Seeder;

/**
 * ShipmentSeeder - Applies delivery failures to existing shipments.
 *
 * Shipments are created by ShippingOrderSeeder via the full service lifecycle:
 * ShipmentService::createFromOrder() → confirmShipment() → markDelivered()
 *
 * This seeder only marks a small percentage of shipped (non-delivered) shipments
 * as Failed via ShipmentService::markFailed(), simulating delivery failures.
 */
class ShipmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $shipmentService = app(ShipmentService::class);

        // Get shipped (non-terminal) shipments for failure scenarios
        $shippedShipments = Shipment::where('status', ShipmentStatus::Shipped)
            ->get();

        if ($shippedShipments->isEmpty()) {
            $this->command->info('No shipped (non-delivered) shipments found. Nothing to do.');

            return;
        }

        $failureCount = 0;
        $failureReasons = [
            'Delivery failed: recipient not at address',
            'Package returned: damaged in transit',
            'Customs clearance failed',
            'Address not found - returned to warehouse',
            'Refused by recipient',
        ];

        // Mark ~10% of shipped shipments as failed
        foreach ($shippedShipments as $shipment) {
            if (! fake()->boolean(10)) {
                continue;
            }

            try {
                $shipmentService->markFailed(
                    $shipment,
                    fake()->randomElement($failureReasons)
                );
                $failureCount++;
            } catch (\Throwable $e) {
                $this->command->warn("Mark failed failed for shipment {$shipment->id}: {$e->getMessage()}");
            }
        }

        $this->command->info("Marked {$failureCount} shipments as failed via ShipmentService.");
    }
}
