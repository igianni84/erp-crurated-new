<?php

namespace Database\Seeders;

use App\Enums\Customer\AddressType;
use App\Models\Customer\Address;
use App\Models\Customer\Customer;
use Illuminate\Database\Seeder;

/**
 * AddressSeeder - Creates billing and shipping addresses for customers
 *
 * Creates realistic addresses for existing customers, ensuring each active
 * customer has at least one billing and one shipping address.
 */
class AddressSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $customers = Customer::all();

        if ($customers->isEmpty()) {
            $this->command->warn('No customers found. Run CustomerSeeder first.');

            return;
        }

        // Predefined realistic addresses by country
        $italianAddresses = [
            ['line_1' => 'Via Monte Napoleone, 15', 'city' => 'Milano', 'state' => 'MI', 'postal_code' => '20121', 'country' => 'IT'],
            ['line_1' => 'Via Condotti, 88', 'city' => 'Roma', 'state' => 'RM', 'postal_code' => '00187', 'country' => 'IT'],
            ['line_1' => 'Via Tornabuoni, 42', 'city' => 'Firenze', 'state' => 'FI', 'postal_code' => '50123', 'country' => 'IT'],
            ['line_1' => 'Piazza San Marco, 1', 'city' => 'Venezia', 'state' => 'VE', 'postal_code' => '30124', 'country' => 'IT'],
            ['line_1' => 'Corso Vittorio Emanuele II, 75', 'city' => 'Torino', 'state' => 'TO', 'postal_code' => '10128', 'country' => 'IT'],
            ['line_1' => 'Via Chiaia, 149', 'city' => 'Napoli', 'state' => 'NA', 'postal_code' => '80121', 'country' => 'IT'],
            ['line_1' => 'Via Garibaldi, 33', 'city' => 'Genova', 'state' => 'GE', 'postal_code' => '16124', 'country' => 'IT'],
            ['line_1' => 'Via dell\'Indipendenza, 55', 'city' => 'Bologna', 'state' => 'BO', 'postal_code' => '40121', 'country' => 'IT'],
        ];

        $ukAddresses = [
            ['line_1' => '42 Mayfair Gardens', 'line_2' => 'Apartment 3A', 'city' => 'London', 'state' => null, 'postal_code' => 'W1K 6TQ', 'country' => 'GB'],
            ['line_1' => '15 Royal Crescent', 'city' => 'Bath', 'state' => null, 'postal_code' => 'BA1 2LR', 'country' => 'GB'],
            ['line_1' => '88 Deansgate', 'city' => 'Manchester', 'state' => null, 'postal_code' => 'M3 2FH', 'country' => 'GB'],
        ];

        $frenchAddresses = [
            ['line_1' => '25 Avenue Montaigne', 'city' => 'Paris', 'state' => null, 'postal_code' => '75008', 'country' => 'FR'],
            ['line_1' => '10 Cours Mirabeau', 'city' => 'Aix-en-Provence', 'state' => null, 'postal_code' => '13100', 'country' => 'FR'],
            ['line_1' => '5 Place Bellecour', 'city' => 'Lyon', 'state' => null, 'postal_code' => '69002', 'country' => 'FR'],
        ];

        $germanAddresses = [
            ['line_1' => 'Maximilianstraße 52', 'city' => 'München', 'state' => 'BY', 'postal_code' => '80538', 'country' => 'DE'],
            ['line_1' => 'Königsallee 60', 'city' => 'Düsseldorf', 'state' => 'NW', 'postal_code' => '40212', 'country' => 'DE'],
        ];

        $usAddresses = [
            ['line_1' => '725 5th Avenue', 'line_2' => 'Suite 2200', 'city' => 'New York', 'state' => 'NY', 'postal_code' => '10022', 'country' => 'US'],
            ['line_1' => '9500 Wilshire Blvd', 'city' => 'Beverly Hills', 'state' => 'CA', 'postal_code' => '90212', 'country' => 'US'],
            ['line_1' => '333 N Michigan Ave', 'city' => 'Chicago', 'state' => 'IL', 'postal_code' => '60601', 'country' => 'US'],
        ];

        $swissAddresses = [
            ['line_1' => 'Bahnhofstrasse 75', 'city' => 'Zürich', 'state' => 'ZH', 'postal_code' => '8001', 'country' => 'CH'],
            ['line_1' => 'Rue du Rhône 48', 'city' => 'Genève', 'state' => 'GE', 'postal_code' => '1204', 'country' => 'CH'],
        ];

        $otherAddresses = [
            ['line_1' => 'Tverskaya Street 15', 'city' => 'Moscow', 'state' => null, 'postal_code' => '125009', 'country' => 'RU'],
            ['line_1' => '88 Connaught Road Central', 'city' => 'Hong Kong', 'state' => null, 'postal_code' => '999077', 'country' => 'HK'],
            ['line_1' => 'Ginza 4-6-12', 'city' => 'Tokyo', 'state' => null, 'postal_code' => '104-0061', 'country' => 'JP'],
        ];

        $allAddressPools = array_merge(
            $italianAddresses,
            $ukAddresses,
            $frenchAddresses,
            $germanAddresses,
            $usAddresses,
            $swissAddresses,
            $otherAddresses
        );

        $addressIndex = 0;

        foreach ($customers as $customer) {
            // Skip closed customers - they don't need addresses
            if ($customer->status === Customer::STATUS_CLOSED) {
                continue;
            }

            // Get addresses for this customer (cycle through pools)
            $billingAddress = $allAddressPools[$addressIndex % count($allAddressPools)];
            $shippingAddress = $allAddressPools[($addressIndex + 1) % count($allAddressPools)];

            // Create billing address
            Address::firstOrCreate(
                [
                    'addressable_type' => Customer::class,
                    'addressable_id' => $customer->id,
                    'type' => AddressType::Billing,
                    'line_1' => $billingAddress['line_1'],
                ],
                [
                    'line_2' => $billingAddress['line_2'] ?? null,
                    'city' => $billingAddress['city'],
                    'state' => $billingAddress['state'],
                    'postal_code' => $billingAddress['postal_code'],
                    'country' => $billingAddress['country'],
                    'is_default' => true,
                ]
            );

            // Create shipping address (can be same or different from billing)
            Address::firstOrCreate(
                [
                    'addressable_type' => Customer::class,
                    'addressable_id' => $customer->id,
                    'type' => AddressType::Shipping,
                    'line_1' => $shippingAddress['line_1'],
                ],
                [
                    'line_2' => $shippingAddress['line_2'] ?? null,
                    'city' => $shippingAddress['city'],
                    'state' => $shippingAddress['state'],
                    'postal_code' => $shippingAddress['postal_code'],
                    'country' => $shippingAddress['country'],
                    'is_default' => true,
                ]
            );

            // Some customers have additional shipping addresses (30% chance)
            if (fake()->boolean(30)) {
                $extraAddress = $allAddressPools[($addressIndex + 2) % count($allAddressPools)];
                Address::firstOrCreate(
                    [
                        'addressable_type' => Customer::class,
                        'addressable_id' => $customer->id,
                        'type' => AddressType::Shipping,
                        'line_1' => $extraAddress['line_1'],
                    ],
                    [
                        'line_2' => $extraAddress['line_2'] ?? null,
                        'city' => $extraAddress['city'],
                        'state' => $extraAddress['state'],
                        'postal_code' => $extraAddress['postal_code'],
                        'country' => $extraAddress['country'],
                        'is_default' => false,
                    ]
                );
            }

            $addressIndex++;
        }
    }
}
