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
        Schema::create('discount_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('rule_type'); // percentage, fixed_amount, tiered, volume_based
            $table->json('logic_definition'); // Contains: value, tiers, thresholds based on type
            $table->string('status')->default('active'); // active, inactive
            $table->timestamps();

            // Index for status filtering
            $table->index('status');
            // Index for rule type filtering
            $table->index('rule_type');
        });

        // Add foreign key constraint to offer_benefits table for discount_rule_id
        Schema::table('offer_benefits', function (Blueprint $table) {
            $table->foreign('discount_rule_id')
                ->references('id')
                ->on('discount_rules')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove foreign key constraint from offer_benefits
        Schema::table('offer_benefits', function (Blueprint $table) {
            $table->dropForeign(['discount_rule_id']);
        });

        Schema::dropIfExists('discount_rules');
    }
};
