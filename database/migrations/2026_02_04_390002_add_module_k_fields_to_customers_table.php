<?php

use App\Enums\Customer\CustomerType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Add Module K fields to the customers table.
 * Enhances the placeholder customers table with Party relationship and new fields.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            // Add FK to parties table (nullable for existing records)
            $table->foreignUuid('party_id')
                ->nullable()
                ->after('id')
                ->constrained('parties')
                ->cascadeOnDelete();

            // Add customer_type field
            $table->string('customer_type')
                ->default(CustomerType::B2C->value)
                ->after('email');

            // Add nullable FK for default billing address (will reference addresses table when created)
            $table->uuid('default_billing_address_id')
                ->nullable()
                ->after('customer_type');

            // Add audit fields
            $table->foreignId('created_by')
                ->nullable()
                ->after('default_billing_address_id')
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('updated_by')
                ->nullable()
                ->after('created_by')
                ->constrained('users')
                ->nullOnDelete();

            // Index for party relationship
            $table->index('party_id');
            $table->index('customer_type');
        });

        // Modify status column to include 'prospect' value
        // For SQLite compatibility (used in tests), we recreate the column
        // For MySQL/PostgreSQL, we would use ALTER COLUMN
        if (DB::connection()->getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE customers MODIFY COLUMN status ENUM('prospect', 'active', 'suspended', 'closed') DEFAULT 'active'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropForeign(['party_id']);
            $table->dropForeign(['created_by']);
            $table->dropForeign(['updated_by']);
            $table->dropIndex(['party_id']);
            $table->dropIndex(['customer_type']);
            $table->dropColumn([
                'party_id',
                'customer_type',
                'default_billing_address_id',
                'created_by',
                'updated_by',
            ]);
        });

        // Restore original status enum
        if (DB::connection()->getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE customers MODIFY COLUMN status ENUM('active', 'suspended', 'closed') DEFAULT 'active'");
        }
    }
};
