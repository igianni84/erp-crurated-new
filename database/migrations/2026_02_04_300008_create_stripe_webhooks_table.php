<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stripe_webhooks', function (Blueprint $table) {
            // Primary key (standard auto-increment, not UUID)
            $table->id();

            // Stripe event identification (unique for idempotency)
            $table->string('event_id')->unique();

            // Event type (e.g., payment_intent.succeeded, charge.refunded)
            $table->string('event_type');

            // Complete webhook payload
            $table->json('payload');

            // Processing status
            $table->boolean('processed')->default(false);
            $table->timestamp('processed_at')->nullable();

            // Error tracking
            $table->text('error_message')->nullable();

            // Created timestamp only (no updated_at, no soft deletes - logs are immutable)
            $table->timestamp('created_at')->nullable();

            // Indexes
            $table->index('event_type');
            $table->index('processed');
            $table->index('created_at');
            $table->index(['processed', 'event_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stripe_webhooks');
    }
};
