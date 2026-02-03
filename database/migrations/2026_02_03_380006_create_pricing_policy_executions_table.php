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
        Schema::create('pricing_policy_executions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('pricing_policy_id')
                ->constrained('pricing_policies')
                ->cascadeOnDelete();
            $table->timestamp('executed_at');
            $table->string('execution_type'); // manual, scheduled, dry_run
            $table->unsignedInteger('skus_processed')->default(0);
            $table->unsignedInteger('prices_generated')->default(0);
            $table->unsignedInteger('errors_count')->default(0);
            $table->string('status'); // success, partial, failed
            $table->text('log_summary')->nullable();
            $table->timestamps();

            // Index for efficient querying by policy
            $table->index('pricing_policy_id');
            // Index for querying by execution type
            $table->index('execution_type');
            // Index for querying by status
            $table->index('status');
            // Index for ordering by execution date
            $table->index('executed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pricing_policy_executions');
    }
};
