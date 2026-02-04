<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // FX rate snapshot at issuance - captures exchange rate when invoice is issued
            // NULL for draft invoices and EUR invoices (base currency)
            $table->decimal('fx_rate_at_issuance', 10, 6)->nullable()->after('currency');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('fx_rate_at_issuance');
        });
    }
};
