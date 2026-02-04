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
        Schema::create('shipments', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Shipping Order relationship (NOT NULL - every shipment must have a SO)
            $table->uuid('shipping_order_id');
            $table->foreign('shipping_order_id')
                ->references('id')
                ->on('shipping_orders')
                ->restrictOnDelete();

            // Carrier & tracking
            $table->string('carrier');
            $table->string('tracking_number')->nullable();

            // Timestamps for shipment lifecycle
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();

            // Status
            $table->enum('status', [
                'preparing',
                'shipped',
                'in_transit',
                'delivered',
                'failed',
            ])->default('preparing');

            // Immutable record of shipped bottle serials (JSON array)
            $table->json('shipped_bottle_serials');

            // Origin warehouse (location)
            $table->uuid('origin_warehouse_id');
            $table->foreign('origin_warehouse_id')
                ->references('id')
                ->on('locations')
                ->restrictOnDelete();

            // Destination address (stored as text for immutable record)
            $table->text('destination_address');

            // Optional fields
            $table->decimal('weight', 10, 2)->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes for common queries
            $table->index('shipping_order_id');
            $table->index('carrier');
            $table->index('tracking_number');
            $table->index('status');
            $table->index('shipped_at');
            $table->index('delivered_at');
            $table->index('origin_warehouse_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};
