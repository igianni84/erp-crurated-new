<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds mis-serialization correction fields for US-B029:
     * - The 'mis_serialized' state is now defined in the original migration
     * - Adds correction_reference field to link original and corrective records
     */
    public function up(): void
    {
        // Add correction_reference field for linking original and corrective records
        // Note: The 'mis_serialized' state is already included in the original
        // serialized_bottles table migration for SQLite compatibility
        Schema::table('serialized_bottles', function (Blueprint $table) {
            $table->uuid('correction_reference')
                ->nullable()
                ->after('nft_minted_at');

            // Foreign key to link to another SerializedBottle
            $table->foreign('correction_reference')
                ->references('id')
                ->on('serialized_bottles')
                ->nullOnDelete();

            // Index for efficient queries
            $table->index(['correction_reference']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('serialized_bottles', function (Blueprint $table) {
            $table->dropForeign(['correction_reference']);
            $table->dropIndex(['correction_reference']);
            $table->dropColumn('correction_reference');
        });
    }
};
