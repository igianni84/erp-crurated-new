<?php

namespace Database\Seeders;

use App\Enums\Fulfillment\ShipmentStatus;
use App\Enums\Fulfillment\ShippingOrderStatus;
use App\Models\Fulfillment\Shipment;
use App\Models\Fulfillment\ShippingOrder;
use App\Models\Inventory\Location;
use App\Models\Inventory\SerializedBottle;
use Illuminate\Database\Seeder;

/**
 * ShipmentSeeder - Creates shipment records for shipping orders
 *
 * Shipments represent the physical shipping event - the point of no return
 * where goods leave the warehouse.
 *
 * Shipment statuses:
 * - Preparing: Being packed for shipment
 * - Shipped: Handed to carrier
 * - InTransit: On the way to destination
 * - Delivered: Successfully received by customer
 * - Failed: Delivery failed (returned, lost, damaged)
 */
class ShipmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get shipping orders that should have shipments (shipped or completed)
        $shippingOrders = ShippingOrder::whereIn('status', [
            ShippingOrderStatus::Shipped,
            ShippingOrderStatus::Completed,
            ShippingOrderStatus::Picking, // Some in picking might have partial shipments
        ])->with('customer')->get();

        if ($shippingOrders->isEmpty()) {
            $this->command->warn('No shipping orders found for shipments. Run ShippingOrderSeeder first.');

            return;
        }

        // Get warehouse locations
        $warehouses = Location::whereIn('location_type', ['main_warehouse', 'satellite_warehouse'])
            ->where('status', 'active')
            ->get();

        if ($warehouses->isEmpty()) {
            $this->command->warn('No warehouse locations found. Run LocationSeeder first.');

            return;
        }

        // Get some serialized bottles for shipment records
        $serializedBottles = SerializedBottle::where('state', 'shipped')
            ->orWhere('state', 'stored')
            ->pluck('serial_number')
            ->toArray();

        $carriers = [
            'DHL Express' => ['DHL', '1234567890'],
            'UPS' => ['1Z', 'Y12345678'],
            'FedEx' => ['FX', '123456789012'],
            'TNT' => ['GD', '123456789'],
            'SEUR' => ['SR', '1234567890123'],
        ];

        $totalCreated = 0;

        foreach ($shippingOrders as $order) {
            // Skip orders that are just in picking (only 20% have partial shipments)
            if ($order->status === ShippingOrderStatus::Picking && ! fake()->boolean(20)) {
                continue;
            }

            $warehouse = $order->source_warehouse_id
                ? Location::find($order->source_warehouse_id)
                : $warehouses->random();

            if (! $warehouse) {
                $warehouse = $warehouses->random();
            }

            // Determine shipment status based on order status
            if ($order->status === ShippingOrderStatus::Completed) {
                // Completed orders have delivered shipments
                $shipmentStatus = ShipmentStatus::Delivered;
            } elseif ($order->status === ShippingOrderStatus::Shipped) {
                // Shipped orders have various shipment statuses
                $statusRandom = fake()->numberBetween(1, 100);
                $shipmentStatus = match (true) {
                    $statusRandom <= 30 => ShipmentStatus::Shipped,
                    $statusRandom <= 70 => ShipmentStatus::InTransit,
                    $statusRandom <= 95 => ShipmentStatus::Delivered,
                    default => ShipmentStatus::Failed,
                };
            } else {
                // Picking orders might have preparing shipments
                $shipmentStatus = ShipmentStatus::Preparing;
            }

            // Select carrier
            $carrierName = fake()->randomElement(array_keys($carriers));
            $carrierInfo = $carriers[$carrierName];

            // Generate tracking number
            $trackingNumber = null;
            if ($shipmentStatus !== ShipmentStatus::Preparing) {
                $trackingNumber = $carrierInfo[0].fake()->numerify(str_repeat('#', strlen($carrierInfo[1])));
            }

            // Generate bottle serials for this shipment
            $bottleCount = fake()->numberBetween(1, 12);
            $shippedSerials = [];

            if (! empty($serializedBottles) && $shipmentStatus !== ShipmentStatus::Preparing) {
                // Use some real serials if available
                $availableSerials = array_slice($serializedBottles, 0, min($bottleCount, count($serializedBottles)));
                $shippedSerials = $availableSerials;

                // Fill remaining with generated serials
                while (count($shippedSerials) < $bottleCount) {
                    $shippedSerials[] = 'BTL-'.fake()->regexify('[A-Z0-9]{12}');
                }
            } else {
                // Generate all serials
                for ($i = 0; $i < $bottleCount; $i++) {
                    $shippedSerials[] = 'BTL-'.fake()->regexify('[A-Z0-9]{12}');
                }
            }

            // Determine timestamps based on status
            $shippedAt = null;
            $deliveredAt = null;

            if ($shipmentStatus === ShipmentStatus::Shipped || $shipmentStatus === ShipmentStatus::InTransit || $shipmentStatus === ShipmentStatus::Delivered) {
                $shippedAt = fake()->dateTimeBetween('-3 months', '-1 day');
            }

            if ($shipmentStatus === ShipmentStatus::Delivered) {
                $deliveredAt = fake()->dateTimeBetween($shippedAt ?? '-2 months', 'now');
            }

            if ($shipmentStatus === ShipmentStatus::Failed) {
                $shippedAt = fake()->dateTimeBetween('-2 months', '-1 week');
            }

            // Calculate weight (approx 1.5kg per 750ml bottle)
            $weight = $bottleCount * 1.5;

            // Destination address (from order or generate)
            $destinationAddress = $order->destination_address ?? fake()->address();

            // Notes for special cases
            $notes = null;
            if ($shipmentStatus === ShipmentStatus::Failed) {
                $notes = fake()->randomElement([
                    'Delivery failed: recipient not at address',
                    'Package returned: damaged in transit',
                    'Customs clearance failed',
                    'Address not found - returned to warehouse',
                    'Refused by recipient',
                ]);
            } elseif (fake()->boolean(20)) {
                $notes = fake()->randomElement([
                    'Signature obtained on delivery',
                    'Left with concierge',
                    'Delivered to side entrance as requested',
                    'Temperature-controlled transport used',
                    'Insurance claimed for minor damage',
                ]);
            }

            Shipment::create([
                'shipping_order_id' => $order->id,
                'carrier' => $carrierName,
                'tracking_number' => $trackingNumber,
                'shipped_at' => $shippedAt,
                'delivered_at' => $deliveredAt,
                'status' => $shipmentStatus,
                'shipped_bottle_serials' => $shippedSerials,
                'origin_warehouse_id' => $warehouse->id,
                'destination_address' => $destinationAddress,
                'weight' => number_format($weight, 2),
                'notes' => $notes,
            ]);

            $totalCreated++;
        }

        // Create some additional "preparing" shipments for variety
        $draftOrders = ShippingOrder::whereIn('status', [
            ShippingOrderStatus::Planned,
            ShippingOrderStatus::Picking,
        ])->take(5)->get();

        foreach ($draftOrders as $order) {
            $warehouse = $order->source_warehouse_id
                ? Location::find($order->source_warehouse_id)
                : $warehouses->random();

            if (! $warehouse) {
                $warehouse = $warehouses->random();
            }

            $bottleCount = fake()->numberBetween(1, 6);
            $shippedSerials = [];
            for ($i = 0; $i < $bottleCount; $i++) {
                $shippedSerials[] = 'PENDING-'.fake()->regexify('[A-Z0-9]{10}');
            }

            Shipment::create([
                'shipping_order_id' => $order->id,
                'carrier' => fake()->randomElement(array_keys($carriers)),
                'tracking_number' => null,
                'shipped_at' => null,
                'delivered_at' => null,
                'status' => ShipmentStatus::Preparing,
                'shipped_bottle_serials' => $shippedSerials,
                'origin_warehouse_id' => $warehouse->id,
                'destination_address' => $order->destination_address ?? fake()->address(),
                'weight' => number_format($bottleCount * 1.5, 2),
                'notes' => 'Awaiting final packing confirmation',
            ]);

            $totalCreated++;
        }

        $this->command->info("Created {$totalCreated} shipments.");
    }
}
