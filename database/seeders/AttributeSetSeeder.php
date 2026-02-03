<?php

namespace Database\Seeders;

use App\Models\Pim\AttributeDefinition;
use App\Models\Pim\AttributeGroup;
use App\Models\Pim\AttributeSet;
use Illuminate\Database\Seeder;

class AttributeSetSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create default wine attribute set
        $defaultSet = AttributeSet::firstOrCreate(
            ['code' => 'wine_default'],
            [
                'name' => 'Wine Attributes',
                'description' => 'Default attribute set for wine products',
                'sort_order' => 1,
                'is_default' => true,
            ]
        );

        // Wine Info group
        $wineInfoGroup = AttributeGroup::firstOrCreate(
            ['attribute_set_id' => $defaultSet->id, 'code' => 'wine_info'],
            [
                'name' => 'Wine Information',
                'icon' => 'heroicon-o-beaker',
                'description' => 'Technical wine details',
                'sort_order' => 1,
                'is_collapsible' => true,
                'is_collapsed_by_default' => false,
            ]
        );

        // Production group
        $productionGroup = AttributeGroup::firstOrCreate(
            ['attribute_set_id' => $defaultSet->id, 'code' => 'production'],
            [
                'name' => 'Production Details',
                'icon' => 'heroicon-o-cog-6-tooth',
                'description' => 'Winemaking and production information',
                'sort_order' => 2,
                'is_collapsible' => true,
                'is_collapsed_by_default' => false,
            ]
        );

        // Tasting Notes group
        $tastingGroup = AttributeGroup::firstOrCreate(
            ['attribute_set_id' => $defaultSet->id, 'code' => 'tasting'],
            [
                'name' => 'Tasting Notes',
                'icon' => 'heroicon-o-sparkles',
                'description' => 'Sensory characteristics and tasting notes',
                'sort_order' => 3,
                'is_collapsible' => true,
                'is_collapsed_by_default' => true,
            ]
        );

        // Compliance group
        $complianceGroup = AttributeGroup::firstOrCreate(
            ['attribute_set_id' => $defaultSet->id, 'code' => 'compliance'],
            [
                'name' => 'Compliance & Certifications',
                'icon' => 'heroicon-o-shield-check',
                'description' => 'Regulatory compliance and certifications',
                'sort_order' => 4,
                'is_collapsible' => true,
                'is_collapsed_by_default' => true,
            ]
        );

        // Wine Info attributes
        $this->createAttribute($wineInfoGroup, [
            'name' => 'Grape Varieties',
            'code' => 'grape_varieties',
            'type' => 'textarea',
            'is_required' => false,
            'is_lockable_from_livex' => true,
            'completeness_weight' => 8,
            'help_text' => 'List the grape varieties used in this wine',
            'placeholder' => 'e.g., 60% Cabernet Sauvignon, 40% Merlot',
            'sort_order' => 1,
        ]);

        $this->createAttribute($wineInfoGroup, [
            'name' => 'Color',
            'code' => 'color',
            'type' => 'select',
            'options' => ['Red', 'White', 'Rosé', 'Orange', 'Sparkling'],
            'is_required' => false,
            'is_lockable_from_livex' => true,
            'completeness_weight' => 5,
            'sort_order' => 2,
        ]);

        $this->createAttribute($wineInfoGroup, [
            'name' => 'Wine Style',
            'code' => 'wine_style',
            'type' => 'select',
            'options' => ['Still', 'Sparkling', 'Fortified', 'Dessert', 'Natural'],
            'is_required' => false,
            'is_lockable_from_livex' => true,
            'completeness_weight' => 5,
            'sort_order' => 3,
        ]);

        $this->createAttribute($wineInfoGroup, [
            'name' => 'Residual Sugar',
            'code' => 'residual_sugar',
            'type' => 'number',
            'validation_rules' => ['min' => 0, 'max' => 500, 'step' => 0.1],
            'unit' => 'g/L',
            'is_required' => false,
            'completeness_weight' => 3,
            'sort_order' => 4,
        ]);

        $this->createAttribute($wineInfoGroup, [
            'name' => 'Total Acidity',
            'code' => 'total_acidity',
            'type' => 'number',
            'validation_rules' => ['min' => 0, 'max' => 20, 'step' => 0.1],
            'unit' => 'g/L',
            'is_required' => false,
            'completeness_weight' => 3,
            'sort_order' => 5,
        ]);

        $this->createAttribute($wineInfoGroup, [
            'name' => 'pH Level',
            'code' => 'ph_level',
            'type' => 'number',
            'validation_rules' => ['min' => 2.5, 'max' => 4.5, 'step' => 0.01],
            'is_required' => false,
            'completeness_weight' => 2,
            'sort_order' => 6,
        ]);

        // Production attributes
        $this->createAttribute($productionGroup, [
            'name' => 'Vineyard',
            'code' => 'vineyard',
            'type' => 'text',
            'is_required' => false,
            'is_lockable_from_livex' => true,
            'completeness_weight' => 5,
            'placeholder' => 'Vineyard name or location',
            'sort_order' => 1,
        ]);

        $this->createAttribute($productionGroup, [
            'name' => 'Soil Type',
            'code' => 'soil_type',
            'type' => 'text',
            'is_required' => false,
            'completeness_weight' => 3,
            'placeholder' => 'e.g., Limestone, Clay, Gravel',
            'sort_order' => 2,
        ]);

        $this->createAttribute($productionGroup, [
            'name' => 'Vine Age',
            'code' => 'vine_age',
            'type' => 'text',
            'is_required' => false,
            'completeness_weight' => 3,
            'placeholder' => 'e.g., 30-50 years',
            'sort_order' => 3,
        ]);

        $this->createAttribute($productionGroup, [
            'name' => 'Yield',
            'code' => 'yield',
            'type' => 'text',
            'is_required' => false,
            'completeness_weight' => 2,
            'placeholder' => 'e.g., 35 hl/ha',
            'sort_order' => 4,
        ]);

        $this->createAttribute($productionGroup, [
            'name' => 'Fermentation',
            'code' => 'fermentation',
            'type' => 'textarea',
            'is_required' => false,
            'completeness_weight' => 3,
            'help_text' => 'Describe the fermentation process',
            'sort_order' => 5,
        ]);

        $this->createAttribute($productionGroup, [
            'name' => 'Aging',
            'code' => 'aging',
            'type' => 'textarea',
            'is_required' => false,
            'is_lockable_from_livex' => true,
            'completeness_weight' => 5,
            'help_text' => 'Describe the aging process (oak, duration, etc.)',
            'sort_order' => 6,
        ]);

        $this->createAttribute($productionGroup, [
            'name' => 'Cases Produced',
            'code' => 'cases_produced',
            'type' => 'number',
            'validation_rules' => ['min' => 0],
            'is_required' => false,
            'is_lockable_from_livex' => true,
            'completeness_weight' => 3,
            'sort_order' => 7,
        ]);

        // Tasting Notes attributes
        $this->createAttribute($tastingGroup, [
            'name' => 'Aroma',
            'code' => 'aroma',
            'type' => 'textarea',
            'is_required' => false,
            'completeness_weight' => 4,
            'help_text' => 'Describe the wine\'s aromatic profile',
            'sort_order' => 1,
        ]);

        $this->createAttribute($tastingGroup, [
            'name' => 'Palate',
            'code' => 'palate',
            'type' => 'textarea',
            'is_required' => false,
            'completeness_weight' => 4,
            'help_text' => 'Describe the taste experience',
            'sort_order' => 2,
        ]);

        $this->createAttribute($tastingGroup, [
            'name' => 'Finish',
            'code' => 'finish',
            'type' => 'textarea',
            'is_required' => false,
            'completeness_weight' => 3,
            'help_text' => 'Describe the wine\'s finish',
            'sort_order' => 3,
        ]);

        $this->createAttribute($tastingGroup, [
            'name' => 'Food Pairing',
            'code' => 'food_pairing',
            'type' => 'textarea',
            'is_required' => false,
            'completeness_weight' => 3,
            'help_text' => 'Recommended food pairings',
            'sort_order' => 4,
        ]);

        $this->createAttribute($tastingGroup, [
            'name' => 'Serving Temperature',
            'code' => 'serving_temperature',
            'type' => 'text',
            'is_required' => false,
            'completeness_weight' => 2,
            'placeholder' => 'e.g., 16-18°C',
            'sort_order' => 5,
        ]);

        // Compliance attributes
        $this->createAttribute($complianceGroup, [
            'name' => 'Organic Certified',
            'code' => 'organic_certified',
            'type' => 'boolean',
            'is_required' => false,
            'completeness_weight' => 2,
            'help_text' => 'Is this wine certified organic?',
            'sort_order' => 1,
        ]);

        $this->createAttribute($complianceGroup, [
            'name' => 'Biodynamic Certified',
            'code' => 'biodynamic_certified',
            'type' => 'boolean',
            'is_required' => false,
            'completeness_weight' => 2,
            'help_text' => 'Is this wine certified biodynamic?',
            'sort_order' => 2,
        ]);

        $this->createAttribute($complianceGroup, [
            'name' => 'Vegan',
            'code' => 'vegan',
            'type' => 'boolean',
            'is_required' => false,
            'completeness_weight' => 2,
            'help_text' => 'Is this wine suitable for vegans?',
            'sort_order' => 3,
        ]);

        $this->createAttribute($complianceGroup, [
            'name' => 'Contains Sulfites',
            'code' => 'contains_sulfites',
            'type' => 'boolean',
            'is_required' => false,
            'completeness_weight' => 2,
            'help_text' => 'Does this wine contain sulfites?',
            'sort_order' => 4,
        ]);

        $this->createAttribute($complianceGroup, [
            'name' => 'Allergens',
            'code' => 'allergens',
            'type' => 'multiselect',
            'options' => ['Sulfites', 'Milk', 'Eggs', 'Fish', 'None'],
            'is_required' => false,
            'completeness_weight' => 3,
            'sort_order' => 5,
        ]);

        $this->createAttribute($complianceGroup, [
            'name' => 'Certifications',
            'code' => 'certifications',
            'type' => 'multiselect',
            'options' => ['EU Organic', 'USDA Organic', 'Demeter', 'Ecocert', 'Natural Wine', 'Other'],
            'is_required' => false,
            'completeness_weight' => 3,
            'sort_order' => 6,
        ]);
    }

    /**
     * Helper to create an attribute definition.
     *
     * @param  array<string, mixed>  $data
     */
    private function createAttribute(AttributeGroup $group, array $data): void
    {
        AttributeDefinition::firstOrCreate(
            ['code' => $data['code']],
            array_merge(['attribute_group_id' => $group->id], $data)
        );
    }
}
