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
        Schema::create('sellable_skus', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Foreign keys
            $table->foreignUuid('wine_variant_id')->constrained('wine_variants')->cascadeOnDelete();
            $table->foreignUuid('format_id')->constrained('formats')->cascadeOnDelete();
            $table->foreignUuid('case_configuration_id')->constrained('case_configurations')->cascadeOnDelete();

            // SKU identifiers
            $table->string('sku_code')->unique();
            $table->string('barcode')->nullable();

            // Lifecycle
            $table->enum('lifecycle_status', ['draft', 'active', 'retired'])->default('draft');

            // Audit fields
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            // Unique constraint: combination of wine_variant + format + case_configuration must be unique
            $table->unique(['wine_variant_id', 'format_id', 'case_configuration_id'], 'sellable_skus_combination_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sellable_skus');
    }
};
