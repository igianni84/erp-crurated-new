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
        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            // Linked entities
            $table->foreignUuid('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->foreignUuid('payment_id')->constrained('payments')->cascadeOnDelete();
            $table->foreignUuid('credit_note_id')->nullable()->constrained('credit_notes')->nullOnDelete();

            // Refund details
            $table->string('refund_type'); // enum: full, partial
            $table->string('method'); // enum: stripe, bank_transfer
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('EUR');
            $table->string('status')->default('pending'); // enum: pending, processed, failed
            $table->text('reason'); // NOT NULL - required for audit

            // External references
            $table->string('stripe_refund_id')->nullable()->unique();
            $table->string('bank_reference')->nullable();

            // Processing tracking
            $table->timestamp('processed_at')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();

            // Audit fields
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['status']);
            $table->index(['method']);
            $table->index(['invoice_id', 'payment_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('refunds');
    }
};
