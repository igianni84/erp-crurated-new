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
        Schema::create('price_book_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Price Book association (required)
            $table->foreignUuid('price_book_id')
                ->constrained('price_books')
                ->cascadeOnDelete();

            // Sellable SKU association (required)
            $table->foreignUuid('sellable_sku_id')
                ->constrained('sellable_skus')
                ->cascadeOnDelete();

            // Base price (must be > 0)
            $table->decimal('base_price', 12, 2);

            // Source of the price
            $table->enum('source', ['manual', 'policy_generated'])->default('manual');

            // Optional pricing policy reference (FK will be enforced when pricing_policies table exists)
            $table->uuid('policy_id')->nullable();

            $table->timestamps();

            // Unique constraint: one price per SKU per Price Book
            $table->unique(['price_book_id', 'sellable_sku_id'], 'price_book_entry_unique');

            // Index for efficient lookups by SKU
            $table->index('sellable_sku_id');

            // Index for finding entries by policy
            $table->index('policy_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('price_book_entries');
    }
};
