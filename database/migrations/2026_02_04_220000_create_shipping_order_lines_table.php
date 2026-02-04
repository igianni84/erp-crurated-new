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
        Schema::create('shipping_order_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Parent shipping order
            $table->uuid('shipping_order_id');
            $table->foreign('shipping_order_id')
                ->references('id')
                ->on('shipping_orders')
                ->cascadeOnDelete();

            // Voucher relationship - 1 voucher = 1 line = 1 bottle
            $table->uuid('voucher_id');
            $table->foreign('voucher_id')
                ->references('id')
                ->on('vouchers')
                ->restrictOnDelete();

            // Allocation lineage - IMMUTABLE after creation, copied from voucher
            $table->uuid('allocation_id');
            $table->foreign('allocation_id')
                ->references('id')
                ->on('allocations')
                ->restrictOnDelete();

            // Status workflow
            $table->enum('status', [
                'pending',
                'validated',
                'picked',
                'shipped',
                'cancelled',
            ])->default('pending');

            // Late binding - populated after WMS pick confirmation
            $table->string('bound_bottle_serial')->nullable();

            // Bound case (if shipping as case)
            $table->uuid('bound_case_id')->nullable();
            $table->foreign('bound_case_id')
                ->references('id')
                ->on('cases')
                ->nullOnDelete();

            // Early binding - pre-bound serial from Module D personalization
            $table->string('early_binding_serial')->nullable();

            // Binding confirmation tracking
            $table->timestamp('binding_confirmed_at')->nullable();
            $table->foreignId('binding_confirmed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

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
            $table->index('shipping_order_id');
            $table->index('voucher_id');
            $table->index('allocation_id');
            $table->index('status');
            $table->index('bound_bottle_serial');
            $table->index('early_binding_serial');

            // Unique constraint: one voucher per active shipping order line
            // (allows same voucher in cancelled lines)
            $table->unique(['voucher_id', 'shipping_order_id'], 'sol_voucher_order_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipping_order_lines');
    }
};
