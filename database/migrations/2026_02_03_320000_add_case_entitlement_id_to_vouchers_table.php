<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add case_entitlement_id FK to vouchers table.
 * This allows vouchers to be associated with a CaseEntitlement when sold as part of a fixed case.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            // Case entitlement reference (nullable - not all vouchers are part of a case)
            $table->foreignUuid('case_entitlement_id')
                ->nullable()
                ->after('sellable_sku_id')
                ->constrained('case_entitlements')
                ->nullOnDelete();

            // Index for case membership lookups
            $table->index('case_entitlement_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->dropForeign(['case_entitlement_id']);
            $table->dropIndex(['case_entitlement_id']);
            $table->dropColumn('case_entitlement_id');
        });
    }
};
