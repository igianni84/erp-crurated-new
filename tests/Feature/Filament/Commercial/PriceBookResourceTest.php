<?php

namespace Tests\Feature\Filament\Commercial;

use App\Enums\Commercial\PriceBookStatus;
use App\Filament\Resources\PriceBookResource\Pages\CreatePriceBook;
use App\Filament\Resources\PriceBookResource\Pages\EditPriceBook;
use App\Filament\Resources\PriceBookResource\Pages\ListPriceBooks;
use App\Filament\Resources\PriceBookResource\Pages\ViewPriceBook;
use App\Models\Commercial\PriceBook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\FilamentTestHelpers;
use Tests\TestCase;

class PriceBookResourceTest extends TestCase
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

        Livewire::test(ListPriceBooks::class)
            ->assertSuccessful();
    }

    public function test_list_shows_price_books(): void
    {
        $this->actingAsSuperAdmin();

        $priceBooks = PriceBook::factory()->count(3)->create();

        Livewire::test(ListPriceBooks::class)
            ->assertCanSeeTableRecords($priceBooks);
    }

    public function test_list_can_filter_by_status(): void
    {
        $this->actingAsSuperAdmin();

        $draft = PriceBook::factory()->create(['status' => PriceBookStatus::Draft]);
        $active = PriceBook::factory()->active()->create();

        Livewire::test(ListPriceBooks::class)
            ->filterTable('status', PriceBookStatus::Draft->value)
            ->assertCanSeeTableRecords([$draft])
            ->assertCanNotSeeTableRecords([$active]);
    }

    public function test_list_can_search_by_name(): void
    {
        $this->actingAsSuperAdmin();

        $target = PriceBook::factory()->create(['name' => 'Unique Premium Price Book']);
        $other = PriceBook::factory()->create(['name' => 'Another Price Book']);

        Livewire::test(ListPriceBooks::class)
            ->searchTable('Unique Premium')
            ->assertCanSeeTableRecords([$target])
            ->assertCanNotSeeTableRecords([$other]);
    }

    // ── Create Page ─────────────────────────────────────────────

    public function test_create_page_renders(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(CreatePriceBook::class)
            ->assertSuccessful();
    }

    public function test_can_create_price_book(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(CreatePriceBook::class)
            ->fillForm([
                'name' => 'Test Price Book',
                'market' => 'EU',
                'currency' => 'EUR',
                'valid_from' => '2026-01-01',
                'status' => PriceBookStatus::Draft->value,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('price_books', [
            'name' => 'Test Price Book',
            'market' => 'EU',
            'currency' => 'EUR',
            'status' => PriceBookStatus::Draft->value,
        ]);
    }

    public function test_create_validates_required_fields(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(CreatePriceBook::class)
            ->fillForm([
                'name' => null,
                'market' => null,
                'currency' => null,
                'valid_from' => null,
            ])
            ->call('create')
            ->assertHasFormErrors(['name' => 'required', 'market' => 'required', 'currency' => 'required', 'valid_from' => 'required']);
    }

    // ── Edit Page ───────────────────────────────────────────────

    public function test_edit_page_renders(): void
    {
        $this->actingAsSuperAdmin();

        $priceBook = PriceBook::factory()->create(['status' => PriceBookStatus::Draft]);

        Livewire::test(EditPriceBook::class, ['record' => $priceBook->id])
            ->assertSuccessful();
    }

    public function test_can_update_price_book(): void
    {
        $this->actingAsSuperAdmin();

        $priceBook = PriceBook::factory()->create(['status' => PriceBookStatus::Draft]);

        Livewire::test(EditPriceBook::class, ['record' => $priceBook->id])
            ->fillForm([
                'name' => 'Updated Price Book',
                'market' => 'UK',
                'currency' => 'GBP',
                'valid_from' => '2026-01-01',
                'status' => PriceBookStatus::Draft->value,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('price_books', [
            'id' => $priceBook->id,
            'name' => 'Updated Price Book',
            'market' => 'UK',
            'currency' => 'GBP',
        ]);
    }

    // ── View Page ───────────────────────────────────────────────

    public function test_view_page_renders(): void
    {
        $this->actingAsSuperAdmin();

        $priceBook = PriceBook::factory()->create();

        Livewire::test(ViewPriceBook::class, ['record' => $priceBook->id])
            ->assertSuccessful();
    }

    // ── Authorization ───────────────────────────────────────────

    public function test_viewer_can_access_list(): void
    {
        $this->actingAsViewer();

        Livewire::test(ListPriceBooks::class)
            ->assertSuccessful();
    }
}
