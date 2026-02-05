<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds source context fields to procurement_intents table for tracking
     * the originating allocation and voucher when intents are auto-created
     * from voucher sales.
     */
    public function up(): void
    {
        Schema::table('procurement_intents', function (Blueprint $table) {
            // Source context fields for voucher-driven intents
            $table->foreignUuid('source_allocation_id')
                ->nullable()
                ->after('rationale')
                ->constrained('allocations')
                ->nullOnDelete();

            $table->foreignUuid('source_voucher_id')
                ->nullable()
                ->after('source_allocation_id')
                ->constrained('vouchers')
                ->nullOnDelete();

            // Flag for Ops review (true when intent needs attention)
            $table->boolean('needs_ops_review')
                ->default(false)
                ->after('source_voucher_id');

            // Index for filtering intents needing review
            $table->index('needs_ops_review');
            $table->index('source_allocation_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('procurement_intents', function (Blueprint $table) {
            $table->dropForeign(['source_allocation_id']);
            $table->dropForeign(['source_voucher_id']);
            $table->dropIndex(['needs_ops_review']);
            $table->dropIndex(['source_allocation_id']);
            $table->dropColumn(['source_allocation_id', 'source_voucher_id', 'needs_ops_review']);
        });
    }
};
