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
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Movement type and trigger
            $table->enum('movement_type', [
                'internal_transfer',
                'consignment_placement',
                'consignment_return',
                'event_shipment',
                'event_consumption',
            ]);
            $table->enum('trigger', [
                'wms_event',
                'erp_operator',
                'system_automatic',
            ]);

            // Source and destination locations (both nullable for different scenarios)
            $table->uuid('source_location_id')->nullable();
            $table->foreign('source_location_id')
                ->references('id')
                ->on('locations')
                ->nullOnDelete();

            $table->uuid('destination_location_id')->nullable();
            $table->foreign('destination_location_id')
                ->references('id')
                ->on('locations')
                ->nullOnDelete();

            // Custody change flag
            $table->boolean('custody_changed')->default(false);

            // Reason for movement (optional)
            $table->text('reason')->nullable();

            // WMS event reference for deduplication (unique, nullable)
            $table->string('wms_event_id')->nullable()->unique();

            // Execution info
            $table->timestamp('executed_at');
            $table->foreignId('executed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();
            // NO soft deletes - movements are immutable

            // Indexes for common queries
            $table->index(['movement_type']);
            $table->index(['trigger']);
            $table->index(['source_location_id']);
            $table->index(['destination_location_id']);
            $table->index(['executed_at']);
            $table->index(['executed_by']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
    }
};
