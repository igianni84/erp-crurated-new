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
        Schema::create('producer_supplier_configs', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // FK to Party (unique constraint for one-to-one relationship)
            $table->foreignUuid('party_id')
                ->unique()
                ->constrained('parties')
                ->cascadeOnDelete();

            // Default bottling deadline in days (nullable)
            $table->unsignedInteger('default_bottling_deadline_days')->nullable();

            // Allowed bottle formats as JSON array (nullable)
            $table->json('allowed_formats')->nullable();

            // Serialization constraints as JSON (nullable)
            $table->json('serialization_constraints')->nullable();

            // General notes (nullable)
            $table->text('notes')->nullable();

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
            $table->softDeletes();

            // Index for party lookup
            $table->index('party_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('producer_supplier_configs');
    }
};
