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
        Schema::create('operational_blocks', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Polymorphic relationship to Customer or Account
            $table->string('blockable_type');
            $table->uuid('blockable_id');

            // Block details
            $table->string('block_type');
            $table->text('reason');
            $table->foreignId('applied_by')->nullable()->constrained('users')->nullOnDelete();

            // Block status
            $table->string('status')->default('active');
            $table->timestamp('removed_at')->nullable();
            $table->foreignId('removed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('removal_reason')->nullable();

            // Audit fields
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Indexes for common queries
            $table->index(['blockable_type', 'blockable_id']);
            $table->index('block_type');
            $table->index('status');
            $table->index('applied_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('operational_blocks');
    }
};
