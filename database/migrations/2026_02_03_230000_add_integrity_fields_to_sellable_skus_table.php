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
        Schema::table('sellable_skus', function (Blueprint $table) {
            // Integrity flags
            $table->boolean('is_intrinsic')->default(false)->after('barcode')
                ->comment('SKU represents original producer packaging');
            $table->boolean('is_producer_original')->default(false)->after('is_intrinsic')
                ->comment('Packaging as originally released by producer');
            $table->boolean('is_verified')->default(false)->after('is_producer_original')
                ->comment('SKU configuration has been verified');

            // Source tracking
            $table->enum('source', ['manual', 'liv_ex', 'producer', 'generated'])->default('manual')->after('is_verified')
                ->comment('How this SKU was created');

            // Notes for the SKU
            $table->text('notes')->nullable()->after('source');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sellable_skus', function (Blueprint $table) {
            $table->dropColumn([
                'is_intrinsic',
                'is_producer_original',
                'is_verified',
                'source',
                'notes',
            ]);
        });
    }
};
