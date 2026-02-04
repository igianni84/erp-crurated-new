<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_lines', function (Blueprint $table) {
            // Primary key
            $table->id();

            // Invoice relationship
            $table->foreignUuid('invoice_id')
                ->constrained('invoices')
                ->cascadeOnDelete();

            // Line description
            $table->string('description');

            // Quantity and pricing
            $table->decimal('quantity', 8, 2);
            $table->decimal('unit_price', 10, 2);

            // Tax details
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('tax_amount', 10, 2)->default(0);

            // Line total (calculated: quantity * unit_price + tax_amount)
            $table->decimal('line_total', 10, 2);

            // Optional link to SellableSku
            $table->foreignId('sellable_sku_id')
                ->nullable()
                ->constrained('sellable_skus')
                ->nullOnDelete();

            // Additional metadata
            $table->json('metadata')->nullable();

            // Timestamps
            $table->timestamps();

            // Indexes
            $table->index('invoice_id');
            $table->index('sellable_sku_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_lines');
    }
};
