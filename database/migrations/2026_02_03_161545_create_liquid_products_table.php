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
        Schema::create('liquid_products', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Foreign key to wine_variants
            $table->foreignUuid('wine_variant_id')->constrained('wine_variants')->cascadeOnDelete();

            // JSON fields for flexible configuration
            $table->json('allowed_equivalent_units')->nullable();
            $table->json('allowed_final_formats')->nullable();
            $table->json('allowed_case_configurations')->nullable();
            $table->json('bottling_constraints')->nullable();

            // Serialization requirement
            $table->boolean('serialization_required')->default(true);

            // Lifecycle status enum
            $table->enum('lifecycle_status', ['draft', 'in_review', 'approved', 'published', 'archived'])->default('draft');

            // Audit fields
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            // One liquid product per wine variant
            $table->unique('wine_variant_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('liquid_products');
    }
};
