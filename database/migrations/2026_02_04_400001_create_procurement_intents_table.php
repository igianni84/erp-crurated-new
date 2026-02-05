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
        Schema::create('procurement_intents', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Polymorphic product reference (bottle_sku/sellable_sku OR liquid_product)
            $table->string('product_reference_type');
            $table->uuid('product_reference_id');

            // Quantity in bottles or bottle-equivalents
            $table->unsignedInteger('quantity');

            // Enumerated fields
            $table->enum('trigger_type', [
                'voucher_driven',
                'allocation_driven',
                'strategic',
                'contractual',
            ]);
            $table->enum('sourcing_model', [
                'purchase',
                'passive_consignment',
                'third_party_custody',
            ]);
            $table->enum('status', [
                'draft',
                'approved',
                'executed',
                'closed',
            ])->default('draft');

            // Optional location preference
            $table->string('preferred_inbound_location')->nullable();

            // Optional rationale/notes
            $table->text('rationale')->nullable();

            // Approval tracking
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

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
            $table->index(['product_reference_type', 'product_reference_id'], 'pi_product_ref_idx');
            $table->index(['status', 'trigger_type'], 'pi_status_trigger_idx');
            $table->index('approved_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('procurement_intents');
    }
};
