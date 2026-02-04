<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds mis-serialization correction fields for US-B029:
     * - Updates state enum to include 'mis_serialized'
     * - Adds correction_reference field to link original and corrective records
     */
    public function up(): void
    {
        // First, modify the state enum to add 'mis_serialized'
        // MySQL requires MODIFY COLUMN for enum changes
        DB::statement("ALTER TABLE serialized_bottles MODIFY COLUMN state ENUM(
            'stored',
            'reserved_for_picking',
            'shipped',
            'consumed',
            'destroyed',
            'missing',
            'mis_serialized'
        ) DEFAULT 'stored'");

        // Add correction_reference field for linking original and corrective records
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

        // Revert state enum to original values
        DB::statement("ALTER TABLE serialized_bottles MODIFY COLUMN state ENUM(
            'stored',
            'reserved_for_picking',
            'shipped',
            'consumed',
            'destroyed',
            'missing'
        ) DEFAULT 'stored'");
    }
};
