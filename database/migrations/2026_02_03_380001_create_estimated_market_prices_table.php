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
        Schema::create('estimated_market_prices', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Foreign key to Sellable SKU
            $table->foreignUuid('sellable_sku_id')
                ->constrained('sellable_skus')
                ->cascadeOnDelete();

            // Market identifier (e.g., 'UK', 'US', 'EU')
            $table->string('market');

            // Estimated Market Price value
            $table->decimal('emp_value', 12, 2);

            // Source of the EMP data
            $table->enum('source', ['livex', 'internal', 'composite']);

            // Confidence level of the EMP data
            $table->enum('confidence_level', ['high', 'medium', 'low']);

            // When the EMP data was fetched/calculated
            $table->timestamp('fetched_at')->nullable();

            $table->timestamps();

            // Unique constraint: one EMP per SKU per market
            $table->unique(['sellable_sku_id', 'market'], 'emp_sku_market_unique');

            // Index for common queries
            $table->index(['market', 'confidence_level']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('estimated_market_prices');
    }
};
