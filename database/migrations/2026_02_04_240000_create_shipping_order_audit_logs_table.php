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
        Schema::create('shipping_order_audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Parent shipping order
            $table->uuid('shipping_order_id');
            $table->foreign('shipping_order_id')
                ->references('id')
                ->on('shipping_orders')
                ->cascadeOnDelete();

            // Event details
            $table->string('event_type');
            $table->text('description');

            // Change tracking
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();

            // User who triggered the event (nullable for system events)
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Only created_at - audit logs are immutable (no updates, no deletes)
            $table->timestamp('created_at')->useCurrent();

            // Indexes for common queries
            $table->index('shipping_order_id');
            $table->index('event_type');
            $table->index('user_id');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipping_order_audit_logs');
    }
};
