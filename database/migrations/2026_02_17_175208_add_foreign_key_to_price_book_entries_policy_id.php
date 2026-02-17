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
        Schema::table('price_book_entries', function (Blueprint $table) {
            $table->foreign('policy_id')
                ->references('id')
                ->on('pricing_policies')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('price_book_entries', function (Blueprint $table) {
            $table->dropForeign(['policy_id']);
        });
    }
};
