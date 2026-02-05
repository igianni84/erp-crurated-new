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
        Schema::create('inbounds', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Optional link to Procurement Intent (nullable - inbound without intent is flagged but allowed)
            $table->foreignUuid('procurement_intent_id')
                ->nullable()
                ->constrained('procurement_intents')
                ->nullOnDelete();

            // Optional link to Purchase Order (nullable)
            $table->foreignUuid('purchase_order_id')
                ->nullable()
                ->constrained('purchase_orders')
                ->nullOnDelete();

            // Physical location
            $table->string('warehouse');

            // Morphic product reference (SellableSku or LiquidProduct)
            $table->string('product_reference_type');
            $table->uuid('product_reference_id');

            // Quantity
            $table->unsignedInteger('quantity');

            // Packaging type
            $table->enum('packaging', [
                'cases',
                'loose',
                'mixed',
            ]);

            // Ownership flag - explicit, does NOT imply ownership from inbound
            $table->enum('ownership_flag', [
                'owned',
                'in_custody',
                'pending',
            ])->default('pending');

            // Receipt date
            $table->date('received_date');

            // Condition notes
            $table->text('condition_notes')->nullable();

            // Serialization fields
            $table->boolean('serialization_required')->default(true);
            $table->string('serialization_location_authorized')->nullable();
            $table->text('serialization_routing_rule')->nullable();

            // Lifecycle status
            $table->enum('status', [
                'recorded',
                'routed',
                'completed',
            ])->default('recorded');

            // Module B hand-off tracking
            $table->boolean('handed_to_module_b')->default(false);
            $table->timestamp('handed_to_module_b_at')->nullable();

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
            $table->index('procurement_intent_id');
            $table->index('purchase_order_id');
            $table->index('warehouse');
            $table->index(['product_reference_type', 'product_reference_id'], 'inbounds_product_ref_idx');
            $table->index('status');
            $table->index('ownership_flag');
            $table->index('received_date');
            $table->index('handed_to_module_b');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inbounds');
    }
};
