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
        Schema::create('offer_eligibilities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('offer_id')
                ->constrained('offers')
                ->cascadeOnDelete();
            $table->json('allowed_markets')->nullable();
            $table->json('allowed_customer_types')->nullable();
            $table->json('allowed_membership_tiers')->nullable();
            // Reference to authoritative allocation constraint from Module A
            // This cannot be overridden by eligibility rules
            $table->uuid('allocation_constraint_id')->nullable();
            $table->timestamps();

            // Ensure one eligibility per offer
            $table->unique('offer_id');
            // Index for constraint lookups
            $table->index('allocation_constraint_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('offer_eligibilities');
    }
};
