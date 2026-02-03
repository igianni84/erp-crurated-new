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
        Schema::create('liquid_allocation_constraints', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // One-to-one relationship with allocation
            $table->foreignUuid('allocation_id')
                ->unique()
                ->constrained('allocations')
                ->cascadeOnDelete();

            // Liquid-specific constraints (JSON arrays)
            $table->json('allowed_bottling_formats')->nullable();
            $table->json('allowed_case_configurations')->nullable();

            // Bottling deadline
            $table->date('bottling_confirmation_deadline')->nullable();

            // Audit fields
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('liquid_allocation_constraints');
    }
};
