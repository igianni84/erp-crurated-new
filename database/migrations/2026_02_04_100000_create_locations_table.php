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
        Schema::create('locations', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Location identification
            $table->string('name')->unique();
            $table->enum('location_type', [
                'main_warehouse',
                'satellite_warehouse',
                'consignee',
                'third_party_storage',
                'event_location',
            ]);
            $table->string('country');
            $table->text('address')->nullable();

            // Serialization authorization
            $table->boolean('serialization_authorized')->default(false);

            // WMS integration
            $table->string('linked_wms_id')->nullable();

            // Status
            $table->enum('status', ['active', 'inactive', 'suspended'])
                ->default('active');

            // Notes
            $table->text('notes')->nullable();

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
            $table->index(['location_type', 'status']);
            $table->index('country');
            $table->index('serialization_authorized');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('locations');
    }
};
