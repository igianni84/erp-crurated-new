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
        // Add is_composite column to sellable_skus
        Schema::table('sellable_skus', function (Blueprint $table) {
            $table->boolean('is_composite')->default(false)->after('notes');
        });

        // Create composite_sku_items table for bundle composition
        Schema::create('composite_sku_items', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Foreign keys
            $table->foreignUuid('composite_sku_id')
                ->constrained('sellable_skus')
                ->cascadeOnDelete();
            $table->foreignUuid('sellable_sku_id')
                ->constrained('sellable_skus')
                ->cascadeOnDelete();

            // Quantity of this SKU in the composite
            $table->unsignedInteger('quantity')->default(1);

            // Audit fields
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            // Unique constraint: a component SKU can only appear once per composite SKU
            $table->unique(['composite_sku_id', 'sellable_sku_id'], 'composite_sku_items_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('composite_sku_items');

        Schema::table('sellable_skus', function (Blueprint $table) {
            $table->dropColumn('is_composite');
        });
    }
};
