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
        // Attribute Sets - groups of attributes for different product categories
        Schema::create('attribute_sets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name'); // e.g., "Wine Attributes", "Champagne Attributes"
            $table->string('code')->unique(); // e.g., "wine_default", "champagne"
            $table->text('description')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        // Attribute Groups - sections within an attribute set
        Schema::create('attribute_groups', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('attribute_set_id')
                ->constrained('attribute_sets')
                ->cascadeOnDelete();
            $table->string('name'); // e.g., "Wine Info", "Compliance", "Production"
            $table->string('code'); // e.g., "wine_info", "compliance"
            $table->string('icon')->nullable(); // heroicon name
            $table->text('description')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_collapsible')->default(true);
            $table->boolean('is_collapsed_by_default')->default(false);
            $table->timestamps();

            $table->unique(['attribute_set_id', 'code'], 'attr_group_set_code_unique');
        });

        // Attribute Definitions - the actual attribute definitions
        Schema::create('attribute_definitions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('attribute_group_id')
                ->constrained('attribute_groups')
                ->cascadeOnDelete();
            $table->string('name'); // e.g., "Alcohol Percentage"
            $table->string('code')->unique(); // e.g., "alcohol_percentage"
            $table->enum('type', ['text', 'textarea', 'number', 'select', 'multiselect', 'boolean', 'date', 'json']); // field type
            $table->json('options')->nullable(); // for select/multiselect: array of options
            $table->json('validation_rules')->nullable(); // e.g., {"min": 0, "max": 100, "step": 0.1}
            $table->boolean('is_required')->default(false);
            $table->boolean('is_lockable_from_livex')->default(false); // can be locked from Liv-ex import
            $table->integer('completeness_weight')->default(1); // weight for completeness calculation
            $table->string('unit')->nullable(); // e.g., "%", "ml", "years"
            $table->text('help_text')->nullable(); // help text for the field
            $table->string('placeholder')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // Attribute Values - stores the actual values for wine variants
        Schema::create('attribute_values', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('wine_variant_id')
                ->constrained('wine_variants')
                ->cascadeOnDelete();
            $table->foreignUuid('attribute_definition_id')
                ->constrained('attribute_definitions')
                ->cascadeOnDelete();
            $table->text('value')->nullable(); // the actual value (stored as text, cast based on type)
            $table->enum('source', ['manual', 'liv_ex'])->default('manual');
            $table->boolean('is_locked')->default(false); // locked from Liv-ex
            $table->timestamps();

            $table->unique(['wine_variant_id', 'attribute_definition_id'], 'attr_value_variant_def_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attribute_values');
        Schema::dropIfExists('attribute_definitions');
        Schema::dropIfExists('attribute_groups');
        Schema::dropIfExists('attribute_sets');
    }
};
