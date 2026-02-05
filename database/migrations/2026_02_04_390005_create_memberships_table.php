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
        Schema::create('memberships', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('customer_id')
                ->constrained('customers')
                ->cascadeOnDelete();
            $table->string('tier'); // legacy, member, invitation_only
            $table->string('status'); // applied, under_review, approved, rejected, suspended
            $table->timestamp('effective_from')->nullable();
            $table->timestamp('effective_to')->nullable();
            $table->text('decision_notes')->nullable();
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();

            // Index for customer lookups
            $table->index('customer_id');
            // Index for status filtering
            $table->index('status');
            // Index for tier filtering
            $table->index('tier');
            // Index for effective date queries
            $table->index(['effective_from', 'effective_to']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('memberships');
    }
};
