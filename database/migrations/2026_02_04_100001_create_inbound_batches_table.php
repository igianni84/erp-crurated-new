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
        Schema::create('inbound_batches', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Source information
            $table->string('source_type'); // producer, supplier, transfer

            // Morphic product reference
            $table->string('product_reference_type');
            $table->uuid('product_reference_id');

            // Allocation lineage (critical for provenance)
            $table->uuid('allocation_id')->nullable();
            $table->foreign('allocation_id')
                ->references('id')
                ->on('allocations')
                ->nullOnDelete();

            // Procurement intent (Module D reference, nullable)
            $table->uuid('procurement_intent_id')->nullable();

            // Quantities
            $table->unsignedInteger('quantity_expected');
            $table->unsignedInteger('quantity_received')->default(0);

            // Packaging
            $table->string('packaging_type');

            // Receiving location
            $table->uuid('receiving_location_id');
            $table->foreign('receiving_location_id')
                ->references('id')
                ->on('locations')
                ->restrictOnDelete();

            // Ownership
            $table->enum('ownership_type', [
                'crurated_owned',
                'in_custody',
                'third_party_owned',
            ]);

            // Dates
            $table->date('received_date');

            // Condition and notes
            $table->text('condition_notes')->nullable();

            // Serialization status
            $table->enum('serialization_status', [
                'pending_serialization',
                'partially_serialized',
                'fully_serialized',
                'discrepancy',
            ])->default('pending_serialization');

            // WMS integration
            $table->string('wms_reference_id')->nullable();

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
            $table->index(['serialization_status']);
            $table->index(['receiving_location_id']);
            $table->index(['allocation_id']);
            $table->index(['ownership_type']);
            $table->index(['received_date']);
            $table->index(['product_reference_type', 'product_reference_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inbound_batches');
    }
};
