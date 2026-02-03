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
        Schema::table('wine_variants', function (Blueprint $table) {
            // Description field for wine variant
            $table->text('description')->nullable()->after('thumbnail_url');
            // JSON field to store which fields are locked (imported from Liv-ex)
            $table->json('locked_fields')->nullable()->after('description');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('wine_variants', function (Blueprint $table) {
            $table->dropColumn(['description', 'locked_fields']);
        });
    }
};
