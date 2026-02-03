<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add external trading reference to vouchers for external trading suspension.
 * When a voucher is suspended for external trading, this field contains
 * the reference from the external trading platform.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            // External trading reference - stores the reference from external trading platform
            // when voucher is suspended for trading
            $table->string('external_trading_reference')->nullable()->after('suspended');

            // Index for querying vouchers by external trading reference
            $table->index('external_trading_reference');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->dropIndex(['external_trading_reference']);
            $table->dropColumn('external_trading_reference');
        });
    }
};
