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
        Schema::create('case_configurations', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Core fields
            $table->string('name');
            $table->foreignUuid('format_id')->constrained('formats')->cascadeOnDelete();
            $table->integer('bottles_per_case');
            $table->enum('case_type', ['owc', 'oc', 'none']);

            // Boolean flags
            $table->boolean('is_original_from_producer')->default(false);
            $table->boolean('is_breakable')->default(true);

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
        Schema::dropIfExists('case_configurations');
    }
};
