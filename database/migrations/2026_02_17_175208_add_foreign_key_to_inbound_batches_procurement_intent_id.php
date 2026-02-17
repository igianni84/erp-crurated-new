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
        Schema::table('inbound_batches', function (Blueprint $table) {
            $table->foreign('procurement_intent_id')
                ->references('id')
                ->on('procurement_intents')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inbound_batches', function (Blueprint $table) {
            $table->dropForeign(['procurement_intent_id']);
        });
    }
};
