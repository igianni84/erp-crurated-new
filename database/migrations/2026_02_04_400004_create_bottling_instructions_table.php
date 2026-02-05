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
        Schema::create('bottling_instructions', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Required link to Procurement Intent
            $table->foreignUuid('procurement_intent_id')
                ->constrained('procurement_intents')
                ->cascadeOnDelete();

            // Required link to Liquid Product
            $table->foreignUuid('liquid_product_id')
                ->constrained('liquid_products')
                ->cascadeOnDelete();

            // Quantity in bottle-equivalents
            $table->unsignedInteger('bottle_equivalents');

            // Bottling configuration
            $table->json('allowed_formats');
            $table->json('allowed_case_configurations');
            $table->text('default_bottling_rule')->nullable();

            // Deadline (required, non-nullable)
            $table->date('bottling_deadline');

            // Preference tracking
            $table->enum('preference_status', [
                'pending',
                'partial',
                'complete',
                'defaulted',
            ])->default('pending');

            // Personalisation flags
            $table->boolean('personalised_bottling_required')->default(false);
            $table->boolean('early_binding_required')->default(false);

            // Delivery location
            $table->string('delivery_location')->nullable();

            // Lifecycle status
            $table->enum('status', [
                'draft',
                'active',
                'executed',
            ])->default('draft');

            // Defaults application tracking
            $table->timestamp('defaults_applied_at')->nullable();

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

            // Indexes for common queries
            $table->index('procurement_intent_id');
            $table->index('liquid_product_id');
            $table->index(['status', 'bottling_deadline']);
            $table->index('preference_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bottling_instructions');
    }
};
