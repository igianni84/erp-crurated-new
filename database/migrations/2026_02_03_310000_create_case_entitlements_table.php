<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CaseEntitlement model - groups vouchers when a customer buys a fixed case.
 * A case entitlement represents ownership of a complete case with multiple bottles.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('case_entitlements', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Customer (owner of the case entitlement)
            $table->foreignUuid('customer_id')
                ->constrained('customers')
                ->cascadeOnDelete();

            // Sellable SKU (the fixed case that was sold)
            $table->foreignUuid('sellable_sku_id')
                ->constrained('sellable_skus')
                ->cascadeOnDelete();

            // Status: intact (all bottles together) or broken (one or more bottles transferred/traded/redeemed)
            $table->enum('status', ['intact', 'broken'])->default('intact');

            // Timestamp when the case was broken
            $table->timestamp('broken_at')->nullable();

            // Reason why the case was broken (transfer, trade, partial_redemption)
            $table->string('broken_reason')->nullable();

            $table->timestamps();

            // Indexes for common queries
            $table->index('status');
            $table->index(['customer_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('case_entitlements');
    }
};
