<?php

namespace Database\Seeders;

use App\Enums\Customer\PartyRoleType;
use App\Enums\Customer\PartyStatus;
use App\Enums\Procurement\InboundPackaging;
use App\Enums\Procurement\OwnershipFlag;
use App\Enums\Procurement\ProcurementIntentStatus;
use App\Enums\Procurement\PurchaseOrderStatus;
use App\Enums\Procurement\SourcingModel;
use App\Models\Customer\Party;
use App\Models\Inventory\Location;
use App\Models\Procurement\ProcurementIntent;
use App\Models\Procurement\PurchaseOrder;
use App\Models\User;
use App\Services\Procurement\InboundService;
use App\Services\Procurement\ProcurementIntentService;
use App\Services\Procurement\ProducerSupplierConfigService;
use Illuminate\Database\Seeder;

/**
 * ProcurementSeeder - Manages PurchaseOrders and Inbounds for auto-created intents.
 *
 * ProcurementIntents are auto-created by the VoucherIssued event listener.
 * This seeder:
 * 1. Creates ProducerSupplierConfigs via service
 * 2. Transitions auto-created intents through their lifecycle
 * 3. Creates PurchaseOrders (no service exists — direct create)
 * 4. Creates Inbounds via InboundService lifecycle
 */
class ProcurementSeeder extends Seeder
{
    public function run(): void
    {
        $intentService = app(ProcurementIntentService::class);
        $inboundService = app(InboundService::class);
        $configService = app(ProducerSupplierConfigService::class);

        // Get supplier and producer parties
        $suppliers = Party::whereHas('roles', function ($query) {
            $query->where('role', PartyRoleType::Supplier);
        })->where('status', PartyStatus::Active)->get();

        $producers = Party::whereHas('roles', function ($query) {
            $query->where('role', PartyRoleType::Producer);
        })->where('status', PartyStatus::Active)->get();

        if ($suppliers->isEmpty()) {
            $this->command->warn('No supplier parties found. Run PartySeeder first.');

            return;
        }

        // Get warehouse locations
        $warehouses = Location::where('location_type', 'main_warehouse')
            ->orWhere('location_type', 'satellite_warehouse')
            ->get();

        $admin = User::first();

        // 1. Create ProducerSupplierConfigs via service
        $this->createProducerSupplierConfigs($configService, $producers);

        // 2. Recover auto-created ProcurementIntents from VoucherIssued listener
        $intents = ProcurementIntent::where('status', ProcurementIntentStatus::Draft)->get();

        if ($intents->isEmpty()) {
            $this->command->warn('No draft ProcurementIntents found. VoucherIssued listener may not have fired.');
            $this->command->info('Creating manual intents as fallback...');
            $intents = $this->createManualIntents($intentService);
        }

        $this->command->info("Found {$intents->count()} draft ProcurementIntents to process.");

        // 3. Transition intents through lifecycle and create linked objects
        // Distribution: 15% stay draft, 20% approved, 40% executed, 25% closed
        $intentCount = $intents->count();
        $draftCount = (int) round($intentCount * 0.15);
        $approvedCount = (int) round($intentCount * 0.20);
        $executedCount = (int) round($intentCount * 0.40);
        // Rest become closed

        $index = 0;
        foreach ($intents as $intent) {
            if ($index < $draftCount) {
                // Leave as Draft
                $index++;

                continue;
            }

            // Approve all remaining
            try {
                $intentService->approve($intent);
                $intent->refresh();
            } catch (\Throwable $e) {
                $this->command->warn("Approve failed for intent {$intent->id}: {$e->getMessage()}");
                $index++;

                continue;
            }

            if ($index < $draftCount + $approvedCount) {
                // Create PO for some approved intents
                if (fake()->boolean(70)) {
                    $this->createPurchaseOrder($intent, $suppliers, $admin, $warehouses);
                }
                $index++;

                continue;
            }

            // Mark executed
            try {
                $intentService->markExecuted($intent);
                $intent->refresh();
            } catch (\Throwable $e) {
                $this->command->warn("Execute failed for intent {$intent->id}: {$e->getMessage()}");
                $index++;

                continue;
            }

            // Create PO + Inbound for executed intents
            $po = $this->createPurchaseOrder($intent, $suppliers, $admin, $warehouses);
            if ($po && $warehouses->isNotEmpty()) {
                $this->createInbound($inboundService, $intent, $po, $warehouses->random(), $index >= $draftCount + $approvedCount + $executedCount);
            }

            // Close intents that should be closed
            if ($index >= $draftCount + $approvedCount + $executedCount) {
                try {
                    $intentService->close($intent);
                } catch (\Throwable $e) {
                    // Close has strict validation — log and continue
                    $this->command->warn("Close failed for intent {$intent->id}: {$e->getMessage()}");
                }
            }

            $index++;
        }
    }

    private function createProducerSupplierConfigs(ProducerSupplierConfigService $configService, $producers): void
    {
        foreach ($producers as $producer) {
            try {
                $configService->getOrCreate($producer);
            } catch (\Throwable $e) {
                $this->command->warn("Config creation failed for {$producer->legal_name}: {$e->getMessage()}");
            }
        }
    }

    private function createManualIntents(ProcurementIntentService $intentService): \Illuminate\Support\Collection
    {
        $skus = \App\Models\Pim\SellableSku::where('lifecycle_status', \App\Models\Pim\SellableSku::STATUS_ACTIVE)
            ->take(15)
            ->get();

        $intents = collect();
        foreach ($skus as $sku) {
            try {
                $intent = $intentService->createManual([
                    'product_reference_type' => 'sellable_skus',
                    'product_reference_id' => $sku->id,
                    'quantity' => fake()->numberBetween(6, 48),
                    'sourcing_model' => fake()->randomElement([
                        SourcingModel::Purchase,
                        SourcingModel::PassiveConsignment,
                    ]),
                    'rationale' => 'Manual intent created during seeding.',
                ]);
                $intents->push($intent);
            } catch (\Throwable $e) {
                $this->command->warn("Manual intent failed for SKU {$sku->id}: {$e->getMessage()}");
            }
        }

        return $intents;
    }

    private function createPurchaseOrder(
        ProcurementIntent $intent,
        $suppliers,
        ?User $admin,
        $warehouses
    ): ?PurchaseOrder {
        $supplier = $suppliers->random();
        $warehouse = $warehouses->isNotEmpty() ? $warehouses->random() : null;
        $unitCost = number_format(rand(5000, 200000) / 100, 2, '.', '');

        $poStatus = match ($intent->status) {
            ProcurementIntentStatus::Approved => PurchaseOrderStatus::Sent,
            ProcurementIntentStatus::Executed => PurchaseOrderStatus::Confirmed,
            ProcurementIntentStatus::Closed => PurchaseOrderStatus::Closed,
            default => PurchaseOrderStatus::Draft,
        };

        try {
            return PurchaseOrder::create([
                'procurement_intent_id' => $intent->id,
                'supplier_party_id' => $supplier->id,
                'product_reference_type' => $intent->product_reference_type,
                'product_reference_id' => $intent->product_reference_id,
                'quantity' => $intent->quantity,
                'unit_cost' => $unitCost,
                'currency' => 'EUR',
                'incoterms' => fake()->randomElement(['EXW', 'DAP', 'DDP', 'CIF']),
                'ownership_transfer' => $intent->sourcing_model === SourcingModel::Purchase,
                'expected_delivery_start' => now()->addDays(fake()->numberBetween(14, 30)),
                'expected_delivery_end' => now()->addDays(fake()->numberBetween(30, 60)),
                'destination_warehouse' => $warehouse?->name,
                'status' => $poStatus,
                'confirmed_at' => in_array($poStatus, [PurchaseOrderStatus::Confirmed, PurchaseOrderStatus::Closed])
                    ? now()->subDays(fake()->numberBetween(1, 30))
                    : null,
                'confirmed_by' => in_array($poStatus, [PurchaseOrderStatus::Confirmed, PurchaseOrderStatus::Closed])
                    ? $admin?->id
                    : null,
            ]);
        } catch (\Throwable $e) {
            $this->command->warn("PO creation failed for intent {$intent->id}: {$e->getMessage()}");

            return null;
        }
    }

    private function createInbound(
        InboundService $inboundService,
        ProcurementIntent $intent,
        PurchaseOrder $po,
        Location $warehouse,
        bool $shouldComplete
    ): void {
        $ownershipFlag = $po->ownership_transfer ? OwnershipFlag::Owned : OwnershipFlag::InCustody;

        try {
            $inbound = $inboundService->record([
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
                'procurement_intent_id' => $intent->id,
                'purchase_order_id' => $po->id,
                'condition_notes' => fake()->boolean(20) ? 'All bottles in excellent condition' : null,
                'serialization_required' => true,
                'serialization_location_authorized' => $warehouse->name,
            ]);

            // Route the inbound
            $inboundService->route($inbound, $warehouse->name);
            $inbound->refresh();

            // Complete and hand off if this intent should be closed
            if ($shouldComplete) {
                $inboundService->complete($inbound);
                $inbound->refresh();
                $inboundService->handOffToModuleB($inbound);
            }
        } catch (\Throwable $e) {
            $this->command->warn("Inbound creation failed for PO {$po->id}: {$e->getMessage()}");
        }
    }
}
