<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_payments', function (Blueprint $table) {
            // Primary key
            $table->id();

            // Invoice relationship
            $table->foreignUuid('invoice_id')
                ->constrained('invoices')
                ->cascadeOnDelete();

            // Payment relationship
            $table->foreignUuid('payment_id')
                ->constrained('payments')
                ->cascadeOnDelete();

            // Amount applied from this payment to this invoice
            $table->decimal('amount_applied', 10, 2);

            // When the payment was applied
            $table->timestamp('applied_at');

            // Who applied the payment (nullable for automated reconciliation)
            $table->foreignId('applied_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Timestamps
            $table->timestamps();

            // Indexes for efficient queries
            $table->index('invoice_id');
            $table->index('payment_id');
            $table->index('applied_at');

            // Unique constraint to prevent duplicate applications
            // (same payment can be applied to same invoice only once)
            $table->unique(['invoice_id', 'payment_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_payments');
    }
};
