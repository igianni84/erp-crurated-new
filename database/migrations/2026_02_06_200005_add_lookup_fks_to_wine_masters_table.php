<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds FK columns alongside existing string columns for backward compatibility.
     * The legacy string columns (producer, country, region, appellation) are NOT removed.
     */
    public function up(): void
    {
        Schema::table('wine_masters', function (Blueprint $table) {
            $table->foreignUuid('producer_id')->nullable()->after('producer')
                ->constrained('producers')->nullOnDelete();
            $table->foreignUuid('country_id')->nullable()->after('country')
                ->constrained('countries')->nullOnDelete();
            $table->foreignUuid('region_id')->nullable()->after('region')
                ->constrained('regions')->nullOnDelete();
            $table->foreignUuid('appellation_id')->nullable()->after('appellation')
                ->constrained('appellations')->nullOnDelete();

            $table->index('producer_id');
            $table->index('country_id');
            $table->index('region_id');
            $table->index('appellation_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('wine_masters', function (Blueprint $table) {
            $table->dropConstrainedForeignId('producer_id');
            $table->dropConstrainedForeignId('country_id');
            $table->dropConstrainedForeignId('region_id');
            $table->dropConstrainedForeignId('appellation_id');
        });
    }
};
