<?php

use App\Enums\Customer\PartyStatus;
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
        Schema::create('parties', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('legal_name');
            $table->string('party_type'); // individual, legal_entity
            $table->string('tax_id')->nullable();
            $table->string('vat_number')->nullable();
            $table->string('jurisdiction')->nullable();
            $table->string('status')->default(PartyStatus::Active->value);
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

            // Unique constraint: tax_id must be unique within a jurisdiction
            $table->unique(['tax_id', 'jurisdiction'], 'parties_tax_id_jurisdiction_unique');

            // Indexes for common queries
            $table->index('party_type');
            $table->index('status');
            $table->index('jurisdiction');
            $table->index('legal_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('parties');
    }
};
