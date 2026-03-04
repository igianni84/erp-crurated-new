<?php

namespace Tests\Feature\Filament\Pim;

use App\Filament\Resources\Pim\ProducerResource\Pages\CreateProducer;
use App\Filament\Resources\Pim\ProducerResource\Pages\EditProducer;
use App\Filament\Resources\Pim\ProducerResource\Pages\ListProducers;
use App\Models\Pim\Country;
use App\Models\Pim\Producer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\FilamentTestHelpers;
use Tests\TestCase;

class ProducerResourceTest extends TestCase
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

        Livewire::test(ListProducers::class)
            ->assertSuccessful();
    }

    public function test_list_shows_producers(): void
    {
        $this->actingAsSuperAdmin();

        $producers = Producer::factory()->count(3)->create();

        Livewire::test(ListProducers::class)
            ->assertCanSeeTableRecords($producers);
    }

    // ── Create Page ─────────────────────────────────────────────

    public function test_create_page_renders(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(CreateProducer::class)
            ->assertSuccessful();
    }

    public function test_can_create_producer(): void
    {
        $this->actingAsSuperAdmin();

        $country = Country::factory()->create();

        Livewire::test(CreateProducer::class)
            ->fillForm([
                'name' => 'Test Producer Wines',
                'country_id' => $country->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('producers', [
            'name' => 'Test Producer Wines',
            'country_id' => $country->id,
        ]);
    }

    public function test_create_validates_required_fields(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(CreateProducer::class)
            ->fillForm([
                'name' => null,
                'country_id' => null,
            ])
            ->call('create')
            ->assertHasFormErrors(['name' => 'required', 'country_id' => 'required']);
    }

    // ── Edit Page ───────────────────────────────────────────────

    public function test_edit_page_renders(): void
    {
        $this->actingAsSuperAdmin();

        $producer = Producer::factory()->create();

        Livewire::test(EditProducer::class, ['record' => $producer->id])
            ->assertSuccessful();
    }

    public function test_can_update_producer(): void
    {
        $this->actingAsSuperAdmin();

        $producer = Producer::factory()->create();

        Livewire::test(EditProducer::class, ['record' => $producer->id])
            ->fillForm([
                'name' => 'Updated Producer',
                'website' => 'https://example.com',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('producers', [
            'id' => $producer->id,
            'name' => 'Updated Producer',
            'website' => 'https://example.com',
        ]);
    }

    // ── Authorization ───────────────────────────────────────────

    public function test_viewer_can_access_list(): void
    {
        $this->actingAsViewer();

        Livewire::test(ListProducers::class)
            ->assertSuccessful();
    }
}
