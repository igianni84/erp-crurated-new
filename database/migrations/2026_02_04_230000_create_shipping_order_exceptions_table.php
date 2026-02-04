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
        Schema::create('shipping_order_exceptions', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Parent shipping order
            $table->uuid('shipping_order_id');
            $table->foreign('shipping_order_id')
                ->references('id')
                ->on('shipping_orders')
                ->cascadeOnDelete();

            // Optional link to specific line
            $table->uuid('shipping_order_line_id')->nullable();
            $table->foreign('shipping_order_line_id')
                ->references('id')
                ->on('shipping_order_lines')
                ->nullOnDelete();

            // Exception type
            $table->enum('exception_type', [
                'supply_insufficient',
                'voucher_ineligible',
                'wms_discrepancy',
                'binding_failed',
                'case_integrity_violated',
                'ownership_constraint',
                'early_binding_failed',
            ]);

            // Description and resolution
            $table->text('description');
            $table->text('resolution_path')->nullable();

            // Status
            $table->enum('status', ['active', 'resolved'])->default('active');

            // Resolution tracking
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')
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
            $table->index('shipping_order_line_id');
            $table->index('exception_type');
            $table->index('status');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipping_order_exceptions');
    }
};
