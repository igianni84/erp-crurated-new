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
        Schema::create('shipping_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Customer relationship
            $table->uuid('customer_id');
            $table->foreign('customer_id')
                ->references('id')
                ->on('customers')
                ->restrictOnDelete();

            // Destination address (nullable, will be FK to addresses table when Module K implemented)
            // For now, storing as nullable UUID for future compatibility
            $table->uuid('destination_address_id')->nullable();

            // Source warehouse (location)
            $table->uuid('source_warehouse_id')->nullable();
            $table->foreign('source_warehouse_id')
                ->references('id')
                ->on('locations')
                ->nullOnDelete();

            // Status workflow
            $table->enum('status', [
                'draft',
                'planned',
                'picking',
                'shipped',
                'completed',
                'cancelled',
                'on_hold',
            ])->default('draft');

            // Packaging preference
            $table->enum('packaging_preference', [
                'loose',
                'cases',
                'preserve_cases',
            ])->default('loose');

            // Shipping details
            $table->string('shipping_method')->nullable();
            $table->string('carrier')->nullable();
            $table->string('incoterms')->nullable();
            $table->date('requested_ship_date')->nullable();
            $table->text('special_instructions')->nullable();

            // Audit/approval tracking
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('approved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('approved_at')->nullable();

            // For on_hold state machine (to restore previous state)
            $table->enum('previous_status', [
                'draft',
                'planned',
                'picking',
                'shipped',
                'completed',
                'cancelled',
                'on_hold',
            ])->nullable();

            // Updated by for Auditable trait
            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            // Indexes for common queries
            $table->index('status');
            $table->index('customer_id');
            $table->index('source_warehouse_id');
            $table->index('carrier');
            $table->index('requested_ship_date');
            $table->index('created_at');
            $table->index('created_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipping_orders');
    }
};
