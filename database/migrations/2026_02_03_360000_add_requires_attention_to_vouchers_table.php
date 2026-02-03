<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add requires_attention flag to vouchers table for quarantine/anomaly management.
 *
 * Vouchers with requires_attention=true are considered anomalous and need
 * manual intervention. These are outside the normal scope of operations
 * and may have data integrity issues.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            // Flag for anomalous vouchers requiring manual attention
            $table->boolean('requires_attention')->default(false)->after('suspended');

            // Reason for requiring attention (for diagnostic purposes)
            $table->string('attention_reason')->nullable()->after('requires_attention');

            // Index for filtering anomalous vouchers
            $table->index('requires_attention');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->dropIndex(['requires_attention']);
            $table->dropColumn(['requires_attention', 'attention_reason']);
        });
    }
};
