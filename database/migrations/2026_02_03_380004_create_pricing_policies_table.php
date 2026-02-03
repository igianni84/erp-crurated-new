<?php

use App\Enums\Commercial\PricingPolicyStatus;
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
        Schema::create('pricing_policies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('policy_type');
            $table->string('input_source');
            $table->foreignUuid('target_price_book_id')
                ->nullable()
                ->constrained('price_books')
                ->nullOnDelete();
            $table->json('logic_definition');
            $table->string('execution_cadence');
            $table->string('status')->default(PricingPolicyStatus::Draft->value);
            $table->timestamp('last_executed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Index for finding policies by status and target
            $table->index(['status', 'target_price_book_id']);
            // Index for finding scheduled policies
            $table->index(['status', 'execution_cadence']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pricing_policies');
    }
};
