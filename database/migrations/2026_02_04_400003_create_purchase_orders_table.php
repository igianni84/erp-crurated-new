<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Required link to Procurement Intent (NOT NULL enforced at DB level)
            $table->foreignUuid('procurement_intent_id')
                ->constrained('procurement_intents')
                ->cascadeOnDelete();

            // Supplier (Party)
            $table->foreignUuid('supplier_party_id')
                ->constrained('parties')
                ->cascadeOnDelete();

            // Polymorphic product reference (bottle_sku/sellable_sku OR liquid_product)
            $table->string('product_reference_type');
            $table->uuid('product_reference_id');

            // Quantity in bottles or bottle-equivalents
            $table->unsignedInteger('quantity');

            // Commercial terms
            $table->decimal('unit_cost', 12, 2);
            $table->string('currency', 3);
            $table->string('incoterms')->nullable();
            $table->boolean('ownership_transfer')->default(true);

            // Delivery expectations
            $table->date('expected_delivery_start')->nullable();
            $table->date('expected_delivery_end')->nullable();
            $table->string('destination_warehouse')->nullable();
            $table->text('serialization_routing_note')->nullable();

            // Status
            $table->enum('status', [
                'draft',
                'sent',
                'confirmed',
                'closed',
            ])->default('draft');

            // Audit fields
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            // Indexes for common queries
            $table->index(['product_reference_type', 'product_reference_id']);
            $table->index(['status', 'expected_delivery_start']);
            $table->index('supplier_party_id');
            $table->index('procurement_intent_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
