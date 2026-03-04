<?php

namespace Tests\Feature\Filament\Commercial;

use App\Enums\Commercial\BenefitType;
use App\Enums\Commercial\OfferStatus;
use App\Enums\Commercial\OfferType;
use App\Enums\Commercial\OfferVisibility;
use App\Filament\Resources\OfferResource\Pages\CreateOffer;
use App\Filament\Resources\OfferResource\Pages\EditOffer;
use App\Filament\Resources\OfferResource\Pages\ListOffers;
use App\Filament\Resources\OfferResource\Pages\ViewOffer;
use App\Models\Allocation\Allocation;
use App\Models\Commercial\Channel;
use App\Models\Commercial\Offer;
use App\Models\Commercial\PriceBook;
use App\Models\Pim\SellableSku;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\FilamentTestHelpers;
use Tests\TestCase;

class OfferResourceTest extends TestCase
{
    use FilamentTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFilamentTestHelpers();
    }

    // ── List Page ───────────────────────────────────────────────

    public function test_list_page_renders(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(ListOffers::class)
            ->assertSuccessful();
    }

    public function test_list_shows_offers(): void
    {
        $this->actingAsSuperAdmin();

        $offers = Offer::factory()->count(3)->create();

        Livewire::test(ListOffers::class)
            ->assertCanSeeTableRecords($offers);
    }

    public function test_list_can_filter_by_status(): void
    {
        $this->actingAsSuperAdmin();

        $draft = Offer::factory()->create(['status' => OfferStatus::Draft]);
        $active = Offer::factory()->active()->create();

        Livewire::test(ListOffers::class)
            ->filterTable('status', OfferStatus::Draft->value)
            ->assertCanSeeTableRecords([$draft])
            ->assertCanNotSeeTableRecords([$active]);
    }

    public function test_list_can_filter_by_offer_type(): void
    {
        $this->actingAsSuperAdmin();

        $standard = Offer::factory()->create(['offer_type' => OfferType::Standard]);
        $promotion = Offer::factory()->promotion()->create();

        Livewire::test(ListOffers::class)
            ->filterTable('offer_type', OfferType::Standard->value)
            ->assertCanSeeTableRecords([$standard])
            ->assertCanNotSeeTableRecords([$promotion]);
    }

    // ── Create Page (Wizard) ─────────────────────────────────────

    public function test_create_page_renders(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(CreateOffer::class)
            ->assertSuccessful();
    }

    public function test_can_create_offer_via_wizard(): void
    {
        $this->actingAsSuperAdmin();

        $sku = SellableSku::factory()->active()->create();
        Allocation::factory()->active()->create([
            'wine_variant_id' => $sku->wine_variant_id,
            'format_id' => $sku->format_id,
        ]);
        $channel = Channel::factory()->create();
        $priceBook = PriceBook::factory()->active()->create();

        Livewire::test(CreateOffer::class)
            ->fillForm([
                'sellable_sku_id' => $sku->id,
                'channel_id' => $channel->id,
                'price_book_id' => $priceBook->id,
                'benefit_type' => BenefitType::None->value,
                'name' => 'Test Wizard Offer',
                'offer_type' => OfferType::Standard->value,
                'visibility' => OfferVisibility::Public->value,
                'valid_from' => now()->toDateTimeString(),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('offers', [
            'name' => 'Test Wizard Offer',
            'sellable_sku_id' => $sku->id,
            'channel_id' => $channel->id,
            'price_book_id' => $priceBook->id,
            'offer_type' => OfferType::Standard->value,
            'status' => OfferStatus::Draft->value,
        ]);
    }

    public function test_create_validates_required_fields(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(CreateOffer::class)
            ->fillForm([
                'sellable_sku_id' => null,
                'name' => null,
            ])
            ->call('create')
            ->assertHasFormErrors(['sellable_sku_id' => 'required', 'name' => 'required']);
    }

    // ── Edit Page ───────────────────────────────────────────────

    public function test_edit_page_renders(): void
    {
        $this->actingAsSuperAdmin();

        $offer = Offer::factory()->create(['status' => OfferStatus::Draft]);

        Livewire::test(EditOffer::class, ['record' => $offer->id])
            ->assertSuccessful();
    }

    public function test_can_update_offer(): void
    {
        $this->actingAsSuperAdmin();

        $offer = Offer::factory()->create(['status' => OfferStatus::Draft]);
        $channel = Channel::factory()->create();
        $priceBook = PriceBook::factory()->create();

        Livewire::test(EditOffer::class, ['record' => $offer->id])
            ->fillForm([
                'name' => 'Updated Offer Name',
                'sellable_sku_id' => $offer->sellable_sku_id,
                'channel_id' => $channel->id,
                'price_book_id' => $priceBook->id,
                'offer_type' => OfferType::Promotion->value,
                'visibility' => OfferVisibility::Restricted->value,
                'valid_from' => now()->toDateTimeString(),
                'status' => OfferStatus::Draft->value,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('offers', [
            'id' => $offer->id,
            'name' => 'Updated Offer Name',
            'offer_type' => OfferType::Promotion->value,
        ]);
    }

    // ── View Page ───────────────────────────────────────────────

    public function test_view_page_renders(): void
    {
        $this->actingAsSuperAdmin();

        $offer = Offer::factory()->create();

        Livewire::test(ViewOffer::class, ['record' => $offer->id])
            ->assertSuccessful();
    }

    // ── Authorization ───────────────────────────────────────────

    public function test_viewer_can_access_list(): void
    {
        $this->actingAsViewer();

        Livewire::test(ListOffers::class)
            ->assertSuccessful();
    }
}
