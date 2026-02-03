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
        Schema::create('allocations', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Bottle SKU reference (WineVariant + Format)
            $table->foreignUuid('wine_variant_id')
                ->constrained('wine_variants')
                ->cascadeOnDelete();
            $table->foreignUuid('format_id')
                ->constrained('formats')
                ->cascadeOnDelete();

            // Allocation type and form
            $table->enum('source_type', [
                'producer_allocation',
                'owned_stock',
                'passive_consignment',
                'third_party_custody',
            ]);
            $table->enum('supply_form', ['bottled', 'liquid']);

            // Quantities
            $table->unsignedInteger('total_quantity');
            $table->unsignedInteger('sold_quantity')->default(0);

            // Availability window
            $table->date('expected_availability_start')->nullable();
            $table->date('expected_availability_end')->nullable();

            // Serialization requirement
            $table->boolean('serialization_required')->default(true);

            // Status
            $table->enum('status', ['draft', 'active', 'exhausted', 'closed'])
                ->default('draft');

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

            // Index for common queries
            $table->index(['status', 'supply_form']);
            $table->index(['wine_variant_id', 'format_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('allocations');
    }
};
