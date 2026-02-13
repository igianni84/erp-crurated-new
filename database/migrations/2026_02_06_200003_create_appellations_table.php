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
        Schema::create('appellations', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('name');
            $table->foreignUuid('country_id')->constrained('countries');
            $table->foreignUuid('region_id')->nullable()->constrained('regions')->nullOnDelete();
            $table->string('system');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->nullable();

            // Audit fields
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['name', 'country_id']);
            $table->index(['country_id', 'is_active']);
            $table->index(['region_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appellations');
    }
};
