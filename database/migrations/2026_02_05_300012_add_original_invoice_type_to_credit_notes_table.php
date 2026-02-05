<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds original_invoice_type field to credit_notes table.
     * This field preserves the invoice type from the original invoice
     * at the time the credit note is created, ensuring it cannot be
     * changed and enabling reporting by original invoice type.
     */
    public function up(): void
    {
        Schema::table('credit_notes', function (Blueprint $table) {
            $table->string('original_invoice_type')->nullable()->after('customer_id');
            $table->index('original_invoice_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('credit_notes', function (Blueprint $table) {
            $table->dropIndex(['original_invoice_type']);
            $table->dropColumn('original_invoice_type');
        });
    }
};
