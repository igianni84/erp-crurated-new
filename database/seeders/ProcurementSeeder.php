<?php

namespace Database\Seeders;

use App\Enums\Procurement\InboundPackaging;
use App\Enums\Procurement\InboundStatus;
use App\Enums\Procurement\OwnershipFlag;
use App\Enums\Procurement\ProcurementIntentStatus;
use App\Enums\Procurement\ProcurementTriggerType;
use App\Enums\Procurement\PurchaseOrderStatus;
use App\Enums\Procurement\SourcingModel;
use App\Models\Allocation\Allocation;
use App\Models\Customer\Party;
use App\Models\Inventory\Location;
use App\Models\Pim\SellableSku;
use App\Models\Procurement\Inbound;
use App\Models\Procurement\ProcurementIntent;
use App\Models\Procurement\ProducerSupplierConfig;
use App\Models\Procurement\PurchaseOrder;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * ProcurementSeeder - Creates procurement intents, purchase orders, and inbounds
 *
 * Module D: Procurement converts supply commitments into purchase orders
 * and prepares for serialization.
 */
class ProcurementSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get supplier parties
        $suppliers = Party::whereHas('roles', function ($query) {
            $query->where('role', 'supplier');
        })->where('status', 'active')->get();

        if ($suppliers->isEmpty()) {
            $this->command->warn('No supplier parties found. Run PartySeeder first.');

            return;
        }

        // Get producer parties for supplier configs
        $producers = Party::whereHas('roles', function ($query) {
            $query->where('role', 'producer');
        })->where('status', 'active')->get();

        // Get active allocations
        $allocations = Allocation::where('status', 'active')
            ->with(['wineVariant.wineMaster', 'format'])
            ->take(20)
            ->get();

        // Get active SKUs
        $skus = SellableSku::where('lifecycle_status', SellableSku::STATUS_ACTIVE)
            ->with(['wineVariant.wineMaster', 'format', 'caseConfiguration'])
            ->take(15)
            ->get();

        if ($skus->isEmpty()) {
            $this->command->warn('No active SKUs found. Run SellableSkuSeeder first.');

            return;
        }

        // Get warehouse locations for inbounds
        $warehouses = Location::where('location_type', 'main_warehouse')
            ->orWhere('location_type', 'satellite_warehouse')
            ->where('status', 'active')
            ->get();

        // Get admin user
        $admin = User::first();

        // Create producer/supplier configurations for some producers
        $this->createProducerSupplierConfigs($producers);

        // Create procurement intents with various statuses
        $this->createProcurementIntents($skus, $allocations, $admin, $suppliers, $warehouses);
    }

    /**
     * Create producer/supplier configurations.
     *
     * Note: ProducerSupplierConfig only has: party_id, default_bottling_deadline_days,
     * allowed_formats, serialization_constraints, notes
     */
    private function createProducerSupplierConfigs($producers): void
    {
        $wineProducerNames = [
            'Giacomo Conterno' => ['default_bottling_deadline_days' => 90],
            'Bruno Giacosa' => ['default_bottling_deadline_days' => 60],
            'Gaja' => ['default_bottling_deadline_days' => 45],
            'Biondi-Santi' => ['default_bottling_deadline_days' => 120],
            'Tenuta San Guido' => ['default_bottling_deadline_days' => 60],
            'Marchesi Antinori' => ['default_bottling_deadline_days' => 30],
            'Domaine de la Romanée-Conti' => ['default_bottling_deadline_days' => 180],
            'Château Margaux' => ['default_bottling_deadline_days' => 90],
            'Château Latour' => ['default_bottling_deadline_days' => 90],
        ];

        foreach ($producers as $producer) {
            foreach ($wineProducerNames as $name => $config) {
                if (str_contains($producer->legal_name, $name)) {
                    ProducerSupplierConfig::firstOrCreate(
                        ['party_id' => $producer->id],
                        [
                            'default_bottling_deadline_days' => $config['default_bottling_deadline_days'],
                            'allowed_formats' => fake()->boolean(50) ? [750, 1500] : null,
                            'serialization_constraints' => fake()->boolean(30) ? [
                                'authorized_locations' => ['Geneva Warehouse', 'Milan Hub'],
                                'require_provenance_verification' => true,
                            ] : null,
                            'notes' => fake()->boolean(30) ? fake()->sentence() : null,
                        ]
                    );
                    break;
                }
            }
        }
    }

    /**
     * Create procurement intents with different statuses and linked objects.
     */
    private function createProcurementIntents($skus, $allocations, $admin, $suppliers, $warehouses): void
    {
        // Create 25-35 procurement intents with various states
        $intentCount = fake()->numberBetween(25, 35);

        for ($i = 0; $i < $intentCount; $i++) {
            // Select a random SKU or allocation as source
            $useAllocation = fake()->boolean(40) && $allocations->isNotEmpty();
            $sourceAllocation = $useAllocation ? $allocations->random() : null;
            $sku = $useAllocation ? null : $skus->random();

            // Determine product reference
            if ($useAllocation && $sourceAllocation) {
                $productType = 'sellable_skus';
                // Find or create a matching SKU for the allocation
                $matchingSku = SellableSku::where('wine_variant_id', $sourceAllocation->wine_variant_id)
                    ->where('format_id', $sourceAllocation->format_id)
                    ->first();

                if (! $matchingSku) {
                    $matchingSku = $skus->random();
                }

                $productId = $matchingSku->id;
            } else {
                $productType = 'sellable_skus';
                $productId = $sku->id;
            }

            // Determine trigger type (VoucherDriven, AllocationDriven, Strategic, Contractual)
            $triggerType = match (true) {
                $useAllocation => ProcurementTriggerType::VoucherDriven,
                fake()->boolean(30) => ProcurementTriggerType::AllocationDriven,
                fake()->boolean(50) => ProcurementTriggerType::Strategic,
                default => ProcurementTriggerType::Contractual,
            };

            // Determine sourcing model (Purchase, PassiveConsignment, ThirdPartyCustody)
            $sourcingModel = fake()->randomElement([
                SourcingModel::Purchase,
                SourcingModel::PassiveConsignment,
                SourcingModel::ThirdPartyCustody,
            ]);

            // Determine status distribution: 15% draft, 25% approved, 40% executed, 20% closed
            $statusRandom = fake()->numberBetween(1, 100);
            $status = match (true) {
                $statusRandom <= 15 => ProcurementIntentStatus::Draft,
                $statusRandom <= 40 => ProcurementIntentStatus::Approved,
                $statusRandom <= 80 => ProcurementIntentStatus::Executed,
                default => ProcurementIntentStatus::Closed,
            };

            $quantity = fake()->numberBetween(6, 72);
            $preferredLocation = $warehouses->isNotEmpty()
                ? $warehouses->random()->name
                : null;

            // Create procurement intent
            $intent = ProcurementIntent::create([
                'product_reference_type' => $productType,
                'product_reference_id' => $productId,
                'quantity' => $quantity,
                'trigger_type' => $triggerType,
                'sourcing_model' => $sourcingModel,
                'preferred_inbound_location' => $preferredLocation,
                'rationale' => $this->generateRationale($triggerType),
                'source_allocation_id' => $sourceAllocation?->id,
                'source_voucher_id' => null,
                'needs_ops_review' => $status === ProcurementIntentStatus::Draft && fake()->boolean(30),
                'status' => $status,
                'approved_at' => in_array($status, [ProcurementIntentStatus::Approved, ProcurementIntentStatus::Executed, ProcurementIntentStatus::Closed])
                    ? now()->subDays(fake()->numberBetween(7, 60))
                    : null,
                'approved_by' => in_array($status, [ProcurementIntentStatus::Approved, ProcurementIntentStatus::Executed, ProcurementIntentStatus::Closed])
                    ? $admin?->id
                    : null,
            ]);

            // Create purchase orders for approved/executed/closed intents (80% chance)
            if (in_array($status, [ProcurementIntentStatus::Approved, ProcurementIntentStatus::Executed, ProcurementIntentStatus::Closed])
                && fake()->boolean(80)) {
                $this->createPurchaseOrder($intent, $suppliers, $admin, $warehouses, $status);
            }
        }
    }

    /**
     * Generate rationale text based on trigger type.
     */
    private function generateRationale(ProcurementTriggerType $triggerType): string
    {
        return match ($triggerType) {
            ProcurementTriggerType::VoucherDriven => 'Auto-generated from voucher sale. Customer delivery expected within 30 days.',
            ProcurementTriggerType::AllocationDriven => 'Replenishment for active allocation. Current stock below threshold.',
            ProcurementTriggerType::Strategic => 'Strategic inventory build for upcoming vintage release.',
            ProcurementTriggerType::Contractual => fake()->randomElement([
                'Committed purchase per producer agreement.',
                'En primeur contractual obligation.',
                'Pre-agreed allocation fulfillment.',
            ]),
        };
    }

    /**
     * Create a purchase order for an intent.
     */
    private function createPurchaseOrder(
        ProcurementIntent $intent,
        $suppliers,
        $admin,
        $warehouses,
        ProcurementIntentStatus $intentStatus
    ): void {
        $supplier = $suppliers->random();

        // Unit cost based on wine type
        $unitCost = fake()->randomFloat(2, 50, 2000);

        // PO status depends on intent status
        $poStatus = match ($intentStatus) {
            ProcurementIntentStatus::Approved => fake()->randomElement([
                PurchaseOrderStatus::Draft,
                PurchaseOrderStatus::Sent,
            ]),
            ProcurementIntentStatus::Executed => fake()->randomElement([
                PurchaseOrderStatus::Sent,
                PurchaseOrderStatus::Confirmed,
            ]),
            ProcurementIntentStatus::Closed => PurchaseOrderStatus::Closed,
            default => PurchaseOrderStatus::Draft,
        };

        $warehouse = $warehouses->isNotEmpty() ? $warehouses->random() : null;

        $po = PurchaseOrder::create([
            'procurement_intent_id' => $intent->id,
            'supplier_party_id' => $supplier->id,
            'product_reference_type' => $intent->product_reference_type,
            'product_reference_id' => $intent->product_reference_id,
            'quantity' => $intent->quantity,
            'unit_cost' => number_format($unitCost, 2, '.', ''),
            'currency' => 'EUR',
            'incoterms' => fake()->randomElement(['EXW', 'DAP', 'DDP', 'CIF']),
            'ownership_transfer' => $intent->sourcing_model === SourcingModel::Purchase,
            'expected_delivery_start' => now()->addDays(fake()->numberBetween(14, 30)),
            'expected_delivery_end' => now()->addDays(fake()->numberBetween(30, 60)),
            'destination_warehouse' => $warehouse?->name,
            'serialization_routing_note' => fake()->boolean(20) ? 'Priority serialization required' : null,
            'status' => $poStatus,
            'confirmed_at' => in_array($poStatus, [PurchaseOrderStatus::Confirmed, PurchaseOrderStatus::Closed])
                ? now()->subDays(fake()->numberBetween(1, 30))
                : null,
            'confirmed_by' => in_array($poStatus, [PurchaseOrderStatus::Confirmed, PurchaseOrderStatus::Closed])
                ? $admin?->id
                : null,
        ]);

        // Create inbound for confirmed/closed POs (70% chance)
        if (in_array($poStatus, [PurchaseOrderStatus::Confirmed, PurchaseOrderStatus::Closed])
            && fake()->boolean(70)
            && $warehouse) {
            $this->createInbound($intent, $po, $warehouse, $admin, $poStatus);
        }
    }

    /**
     * Create an inbound record for a PO.
     *
     * Inbound model fields: procurement_intent_id, purchase_order_id, warehouse,
     * product_reference_type, product_reference_id, quantity, packaging, ownership_flag,
     * received_date, condition_notes, serialization_required, serialization_location_authorized,
     * serialization_routing_rule, status, handed_to_module_b, handed_to_module_b_at
     */
    private function createInbound(
        ProcurementIntent $intent,
        PurchaseOrder $po,
        Location $warehouse,
        $admin,
        PurchaseOrderStatus $poStatus
    ): void {
        // Inbound status depends on PO status (Recorded, Routed, Completed)
        $inboundStatus = match ($poStatus) {
            PurchaseOrderStatus::Confirmed => fake()->randomElement([
                InboundStatus::Recorded,
                InboundStatus::Routed,
            ]),
            PurchaseOrderStatus::Closed => fake()->randomElement([
                InboundStatus::Routed,
                InboundStatus::Completed,
            ]),
            default => InboundStatus::Recorded,
        };

        // Determine ownership flag (Owned, InCustody, Pending)
        $ownershipFlag = $po->ownership_transfer
            ? OwnershipFlag::Owned
            : fake()->randomElement([OwnershipFlag::InCustody, OwnershipFlag::Pending]);

        // For completed inbounds, ownership should be clarified
        if ($inboundStatus === InboundStatus::Completed && $ownershipFlag === OwnershipFlag::Pending) {
            $ownershipFlag = fake()->boolean(70) ? OwnershipFlag::Owned : OwnershipFlag::InCustody;
        }

        $isCompleted = $inboundStatus === InboundStatus::Completed;
        $handedToModuleB = $isCompleted && fake()->boolean(80);

        Inbound::create([
            'procurement_intent_id' => $intent->id,
            'purchase_order_id' => $po->id,
            'warehouse' => $warehouse->name,
            'product_reference_type' => $po->product_reference_type,
            'product_reference_id' => $po->product_reference_id,
            'quantity' => $po->quantity,
            'packaging' => fake()->randomElement([
                InboundPackaging::Cases,
                InboundPackaging::Loose,
                InboundPackaging::Mixed,
            ]),
            'ownership_flag' => $ownershipFlag,
            'received_date' => now()->subDays(fake()->numberBetween(1, 14)),
            'condition_notes' => fake()->boolean(20)
                ? fake()->randomElement(['All bottles in excellent condition', 'Minor label wear on 2 bottles', 'Original packaging intact'])
                : null,
            'serialization_required' => $warehouse->serialization_authorized ?? true,
            'serialization_location_authorized' => $warehouse->serialization_authorized ? $warehouse->name : null,
            'serialization_routing_rule' => fake()->boolean(20) ? 'Priority serialization within 48h' : null,
            'status' => $inboundStatus,
            'handed_to_module_b' => $handedToModuleB,
            'handed_to_module_b_at' => $handedToModuleB ? now()->subDays(fake()->numberBetween(1, 7)) : null,
        ]);
    }
}
