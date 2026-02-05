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
        Schema::create('offer_benefits', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('offer_id')
                ->unique()
                ->constrained('offers')
                ->cascadeOnDelete();
            $table->string('benefit_type');
            $table->decimal('benefit_value', 12, 2)->nullable();
            $table->uuid('discount_rule_id')->nullable();
            $table->timestamps();

            // Index for discount rule lookups
            $table->index('discount_rule_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('offer_benefits');
    }
};
