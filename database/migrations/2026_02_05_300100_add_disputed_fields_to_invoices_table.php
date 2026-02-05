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
        Schema::table('invoices', function (Blueprint $table) {
            $table->boolean('is_disputed')->default(false)->after('xero_synced_at');
            $table->timestamp('disputed_at')->nullable()->after('is_disputed');
            $table->text('dispute_reason')->nullable()->after('disputed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['is_disputed', 'disputed_at', 'dispute_reason']);
        });
    }
};
