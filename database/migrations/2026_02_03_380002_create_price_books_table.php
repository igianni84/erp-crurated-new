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
        Schema::create('price_books', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Price Book identification
            $table->string('name');

            // Market scope
            $table->string('market');

            // Optional channel association
            $table->foreignUuid('channel_id')
                ->nullable()
                ->constrained('channels')
                ->nullOnDelete();

            // Currency for all prices in this book
            $table->string('currency', 3);

            // Validity period
            $table->date('valid_from');
            $table->date('valid_to')->nullable();

            // Status
            $table->enum('status', ['draft', 'active', 'expired', 'archived'])->default('draft');

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

            // Index for common queries - finding active price books for a context
            $table->index(['market', 'channel_id', 'currency', 'status']);
            $table->index(['status', 'valid_from', 'valid_to']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('price_books');
    }
};
