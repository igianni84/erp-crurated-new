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
        Schema::create('cases', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Case configuration reference
            $table->uuid('case_configuration_id');
            $table->foreign('case_configuration_id')
                ->references('id')
                ->on('case_configurations')
                ->restrictOnDelete();

            // Allocation lineage
            $table->uuid('allocation_id');
            $table->foreign('allocation_id')
                ->references('id')
                ->on('allocations')
                ->restrictOnDelete();

            // Inbound batch reference (nullable)
            $table->uuid('inbound_batch_id')->nullable();
            $table->foreign('inbound_batch_id')
                ->references('id')
                ->on('inbound_batches')
                ->nullOnDelete();

            // Current location
            $table->uuid('current_location_id');
            $table->foreign('current_location_id')
                ->references('id')
                ->on('locations')
                ->restrictOnDelete();

            // Case properties
            $table->boolean('is_original')->default(true);
            $table->boolean('is_breakable')->default(true);

            // Integrity status
            $table->enum('integrity_status', [
                'intact',
                'broken',
            ])->default('intact');

            // Breaking info (populated when case is broken)
            $table->timestamp('broken_at')->nullable();
            $table->foreignId('broken_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->text('broken_reason')->nullable();

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
            $table->index(['integrity_status']);
            $table->index(['case_configuration_id']);
        });

        // Add foreign key from serialized_bottles to cases
        Schema::table('serialized_bottles', function (Blueprint $table) {
            $table->foreign('case_id')
                ->references('id')
                ->on('cases')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('serialized_bottles', function (Blueprint $table) {
            $table->dropForeign(['case_id']);
        });

        Schema::dropIfExists('cases');
    }
};
