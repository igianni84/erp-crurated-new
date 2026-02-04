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
        Schema::create('movement_items', function (Blueprint $table) {
            $table->id();

            // Parent movement (required)
            $table->uuid('inventory_movement_id');
            $table->foreign('inventory_movement_id')
                ->references('id')
                ->on('inventory_movements')
                ->cascadeOnDelete();

            // Reference to serialized bottle (nullable)
            $table->uuid('serialized_bottle_id')->nullable();
            $table->foreign('serialized_bottle_id')
                ->references('id')
                ->on('serialized_bottles')
                ->nullOnDelete();

            // Reference to case (nullable)
            $table->uuid('case_id')->nullable();
            $table->foreign('case_id')
                ->references('id')
                ->on('cases')
                ->nullOnDelete();

            // Quantity (default 1 for single bottle/case)
            $table->unsignedInteger('quantity')->default(1);

            // Optional notes
            $table->text('notes')->nullable();

            $table->timestamps();
            // NO soft deletes - items are immutable

            // Indexes for common queries
            $table->index(['inventory_movement_id']);
            $table->index(['serialized_bottle_id']);
            $table->index(['case_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('movement_items');
    }
};
