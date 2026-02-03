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
        Schema::create('temporary_reservations', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Allocation reference
            $table->foreignUuid('allocation_id')
                ->constrained('allocations')
                ->cascadeOnDelete();

            // Reservation details
            $table->unsignedInteger('quantity');
            $table->enum('context_type', ['checkout', 'negotiation', 'manual_hold']);
            $table->string('context_reference')->nullable();

            // Status
            $table->enum('status', ['active', 'expired', 'cancelled', 'converted'])
                ->default('active');

            // Expiration
            $table->timestamp('expires_at');

            // Created by (optional)
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            // Indexes for common queries
            $table->index(['status', 'expires_at']);
            $table->index(['allocation_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('temporary_reservations');
    }
};
