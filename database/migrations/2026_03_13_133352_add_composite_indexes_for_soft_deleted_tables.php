<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Composite indexes for soft-deleted tables to prevent scanning
     * deleted records when filtering by status/lifecycle_state.
     */
    public function up(): void
    {
        Schema::table('vouchers', function (Blueprint $table): void {
            $table->index(['lifecycle_state', 'deleted_at'], 'vouchers_lifecycle_state_deleted_at_index');
        });

        Schema::table('invoices', function (Blueprint $table): void {
            $table->index(['status', 'deleted_at'], 'invoices_status_deleted_at_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vouchers', function (Blueprint $table): void {
            $table->dropIndex('vouchers_lifecycle_state_deleted_at_index');
        });

        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropIndex('invoices_status_deleted_at_index');
        });
    }
};
