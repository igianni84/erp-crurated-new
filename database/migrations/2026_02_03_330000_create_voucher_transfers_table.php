<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * VoucherTransfer model - tracks transfers of vouchers between customers.
 * A transfer does not create a new voucher, it only changes the holder.
 * A transfer does not consume allocation.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('voucher_transfers', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // The voucher being transferred
            $table->foreignUuid('voucher_id')
                ->constrained('vouchers')
                ->cascadeOnDelete();

            // From customer (current holder at time of transfer)
            $table->foreignUuid('from_customer_id')
                ->constrained('customers')
                ->cascadeOnDelete();

            // To customer (recipient of the transfer)
            $table->foreignUuid('to_customer_id')
                ->constrained('customers')
                ->cascadeOnDelete();

            // Transfer status
            $table->enum('status', ['pending', 'accepted', 'cancelled', 'expired'])
                ->default('pending');

            // Timestamps for transfer lifecycle
            $table->timestamp('initiated_at');
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->timestamps();

            // Indexes for common queries
            $table->index('status');
            $table->index(['voucher_id', 'status']);
            $table->index('from_customer_id');
            $table->index('to_customer_id');
            $table->index('expires_at');

            // Ensure a voucher can only have one pending transfer at a time
            $table->unique(['voucher_id', 'status'], 'voucher_pending_transfer_unique')
                ->where('status', 'pending');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('voucher_transfers');
    }
};
