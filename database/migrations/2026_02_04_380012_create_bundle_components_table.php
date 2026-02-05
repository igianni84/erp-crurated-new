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
        Schema::create('bundle_components', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('bundle_id')
                ->constrained('bundles')
                ->cascadeOnDelete();
            $table->foreignUuid('sellable_sku_id')
                ->constrained('sellable_skus')
                ->cascadeOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->timestamps();

            // Unique constraint: a SKU can only appear once in a bundle
            $table->unique(['bundle_id', 'sellable_sku_id']);

            // Index for SKU lookups
            $table->index('sellable_sku_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bundle_components');
    }
};
