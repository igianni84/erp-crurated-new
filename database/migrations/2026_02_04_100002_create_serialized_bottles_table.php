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
        Schema::create('serialized_bottles', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Serial number - unique and immutable identifier
            $table->string('serial_number')->unique();

            // Wine product reference
            $table->uuid('wine_variant_id');
            $table->foreign('wine_variant_id')
                ->references('id')
                ->on('wine_variants')
                ->restrictOnDelete();

            // Format reference
            $table->uuid('format_id');
            $table->foreign('format_id')
                ->references('id')
                ->on('formats')
                ->restrictOnDelete();

            // Allocation lineage - IMMUTABLE after creation
            $table->uuid('allocation_id');
            $table->foreign('allocation_id')
                ->references('id')
                ->on('allocations')
                ->restrictOnDelete();

            // Inbound batch reference
            $table->uuid('inbound_batch_id');
            $table->foreign('inbound_batch_id')
                ->references('id')
                ->on('inbound_batches')
                ->restrictOnDelete();

            // Current location
            $table->uuid('current_location_id');
            $table->foreign('current_location_id')
                ->references('id')
                ->on('locations')
                ->restrictOnDelete();

            // Case reference (nullable - bottle may not be in a case)
            $table->uuid('case_id')->nullable();
            // Note: FK to cases will be added when cases table is created

            // Ownership and custody
            $table->enum('ownership_type', [
                'crurated_owned',
                'in_custody',
                'third_party_owned',
            ]);
            $table->string('custody_holder')->nullable();

            // Physical state
            $table->enum('state', [
                'stored',
                'reserved_for_picking',
                'shipped',
                'consumed',
                'destroyed',
                'missing',
                'mis_serialized',
            ])->default('stored');

            // Serialization info
            $table->timestamp('serialized_at');
            $table->foreignId('serialized_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // NFT provenance
            $table->string('nft_reference')->nullable();
            $table->timestamp('nft_minted_at')->nullable();

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
            $table->index(['allocation_id']);
            $table->index(['inbound_batch_id']);
            $table->index(['current_location_id']);
            $table->index(['case_id']);
            $table->index(['state']);
            $table->index(['ownership_type']);
            $table->index(['wine_variant_id']);
            $table->index(['serialized_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('serialized_bottles');
    }
};
