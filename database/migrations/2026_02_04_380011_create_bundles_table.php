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
        Schema::create('bundles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('bundle_sku')->unique(); // Unique SKU identifier for the bundle
            $table->string('pricing_logic'); // sum_components, fixed_price, percentage_off_sum
            $table->decimal('fixed_price', 12, 2)->nullable(); // For fixed_price pricing logic
            $table->decimal('percentage_off', 5, 2)->nullable(); // For percentage_off_sum logic (0-100)
            $table->string('status')->default('draft'); // draft, active, inactive
            $table->timestamps();
            $table->softDeletes();

            // Index for status filtering
            $table->index('status');
            // Index for pricing logic filtering
            $table->index('pricing_logic');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bundles');
    }
};
