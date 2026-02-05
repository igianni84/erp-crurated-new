<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Foundation seeders (required by other seeders)
        $this->call([
            UserSeeder::class,
            FormatSeeder::class,
            CaseConfigurationSeeder::class,
            AttributeSetSeeder::class,
        ]);

        // Domain data seeders
        $this->call([
            // Customer & PIM
            CustomerSeeder::class,
            WineMasterSeeder::class,
            WineVariantSeeder::class,
            LocationSeeder::class,

            // Allocations & Vouchers
            AllocationSeeder::class,
            VoucherSeeder::class,

            // Finance
            SubscriptionSeeder::class,
            InvoiceSeeder::class,
            PaymentSeeder::class,

            // Inventory & Fulfillment
            InventoryCaseSeeder::class,
            ShippingOrderSeeder::class,
        ]);
    }
}
