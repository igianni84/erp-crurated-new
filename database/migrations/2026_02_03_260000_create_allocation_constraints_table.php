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
        Schema::create('allocation_constraints', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // One-to-one relationship with allocation
            $table->foreignUuid('allocation_id')
                ->unique()
                ->constrained('allocations')
                ->cascadeOnDelete();

            // Commercial constraints (JSON arrays)
            $table->json('allowed_channels')->nullable();
            $table->json('allowed_geographies')->nullable();
            $table->json('allowed_customer_types')->nullable();

            // Advanced constraints
            $table->string('composition_constraint_group')->nullable();
            $table->boolean('fungibility_exception')->default(false);

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
        Schema::dropIfExists('allocation_constraints');
    }
};
