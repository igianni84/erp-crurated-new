<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * US-E104: Add xero_sync_pending flag to invoices table.
 *
 * This flag indicates that an issued invoice needs to be synced to Xero.
 * When sync fails, the invoice remains issued but is flagged as sync_pending.
 *
 * Invariant: Every issued invoice must have a XeroSyncLog entry.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // Flag to indicate the invoice has pending Xero sync
            // Set to true when invoice is issued, false when sync completes
            $table->boolean('xero_sync_pending')->default(false)->after('xero_synced_at');

            // Index for efficient query of invoices pending sync
            $table->index('xero_sync_pending');

            // Composite index for finding issued invoices without Xero ID
            $table->index(['status', 'xero_invoice_id'], 'invoices_xero_sync_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex('invoices_xero_sync_status_idx');
            $table->dropIndex(['xero_sync_pending']);
            $table->dropColumn('xero_sync_pending');
        });
    }
};
