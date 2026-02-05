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
        Schema::create('pricing_policy_scopes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('pricing_policy_id')
                ->constrained('pricing_policies')
                ->cascadeOnDelete();
            $table->string('scope_type');
            $table->string('scope_reference')->nullable();
            $table->json('markets')->nullable();
            $table->json('channels')->nullable();
            $table->timestamps();

            // Ensure one scope per policy
            $table->unique('pricing_policy_id');
            // Index for finding policies by scope type
            $table->index('scope_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pricing_policy_scopes');
    }
};
