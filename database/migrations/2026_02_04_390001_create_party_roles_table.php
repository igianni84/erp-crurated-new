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
        Schema::create('party_roles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('party_id')
                ->constrained('parties')
                ->cascadeOnDelete();
            $table->string('role'); // customer, supplier, producer, partner
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();

            // Unique constraint: a party can only have one instance of each role
            $table->unique(['party_id', 'role'], 'party_roles_party_id_role_unique');

            // Index for role lookups
            $table->index('role');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('party_roles');
    }
};
