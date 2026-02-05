<?php

namespace Database\Seeders;

use App\Enums\Fulfillment\PackagingPreference;
use App\Enums\Fulfillment\ShippingOrderStatus;
use App\Models\Customer\Customer;
use App\Models\Fulfillment\ShippingOrder;
use App\Models\Inventory\Location;
use Illuminate\Database\Seeder;

/**
 * ShippingOrderSeeder - Creates shipping orders for customers
 */
class ShippingOrderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $customers = Customer::where('status', Customer::STATUS_ACTIVE)->get();

        if ($customers->isEmpty()) {
            $this->command->warn('No active customers found. Run CustomerSeeder first.');

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

        // Shipping addresses per country
        $addresses = [
            'IT' => [
                'Via Roma 15, 20121 Milano MI, Italia',
                'Corso Vittorio Emanuele II 45, 00186 Roma RM, Italia',
                'Via Toledo 123, 80132 Napoli NA, Italia',
                'Via Torino 78, 10123 Torino TO, Italia',
            ],
            'UK' => [
                '10 Downing Street, London SW1A 2AA, United Kingdom',
                '221B Baker Street, London NW1 6XE, United Kingdom',
                'Castle Howard, York YO60 7DA, United Kingdom',
            ],
            'FR' => [
                '8 Rue de Rivoli, 75001 Paris, France',
                '15 Avenue des Champs-Élysées, 75008 Paris, France',
            ],
            'DE' => [
                'Friedrichstraße 123, 10117 Berlin, Germany',
                'Maximilianstraße 45, 80538 München, Germany',
            ],
            'CH' => [
                'Bahnhofstrasse 15, 8001 Zürich, Switzerland',
                'Rue du Rhône 48, 1204 Genève, Switzerland',
            ],
            'US' => [
                '350 Fifth Avenue, New York, NY 10118, USA',
                '1600 Pennsylvania Avenue, Washington DC 20500, USA',
            ],
        ];

        $carriers = ['DHL Express', 'UPS', 'FedEx', 'TNT', 'SEUR'];
        $shippingMethods = ['express', 'standard', 'economy', 'premium'];

        foreach ($customers->take(8) as $customer) {
            // Create 1-3 shipping orders per customer
            $numOrders = fake()->numberBetween(1, 3);

            for ($i = 0; $i < $numOrders; $i++) {
                $country = fake()->randomElement(array_keys($addresses));
                $address = fake()->randomElement($addresses[$country]);
                $warehouse = $warehouses->random();

                // Determine status with realistic distribution
                $statusRandom = fake()->numberBetween(1, 100);
                if ($statusRandom <= 10) {
                    $status = ShippingOrderStatus::Draft;
                } elseif ($statusRandom <= 25) {
                    $status = ShippingOrderStatus::Planned;
                } elseif ($statusRandom <= 35) {
                    $status = ShippingOrderStatus::Picking;
                } elseif ($statusRandom <= 50) {
                    $status = ShippingOrderStatus::Shipped;
                } elseif ($statusRandom <= 85) {
                    $status = ShippingOrderStatus::Completed;
                } elseif ($statusRandom <= 95) {
                    $status = ShippingOrderStatus::Cancelled;
                } else {
                    $status = ShippingOrderStatus::OnHold;
                }

                $packagingPreference = fake()->randomElement([
                    PackagingPreference::Loose,
                    PackagingPreference::Cases,
                    PackagingPreference::PreserveCases,
                ]);

                $requestedShipDate = match ($status) {
                    ShippingOrderStatus::Completed => fake()->dateTimeBetween('-3 months', '-1 week'),
                    ShippingOrderStatus::Cancelled => fake()->dateTimeBetween('-2 months', '-2 weeks'),
                    default => fake()->dateTimeBetween('-1 week', '+2 weeks'),
                };

                $specialInstructions = fake()->boolean(30)
                    ? fake()->randomElement([
                        'Please call before delivery',
                        'Leave at reception',
                        'Signature required',
                        'Fragile - handle with care',
                        'Do not leave outside',
                        'Deliver to side entrance',
                    ])
                    : null;

                // Approved for non-draft orders
                $approvedAt = $status !== ShippingOrderStatus::Draft
                    ? fake()->dateTimeBetween('-1 month', '-1 day')
                    : null;

                ShippingOrder::create([
                    'customer_id' => $customer->id,
                    'destination_address_id' => null, // Would be set if we had address entities
                    'destination_address' => $address,
                    'source_warehouse_id' => $warehouse->id,
                    'status' => $status,
                    'packaging_preference' => $packagingPreference,
                    'shipping_method' => fake()->randomElement($shippingMethods),
                    'carrier' => fake()->randomElement($carriers),
                    'incoterms' => fake()->randomElement(['DAP', 'DDP', 'EXW', 'CIF']),
                    'requested_ship_date' => $requestedShipDate,
                    'special_instructions' => $specialInstructions,
                    'created_by' => 1, // Admin user
                    'approved_by' => $approvedAt ? 1 : null,
                    'approved_at' => $approvedAt,
                    'previous_status' => $status === ShippingOrderStatus::OnHold
                        ? fake()->randomElement([ShippingOrderStatus::Planned, ShippingOrderStatus::Picking])
                        : null,
                ]);
            }
        }
    }
}
