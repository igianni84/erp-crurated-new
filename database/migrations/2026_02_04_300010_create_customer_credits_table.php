<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the customer_credits table for tracking credits from overpayments.
     * Customer credits can be applied to future invoices.
     */
    public function up(): void
    {
        Schema::create('customer_credits', function (Blueprint $table) {
            // Primary key with UUID
            $table->id();
            $table->uuid('uuid')->unique();

            // Customer relationship (required)
            $table->foreignId('customer_id')
                ->constrained('customers')
                ->cascadeOnDelete();

            // Source reference - where did this credit come from?
            // Usually a payment that overpaid an invoice
            $table->foreignId('source_payment_id')
                ->nullable()
                ->constrained('payments')
                ->nullOnDelete();

            $table->foreignId('source_invoice_id')
                ->nullable()
                ->constrained('invoices')
                ->nullOnDelete();

            // Credit amounts
            $table->decimal('original_amount', 10, 2);  // Original credit amount
            $table->decimal('remaining_amount', 10, 2); // What's left to use
            $table->string('currency', 3)->default('EUR');

            // Status
            $table->string('status')->default('available');

            // Descriptive fields
            $table->string('reason');  // Required - why was this credit created?
            $table->text('notes')->nullable();

            // Expiration (optional)
            $table->date('expires_at')->nullable();

            // Audit fields
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Timestamps with soft deletes
            $table->timestamps();
            $table->softDeletes();

            // Indexes for common queries
            $table->index(['customer_id', 'status']);
            $table->index(['status', 'expires_at']);
            $table->index('source_payment_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_credits');
    }
};
