<?php

namespace Tests\Support;

use App\Models\Customer\Customer;
use App\Models\Customer\Party;
use App\Models\Pim\CaseConfiguration;
use App\Models\Pim\Format;
use App\Models\Pim\SellableSku;
use App\Models\Pim\WineMaster;
use App\Models\Pim\WineVariant;
use App\Models\User;

trait FilamentTestHelpers
{
    protected User $superAdmin;

    protected User $viewer;

    protected function setUpFilamentTestHelpers(): void
    {
        $this->superAdmin = User::factory()->superAdmin()->create();
        $this->viewer = User::factory()->viewer()->create();
    }

    protected function actingAsSuperAdmin(): static
    {
        $this->actingAs($this->superAdmin);

        return $this;
    }

    protected function actingAsViewer(): static
    {
        $this->actingAs($this->viewer);

        return $this;
    }

    /**
     * Create a full PIM stack: WineMaster → WineVariant → Format → CaseConfig → SellableSku.
     *
     * @return array{wine_master: WineMaster, wine_variant: WineVariant, format: Format, case_configuration: CaseConfiguration, sellable_sku: SellableSku}
     */
    protected function createPimStack(): array
    {
        $wineMaster = WineMaster::factory()->create();
        $wineVariant = WineVariant::factory()->for($wineMaster, 'wineMaster')->create();
        $format = Format::factory()->standard()->create();
        $caseConfiguration = CaseConfiguration::factory()->create(['format_id' => $format->id]);
        $sellableSku = SellableSku::factory()->create([
            'wine_variant_id' => $wineVariant->id,
            'format_id' => $format->id,
            'case_configuration_id' => $caseConfiguration->id,
        ]);

        return [
            'wine_master' => $wineMaster,
            'wine_variant' => $wineVariant,
            'format' => $format,
            'case_configuration' => $caseConfiguration,
            'sellable_sku' => $sellableSku,
        ];
    }

    /**
     * Create a Customer stack: Party → Customer.
     *
     * @return array{party: Party, customer: Customer}
     */
    protected function createCustomerStack(): array
    {
        $party = Party::factory()->create();
        $customer = Customer::factory()->for($party)->create();

        return [
            'party' => $party,
            'customer' => $customer,
        ];
    }
}
