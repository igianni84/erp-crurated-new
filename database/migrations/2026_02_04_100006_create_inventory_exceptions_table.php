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
        Schema::create('inventory_exceptions', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Exception type (e.g., 'shortage', 'overage', 'damage', 'committed_consumption_override')
            $table->string('exception_type');

            // Reference to serialized bottle (nullable)
            $table->uuid('serialized_bottle_id')->nullable();
            $table->foreign('serialized_bottle_id')
                ->references('id')
                ->on('serialized_bottles')
                ->nullOnDelete();

            // Reference to case (nullable)
            $table->uuid('case_id')->nullable();
            $table->foreign('case_id')
                ->references('id')
                ->on('cases')
                ->nullOnDelete();

            // Reference to inbound batch (nullable)
            $table->uuid('inbound_batch_id')->nullable();
            $table->foreign('inbound_batch_id')
                ->references('id')
                ->on('inbound_batches')
                ->nullOnDelete();

            // Reason for the exception (required)
            $table->text('reason');

            // Resolution details (nullable, filled when resolved)
            $table->text('resolution')->nullable();

            // Resolution tracking
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Creator (required)
            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();

            // Auditable fields (created_by/updated_by handled by Auditable trait)
            $table->timestamps();
            $table->softDeletes();

            // Indexes for common queries
            $table->index(['exception_type']);
            $table->index(['serialized_bottle_id']);
            $table->index(['case_id']);
            $table->index(['inbound_batch_id']);
            $table->index(['resolved_at']);
            $table->index(['created_by']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_exceptions');
    }
};
