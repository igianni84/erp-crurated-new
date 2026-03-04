<?php

namespace Tests\Feature\Filament\Customer;

use App\Enums\Customer\BlockStatus;
use App\Enums\Customer\BlockType;
use App\Filament\Resources\Customer\OperationalBlockResource\Pages\ListOperationalBlocks;
use App\Models\Customer\Customer;
use App\Models\Customer\OperationalBlock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\FilamentTestHelpers;
use Tests\TestCase;

class OperationalBlockResourceTest extends TestCase
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

        Livewire::test(ListOperationalBlocks::class)
            ->assertSuccessful();
    }

    public function test_list_shows_operational_blocks(): void
    {
        $this->actingAsSuperAdmin();

        $customer = Customer::factory()->create();

        $blocks = collect();
        for ($i = 0; $i < 3; $i++) {
            $blocks->push(OperationalBlock::create([
                'blockable_type' => Customer::class,
                'blockable_id' => $customer->id,
                'block_type' => BlockType::Payment,
                'reason' => 'Test block reason '.$i,
                'status' => BlockStatus::Active,
            ]));
        }

        Livewire::test(ListOperationalBlocks::class)
            ->assertCanSeeTableRecords($blocks);
    }

    // ── Authorization ───────────────────────────────────────────

    public function test_viewer_can_access_list(): void
    {
        $this->actingAsViewer();

        Livewire::test(ListOperationalBlocks::class)
            ->assertSuccessful();
    }
}
