<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            // Primary key
            $table->uuid('id')->primary();

            // Invoice identification
            $table->string('invoice_number')->nullable()->unique();

            // Invoice type (IMMUTABLE after creation)
            $table->string('invoice_type');

            // Customer relationship
            $table->foreignUuid('customer_id')
                ->constrained('customers')
                ->restrictOnDelete();

            // Currency and amounts
            $table->string('currency', 3)->default('EUR');
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('tax_amount', 10, 2)->default(0);
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->decimal('amount_paid', 10, 2)->default(0);

            // Status
            $table->string('status')->default('draft');

            // Source reference (polymorphic - tracks what generated this invoice)
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();

            // Dates
            $table->timestamp('issued_at')->nullable();
            $table->date('due_date')->nullable();

            // Additional info
            $table->text('notes')->nullable();

            // Xero integration
            $table->string('xero_invoice_id')->nullable();
            $table->timestamp('xero_synced_at')->nullable();

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
            $table->index('invoice_type');
            $table->index('status');
            $table->index('issued_at');
            $table->index('due_date');
            $table->index(['source_type', 'source_id']);
            $table->index(['customer_id', 'status']);

            // Unique constraint for source reference (prevent duplicate invoices for same event)
            // Note: This allows NULLs (manual invoices) but prevents duplicates for same source
            $table->unique(['source_type', 'source_id'], 'invoices_source_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
