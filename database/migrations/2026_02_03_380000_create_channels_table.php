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
        Schema::create('channels', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Channel identification
            $table->string('name');

            // Channel type
            $table->enum('channel_type', ['b2c', 'b2b', 'private_club']);

            // Currency settings
            $table->string('default_currency', 3);

            // Commercial models allowed for this channel
            $table->json('allowed_commercial_models');

            // Status
            $table->enum('status', ['active', 'inactive'])->default('active');

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

            // Index for common queries
            $table->index(['channel_type', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('channels');
    }
};
