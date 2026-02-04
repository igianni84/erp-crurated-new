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
        Schema::table('shipping_orders', function (Blueprint $table) {
            // Add text destination address field for now
            // This is a temporary solution until Module K (Addresses) is implemented
            // The destination_address_id FK will be used when Address model is available
            $table->text('destination_address')->nullable()->after('destination_address_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shipping_orders', function (Blueprint $table) {
            $table->dropColumn('destination_address');
        });
    }
};
