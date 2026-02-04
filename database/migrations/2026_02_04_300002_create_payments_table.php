<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            // Primary key
            $table->uuid('id')->primary();

            // Payment identification
            $table->string('payment_reference')->unique();

            // Payment source (stripe or bank_transfer)
            $table->string('source');

            // Amount and currency
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3);

            // Status fields
            $table->string('status');
            $table->string('reconciliation_status');

            // Stripe-specific fields
            $table->string('stripe_payment_intent_id')->nullable()->unique();
            $table->string('stripe_charge_id')->nullable();

            // Bank-specific fields
            $table->string('bank_reference')->nullable();

            // When the payment was received
            $table->timestamp('received_at');

            // Customer relationship (nullable for unreconciled payments)
            $table->foreignUuid('customer_id')
                ->nullable()
                ->constrained('customers')
                ->nullOnDelete();

            // Metadata for additional payment info
            $table->json('metadata')->nullable();

            // Audit fields (for Auditable trait)
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Timestamps and soft deletes
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('source');
            $table->index('status');
            $table->index('reconciliation_status');
            $table->index('received_at');
            $table->index(['customer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
