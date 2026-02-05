<?php

use App\Enums\Commercial\OfferStatus;
use App\Enums\Commercial\OfferType;
use App\Enums\Commercial\OfferVisibility;
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
        Schema::create('offers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->foreignUuid('sellable_sku_id')
                ->constrained('sellable_skus')
                ->cascadeOnDelete();
            $table->foreignUuid('channel_id')
                ->constrained('channels')
                ->cascadeOnDelete();
            $table->foreignUuid('price_book_id')
                ->constrained('price_books')
                ->cascadeOnDelete();
            $table->string('offer_type')->default(OfferType::Standard->value);
            $table->string('visibility')->default(OfferVisibility::Public->value);
            $table->dateTime('valid_from');
            $table->dateTime('valid_to')->nullable();
            $table->string('status')->default(OfferStatus::Draft->value);
            $table->string('campaign_tag')->nullable();

            // Audit fields
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            // Indexes for common queries
            $table->index(['sellable_sku_id', 'channel_id', 'status']);
            $table->index(['status', 'valid_from', 'valid_to']);
            $table->index('campaign_tag');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('offers');
    }
};
