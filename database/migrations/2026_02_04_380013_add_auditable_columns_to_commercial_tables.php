<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add auditable columns (created_by, updated_by) to commercial entity tables
 * that are missing them for full audit trail support (US-060).
 *
 * Tables affected:
 * - pricing_policies (missing columns despite having Auditable trait)
 * - price_book_entries
 * - offer_eligibilities
 * - offer_benefits
 * - discount_rules
 * - bundles
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add to pricing_policies (has trait but missing columns)
        Schema::table('pricing_policies', function (Blueprint $table) {
            $table->foreignId('created_by')->nullable()->after('last_executed_at')->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
        });

        // Add to price_book_entries
        Schema::table('price_book_entries', function (Blueprint $table) {
            $table->foreignId('created_by')->nullable()->after('policy_id')->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
        });

        // Add to offer_eligibilities
        Schema::table('offer_eligibilities', function (Blueprint $table) {
            $table->foreignId('created_by')->nullable()->after('allocation_constraint_id')->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
        });

        // Add to offer_benefits
        Schema::table('offer_benefits', function (Blueprint $table) {
            $table->foreignId('created_by')->nullable()->after('discount_rule_id')->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
        });

        // Add to discount_rules
        Schema::table('discount_rules', function (Blueprint $table) {
            $table->foreignId('created_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
        });

        // Add to bundles
        Schema::table('bundles', function (Blueprint $table) {
            $table->foreignId('created_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pricing_policies', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropForeign(['updated_by']);
            $table->dropColumn(['created_by', 'updated_by']);
        });

        Schema::table('price_book_entries', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropForeign(['updated_by']);
            $table->dropColumn(['created_by', 'updated_by']);
        });

        Schema::table('offer_eligibilities', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropForeign(['updated_by']);
            $table->dropColumn(['created_by', 'updated_by']);
        });

        Schema::table('offer_benefits', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropForeign(['updated_by']);
            $table->dropColumn(['created_by', 'updated_by']);
        });

        Schema::table('discount_rules', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropForeign(['updated_by']);
            $table->dropColumn(['created_by', 'updated_by']);
        });

        Schema::table('bundles', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropForeign(['updated_by']);
            $table->dropColumn(['created_by', 'updated_by']);
        });
    }
};
