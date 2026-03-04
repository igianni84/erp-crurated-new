<?php

namespace Tests\Feature\Filament\Fulfillment;

use App\Filament\Resources\Fulfillment\ShippingOrderExceptionResource\Pages\ListShippingOrderExceptions;
use App\Filament\Resources\Fulfillment\ShippingOrderExceptionResource\Pages\ViewShippingOrderException;
use App\Models\Fulfillment\ShippingOrderException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\FilamentTestHelpers;
use Tests\TestCase;

class ShippingOrderExceptionResourceTest extends TestCase
{
    use FilamentTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFilamentTestHelpers();
    }

    // ── List Page (Read-Only) ───────────────────────────────────

    public function test_list_page_renders(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(ListShippingOrderExceptions::class)
            ->assertSuccessful();
    }

    public function test_list_shows_shipping_order_exceptions(): void
    {
        $this->actingAsSuperAdmin();

        $shippingOrderExceptions = ShippingOrderException::factory()->count(3)->create();

        Livewire::test(ListShippingOrderExceptions::class)
            ->assertCanSeeTableRecords($shippingOrderExceptions);
    }

    // ── View Page ───────────────────────────────────────────────

    public function test_view_page_renders(): void
    {
        $this->actingAsSuperAdmin();

        $shippingOrderException = ShippingOrderException::factory()->create();

        Livewire::test(ViewShippingOrderException::class, ['record' => $shippingOrderException->id])
            ->assertSuccessful();
    }

    // ── Authorization ───────────────────────────────────────────

    public function test_viewer_can_access_list(): void
    {
        $this->actingAsViewer();

        Livewire::test(ListShippingOrderExceptions::class)
            ->assertSuccessful();
    }
}
