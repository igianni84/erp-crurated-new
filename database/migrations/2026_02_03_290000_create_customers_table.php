<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Placeholder customers table for Module A (Allocations/Vouchers).
 * This will be enhanced by Module K (Parties, Customers & Eligibility) implementation.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Basic identity - will be expanded by Module K
            $table->string('name');
            $table->string('email')->unique();

            // Status
            $table->enum('status', ['active', 'suspended', 'closed'])
                ->default('active');

            $table->timestamps();
            $table->softDeletes();

            // Index for common queries
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
