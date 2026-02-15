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
        Schema::create('product_media', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('wine_variant_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['image', 'document'])->default('image');
            $table->enum('source', ['liv_ex', 'manual'])->default('manual');
            $table->string('file_path')->nullable(); // For manual uploads
            $table->string('external_url')->nullable(); // For Liv-ex URLs
            $table->string('original_filename')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedInteger('file_size')->nullable(); // in bytes
            $table->string('alt_text')->nullable();
            $table->text('caption')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_locked')->default(false); // true for Liv-ex media
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['wine_variant_id', 'type']);
            $table->index(['wine_variant_id', 'source']);
            $table->index(['wine_variant_id', 'is_primary']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_media');
    }
};
