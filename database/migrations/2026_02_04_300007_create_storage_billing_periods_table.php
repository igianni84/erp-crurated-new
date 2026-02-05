<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('storage_billing_periods', function (Blueprint $table) {
            // Primary key
            $table->uuid('id')->primary();

            // Customer relationship
            $table->foreignUuid('customer_id')
                ->constrained('customers')
                ->cascadeOnDelete();

            // Location relationship (nullable - may aggregate all locations)
            $table->foreignUuid('location_id')
                ->nullable()
                ->constrained('locations')
                ->nullOnDelete();

            // Billing period
            $table->date('period_start');
            $table->date('period_end');

            // Usage metrics
            $table->integer('bottle_count');
            $table->integer('bottle_days'); // sum(bottles * days_stored) in period

            // Rate and amount
            $table->decimal('unit_rate', 10, 4);
            $table->decimal('calculated_amount', 10, 2);
            $table->string('currency', 3)->default('EUR');

            // Status
            $table->string('status'); // enum: pending, invoiced, paid, blocked

            // Invoice relationship (nullable - set when invoice generated)
            $table->foreignUuid('invoice_id')
                ->nullable()
                ->constrained('invoices')
                ->nullOnDelete();

            // Calculation timestamp
            $table->timestamp('calculated_at');

            // Metadata for additional info (rate tier, calculation details)
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
            $table->index('period_start');
            $table->index('period_end');
            $table->index('status');
            $table->index('calculated_at');
            $table->index(['customer_id', 'period_start', 'period_end'], 'sbp_customer_period_index');
            $table->index(['customer_id', 'location_id', 'period_start'], 'sbp_customer_location_period_index');
            $table->index(['customer_id', 'status'], 'sbp_customer_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('storage_billing_periods');
    }
};
