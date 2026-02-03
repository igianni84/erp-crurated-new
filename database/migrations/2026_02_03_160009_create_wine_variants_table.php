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
        Schema::create('wine_variants', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Foreign key to wine_masters
            $table->foreignUuid('wine_master_id')->constrained('wine_masters')->cascadeOnDelete();

            // Vintage specific fields
            $table->integer('vintage_year');
            $table->decimal('alcohol_percentage', 4, 2)->nullable();
            $table->integer('drinking_window_start')->nullable();
            $table->integer('drinking_window_end')->nullable();

            // JSON fields
            $table->json('critic_scores')->nullable();
            $table->json('production_notes')->nullable();

            // Audit fields
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            // Unique constraint: vintage_year must be unique per wine_master
            $table->unique(['wine_master_id', 'vintage_year']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wine_variants');
    }
};
