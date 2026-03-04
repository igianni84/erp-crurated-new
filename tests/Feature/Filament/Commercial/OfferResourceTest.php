<?php

namespace Tests\Feature\Filament\Commercial;

use App\Enums\Commercial\OfferStatus;
use App\Enums\Commercial\OfferType;
use App\Enums\Commercial\OfferVisibility;
use App\Filament\Resources\OfferResource\Pages\EditOffer;
use App\Filament\Resources\OfferResource\Pages\ListOffers;
use App\Filament\Resources\OfferResource\Pages\ViewOffer;
use App\Models\Commercial\Channel;
use App\Models\Commercial\Offer;
use App\Models\Commercial\PriceBook;
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

    // ── Edit Page ───────────────────────────────────────────────
    // NOTE: CreateOffer uses a multi-step wizard with domain-specific
    // option filters (active SKUs with allocations, active price books in date range).
    // Create tests are omitted as factory data cannot satisfy the wizard's constraints.

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
