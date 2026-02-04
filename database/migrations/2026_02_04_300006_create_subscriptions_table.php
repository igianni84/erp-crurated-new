<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            // Primary key
            $table->uuid('id')->primary();

            // Customer relationship
            $table->foreignUuid('customer_id')
                ->constrained('customers')
                ->cascadeOnDelete();

            // Plan details
            $table->string('plan_type'); // enum: membership, service
            $table->string('plan_name');
            $table->string('billing_cycle'); // enum: monthly, quarterly, annual

            // Amount and currency
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('EUR');

            // Status
            $table->string('status'); // enum: active, suspended, cancelled

            // Billing dates
            $table->date('started_at');
            $table->date('next_billing_date');
            $table->date('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();

            // Stripe integration
            $table->string('stripe_subscription_id')->nullable()->unique();

            // Metadata for additional subscription info
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
            $table->index('plan_type');
            $table->index('billing_cycle');
            $table->index('status');
            $table->index('started_at');
            $table->index('next_billing_date');
            $table->index(['customer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
