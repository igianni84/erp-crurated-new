<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * The seeders are organized in a hierarchical dependency order:
     *
     * Phase 1: Foundation (no dependencies)
     * Phase 2: Core Entities (depend on foundation)
     * Phase 3: Module-specific data (depend on core entities)
     * Phase 4: Transactional/derived data (depend on module data)
     * Phase 5: Audit/Movement data (depend on all previous phases)
     */
    public function run(): void
    {
        // =====================================================================
        // PHASE 1: Foundation seeders (no external dependencies)
        // =====================================================================
        $this->call([
            UserSeeder::class,          // System users (admin, operators)
            FormatSeeder::class,        // Wine bottle formats (375ml, 750ml, 1500ml, etc.)
            CaseConfigurationSeeder::class, // Case packaging types (OWC, OC, loose)
            AttributeSetSeeder::class,  // Product attribute definitions
        ]);

        // =====================================================================
        // PHASE 2: Core entities (depend on foundation)
        // =====================================================================
        $this->call([
            // Parties & Customers (Module K)
            PartySeeder::class,         // Producers, suppliers, partners
            CustomerSeeder::class,      // Wine collector customers
            AddressSeeder::class,       // Customer billing/shipping addresses
            AccountSeeder::class,       // Customer operational accounts (B2C, B2B, Club)
            MembershipSeeder::class,    // Customer membership tiers & status

            // Product Information (Module PIM)
            WineMasterSeeder::class,    // Wine identities (Sassicaia, Barolo, etc.)
            WineVariantSeeder::class,   // Specific vintages of wines
            ProductMediaSeeder::class,  // Wine bottle images (depends on WineVariant)
            LiquidProductSeeder::class, // Liquid products for en primeur
            SellableSkuSeeder::class,   // Commercial units (Variant × Format × Case)

            // Commercial (Module S)
            ChannelSeeder::class,       // B2C, B2B, Private Club channels

            // Inventory (Module B)
            LocationSeeder::class,      // Warehouses, storage locations
        ]);

        // =====================================================================
        // PHASE 3: Module-specific data (depend on core entities)
        // =====================================================================
        $this->call([
            // Allocations & Vouchers (Module A)
            AllocationSeeder::class,    // Wine allocations (sellable supply)
            VoucherSeeder::class,       // Customer vouchers (entitlements)

            // Commercial pricing & offers (Module S)
            PriceBookSeeder::class,     // Price books with market-specific pricing
            OfferSeeder::class,         // Commercial offers linking SKUs to channels

            // Finance (Module E)
            SubscriptionSeeder::class,  // Customer memberships/recurring billing
            InvoiceSeeder::class,       // Invoices (5 types: membership, sales, shipping, storage, events)
            PaymentSeeder::class,       // Payments (Stripe, bank transfer)

            // Inventory (Module B)
            InventoryCaseSeeder::class, // Physical wine cases
        ]);

        // =====================================================================
        // PHASE 4: Transactional/derived data (depend on previous phases)
        // =====================================================================
        $this->call([
            // Procurement (Module D)
            ProcurementSeeder::class,   // Procurement intents, POs, inbounds

            // Inventory serialization (Module B)
            SerializedBottleSeeder::class, // Serialized bottles with provenance

            // Fulfillment (Module C)
            ShippingOrderSeeder::class,     // Shipping orders
            ShippingOrderLineSeeder::class, // Shipping order lines (voucher → bottle binding)
            ShipmentSeeder::class,          // Physical shipments with tracking

            // Finance corrections (Module E)
            CreditNoteSeeder::class,    // Credit notes and refunds
        ]);

        // =====================================================================
        // PHASE 5: Audit/Movement data (depend on all previous phases)
        // =====================================================================
        $this->call([
            // Inventory movements (immutable audit trail)
            InventoryMovementSeeder::class, // Internal transfers, consignment, events
        ]);
    }
}
