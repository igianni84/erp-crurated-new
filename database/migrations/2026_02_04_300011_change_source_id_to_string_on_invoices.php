<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Change source_id from unsignedBigInteger to string to support UUID references.
 *
 * This change is necessary because some source models (like Subscription)
 * use UUIDs as primary keys, not auto-incrementing integers.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            // Drop the unique constraint first
            $table->dropUnique('invoices_source_unique');
            // Drop the index
            $table->dropIndex(['source_type', 'source_id']);
        });

        Schema::table('invoices', function (Blueprint $table): void {
            // Change column type from unsignedBigInteger to string
            $table->string('source_id', 36)->nullable()->change();
        });

        Schema::table('invoices', function (Blueprint $table): void {
            // Re-add the index and unique constraint
            $table->index(['source_type', 'source_id']);
            $table->unique(['source_type', 'source_id'], 'invoices_source_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            // Drop the unique constraint first
            $table->dropUnique('invoices_source_unique');
            // Drop the index
            $table->dropIndex(['source_type', 'source_id']);
        });

        Schema::table('invoices', function (Blueprint $table): void {
            // Change back to unsignedBigInteger
            $table->unsignedBigInteger('source_id')->nullable()->change();
        });

        Schema::table('invoices', function (Blueprint $table): void {
            // Re-add the index and unique constraint
            $table->index(['source_type', 'source_id']);
            $table->unique(['source_type', 'source_id'], 'invoices_source_unique');
        });
    }
};
