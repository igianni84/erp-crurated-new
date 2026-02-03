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
        Schema::create('wine_masters', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Core identity fields
            $table->string('name');
            $table->string('producer');
            $table->string('appellation')->nullable();
            $table->string('classification')->nullable();
            $table->string('country');
            $table->string('region')->nullable();
            $table->text('description')->nullable();

            // Optional fields
            $table->string('liv_ex_code')->nullable()->unique();
            $table->json('regulatory_attributes')->nullable();

            // Audit fields
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wine_masters');
    }
};
