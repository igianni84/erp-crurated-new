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
            $table->enum('data_source', ['liv_ex', 'manual'])->default('manual')->after('production_notes');
            $table->string('lwin_code')->nullable()->after('data_source');
            $table->string('internal_code')->nullable()->after('lwin_code');
            $table->string('thumbnail_url')->nullable()->after('internal_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('wine_variants', function (Blueprint $table) {
            $table->dropColumn(['data_source', 'lwin_code', 'internal_code', 'thumbnail_url']);
        });
    }
};
