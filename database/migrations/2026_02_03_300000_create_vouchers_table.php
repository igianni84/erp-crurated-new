<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Voucher model - atomic customer entitlement for Module A.
 * A voucher represents a customer's right to one bottle (or bottle-equivalent).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('vouchers', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Customer (current holder of the voucher)
            $table->foreignUuid('customer_id')
                ->constrained('customers')
                ->cascadeOnDelete();

            // Allocation lineage (immutable after creation)
            $table->foreignUuid('allocation_id')
                ->constrained('allocations')
                ->cascadeOnDelete();

            // Bottle SKU reference (WineVariant + Format) - inherited from allocation
            $table->foreignUuid('wine_variant_id')
                ->constrained('wine_variants')
                ->cascadeOnDelete();
            $table->foreignUuid('format_id')
                ->constrained('formats')
                ->cascadeOnDelete();

            // Sellable SKU (what was actually sold - nullable for direct allocation)
            $table->foreignUuid('sellable_sku_id')
                ->nullable()
                ->constrained('sellable_skus')
                ->nullOnDelete();

            // Quantity is always 1 (1 voucher = 1 bottle or bottle-equivalent)
            $table->unsignedTinyInteger('quantity')->default(1);

            // Lifecycle state
            $table->enum('lifecycle_state', ['issued', 'locked', 'redeemed', 'cancelled'])
                ->default('issued');

            // Behavioral flags
            $table->boolean('tradable')->default(true);
            $table->boolean('giftable')->default(true);
            $table->boolean('suspended')->default(false);

            // Sale reference (for tracking which sale created this voucher)
            $table->string('sale_reference')->nullable();

            // Audit fields
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            // Indexes for common queries
            $table->index('lifecycle_state');
            $table->index(['customer_id', 'lifecycle_state']);
            $table->index('allocation_id');
            $table->index('sale_reference');

            // Composite index for bottle SKU lookups
            $table->index(['wine_variant_id', 'format_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vouchers');
    }
};
