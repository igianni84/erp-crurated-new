<?php

use App\Enums\Finance\XeroSyncStatus;
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
        Schema::create('xero_sync_logs', function (Blueprint $table) {
            $table->id();

            // Sync type (invoice, credit_note, payment)
            $table->string('sync_type');

            // Polymorphic relationship to the syncable entity
            $table->string('syncable_type');
            $table->unsignedBigInteger('syncable_id');

            // Xero reference ID (after successful sync)
            $table->string('xero_id')->nullable();

            // Sync status
            $table->string('status')->default(XeroSyncStatus::Pending->value);

            // Request/response payloads for debugging
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();

            // Error handling
            $table->text('error_message')->nullable();

            // Timestamps
            $table->timestamp('synced_at')->nullable();

            // Retry tracking
            $table->unsignedInteger('retry_count')->default(0);

            // Only created_at, no updated_at (logs are append-only)
            $table->timestamp('created_at')->nullable();

            // Indexes for querying
            $table->index(['syncable_type', 'syncable_id']);
            $table->index('sync_type');
            $table->index('status');
            $table->index('synced_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('xero_sync_logs');
    }
};
