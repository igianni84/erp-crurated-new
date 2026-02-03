<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a unique constraint for sale_reference per allocation+customer combination.
 *
 * This ensures idempotency when issuing vouchers - duplicate requests for the same
 * sale will return existing vouchers instead of creating duplicates.
 *
 * Note: We use a unique index on (allocation_id, customer_id, sale_reference) rather than
 * just (sale_reference) to allow the same sale_reference to be reused across different
 * allocation/customer combinations (which might be valid in some business scenarios).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            // Add unique constraint for sale_reference within allocation+customer scope
            // This allows idempotent voucher issuance - calling issueVouchers with the
            // same parameters will return existing vouchers instead of duplicates
            $table->unique(
                ['allocation_id', 'customer_id', 'sale_reference'],
                'vouchers_allocation_customer_sale_reference_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->dropUnique('vouchers_allocation_customer_sale_reference_unique');
        });
    }
};
